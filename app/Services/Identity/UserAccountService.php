<?php

namespace App\Services\Identity;

use App\Enums\AccountStatus;
use App\Enums\ApplicantType;
use App\Enums\ProfileOptionField;
use App\Enums\ReviewerClassification;
use App\Enums\UserRole;
use App\Models\User;
use App\Notifications\UsernameChangedNotification;
use App\Services\AuditLogService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class UserAccountService
{
    public function __construct(
        private readonly AccountCreationAuthorizationService $authorization,
        private readonly UsernameGenerator $usernameGenerator,
        private readonly ProfileOptionCatalog $profileOptions,
        private readonly AuditLogService $auditLog,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     *
     * @throws AuthorizationException|ValidationException
     */
    public function create(User $actor, array $attributes, ?string $expectedUsername = null): User
    {
        $validated = $this->validateCreation($actor, $attributes);

        return DB::transaction(function () use ($actor, $expectedUsername, $validated): User {
            $targetRole = UserRole::from($validated['role']);
            $applicantType = $targetRole === UserRole::Applicant
                ? ApplicantType::from($validated['applicant_type'])
                : null;
            $username = $this->usernameGenerator->generate(
                $validated['institutional_identifier'],
                $validated['last_name'],
            );

            if ($expectedUsername !== null && $username !== $expectedUsername) {
                throw ValidationException::withMessages([
                    'import_token' => 'The import preview is stale. Validate the file again before confirming.',
                ]);
            }

            // Compatibility name and username are generated server-side and cannot be overridden by the request.
            $user = User::create([
                ...$this->profileValues($validated, $targetRole, $applicantType),
                'name' => User::formatName(
                    $validated['first_name'],
                    $validated['middle_name'] ?? null,
                    $validated['last_name'],
                    $validated['suffix'] ?? null,
                ),
                'username' => $username,
                'password' => Hash::make(Str::random(64)),
                'password_changed_at' => null,
                'password_setup_completed_at' => null,
                'onboarding_completed_at' => null,
                'setup_email_status' => 'not_sent',
                'role' => $targetRole,
                'applicant_type' => $applicantType,
                'account_status' => AccountStatus::PendingSetup->value,
                'created_by_user_id' => $actor->id,
            ]);

            $this->auditLog->record($actor, 'user.created', $user, [
                'role' => $targetRole->value,
                'applicant_type' => $applicantType?->value,
                'username' => $username,
            ]);

            return $user;
        });
    }

    /** @param array<string, mixed> $attributes */
    public function updateProfile(User $actor, User $subject, array $attributes): User
    {
        Gate::forUser($actor)->authorize('update', $subject);
        $normalized = $this->normalizeProfile($attributes);

        if (($normalized['last_name'] ?? null) !== $subject->last_name
            || ($normalized['institutional_identifier'] ?? null) !== $subject->institutional_identifier) {
            throw ValidationException::withMessages([
                'identity' => 'Use the confirmed identity correction action to change surname or institutional identifier.',
            ]);
        }

        $validated = validator(
            $normalized,
            $this->profileRules($subject, $subject->role, $subject->applicant_type),
            $this->profileValidationMessages(),
        )->validate();

        return DB::transaction(function () use ($actor, $subject, $validated): User {
            $subject->fill([
                ...$this->profileValues($validated, $subject->role, $subject->applicant_type),
                'name' => User::formatName(
                    $validated['first_name'],
                    $validated['middle_name'] ?? null,
                    $validated['last_name'],
                    $validated['suffix'] ?? null,
                ),
            ]);
            $changedFields = array_keys($subject->getDirty());
            $subject->save();

            $this->auditLog->record($actor, 'user.profile_updated', $subject, [
                'changed_fields' => array_values(array_diff($changedFields, ['updated_at'])),
            ]);

            return $subject->refresh();
        });
    }

    /** @param array<string, mixed> $attributes */
    public function regenerateUsername(User $actor, User $subject, array $attributes): User
    {
        Gate::forUser($actor)->authorize('update', $subject);
        $lastName = Str::squish((string) ($attributes['last_name'] ?? ''));
        $identifier = Str::upper(trim((string) ($attributes['institutional_identifier'] ?? '')));
        $validated = validator([
            ...$attributes,
            'last_name' => $lastName,
            'institutional_identifier' => $identifier,
        ], [
            'last_name' => ['required', 'string', 'max:100'],
            'institutional_identifier' => [
                'required',
                'string',
                'max:50',
                'regex:/^[A-Z0-9][A-Z0-9._-]*$/i',
                Rule::unique('users', 'institutional_identifier')->ignore($subject->id),
            ],
            'confirm_username_regeneration' => ['accepted'],
        ])->validate();

        if ($validated['last_name'] === $subject->last_name
            && $validated['institutional_identifier'] === $subject->institutional_identifier) {
            throw ValidationException::withMessages(['identity' => 'Change the surname or institutional identifier before confirming.']);
        }

        $previousUsername = $subject->username;
        $updated = DB::transaction(function () use ($actor, $subject, $validated, $previousUsername): User {
            $username = $this->usernameGenerator->generate(
                $validated['institutional_identifier'],
                $validated['last_name'],
            );
            $subject->forceFill([
                'last_name' => $validated['last_name'],
                'institutional_identifier' => $validated['institutional_identifier'],
                'username' => $username,
                'name' => User::formatName($subject->first_name, $subject->middle_name, $validated['last_name'], $subject->suffix),
            ])->save();

            $this->auditLog->record($actor, 'user.username_regenerated', $subject, [
                'previous_username' => $previousUsername,
                'new_username' => $username,
                'result' => 'updated',
            ]);

            return $subject->refresh();
        });

        try {
            $updated->notify(new UsernameChangedNotification($updated->username));
        } catch (Throwable) {
            $this->auditLog->record($actor, 'user.username_notification_failed', $updated, ['result' => 'failed']);
        }

        return $updated;
    }

    public function changeStatus(User $actor, User $subject, AccountStatus|string $status): User
    {
        Gate::forUser($actor)->authorize('changeStatus', $subject);
        $status = $status instanceof AccountStatus ? $status : AccountStatus::tryFrom($status);

        if (! $status) {
            throw ValidationException::withMessages(['account_status' => 'Select a valid account status.']);
        }

        if ($status === AccountStatus::Active && ! $subject->password_setup_completed_at) {
            throw ValidationException::withMessages([
                'account_status' => 'The account cannot be activated until password setup is complete.',
            ]);
        }

        return DB::transaction(function () use ($actor, $subject, $status): User {
            $previousStatus = $subject->account_status;
            $subject->update(['account_status' => $status->value]);

            $this->auditLog->record($actor, 'user.status_changed', $subject, [
                'from' => $previousStatus,
                'to' => $status->value,
            ]);

            return $subject->refresh();
        });
    }

    /**
     * Validate all account fields for direct creation and preflighted bulk imports.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function validateCreation(User $actor, array $attributes, bool $checkDatabaseUniqueness = true): array
    {
        $attributes = $this->normalizeProfile($attributes);
        $attributes['role'] = $attributes['role'] instanceof UserRole
            ? $attributes['role']->value
            : (string) ($attributes['role'] ?? '');
        $attributes['applicant_type'] = $attributes['applicant_type'] instanceof ApplicantType
            ? $attributes['applicant_type']->value
            : ($attributes['applicant_type'] ?? null);

        $targetRole = UserRole::tryFrom($attributes['role']);

        if (! $targetRole || ! $this->authorization->canCreate($actor, $targetRole)) {
            throw new AuthorizationException('You are not allowed to create this account type.');
        }

        $applicantType = $targetRole === UserRole::Applicant
            ? ApplicantType::tryFrom((string) ($attributes['applicant_type'] ?? ''))
            : null;
        $rules = [
            ...$this->profileRules(null, $targetRole, $applicantType, $checkDatabaseUniqueness),
            'role' => ['required', Rule::enum(UserRole::class)],
            'applicant_type' => [
                Rule::requiredIf($targetRole === UserRole::Applicant),
                'nullable',
                Rule::enum(ApplicantType::class),
            ],
        ];

        return validator($attributes, $rules, $this->profileValidationMessages())->validate();
    }

    /** @return array<string, array<int, mixed>> */
    private function profileRules(
        ?User $subject = null,
        ?UserRole $targetRole = null,
        ?ApplicantType $applicantType = null,
        bool $checkDatabaseUniqueness = true,
    ): array {
        return [
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'suffix' => ['nullable', 'string', 'max:30'],
            'email' => array_values(array_filter([
                'required',
                'email:rfc',
                'max:255',
                $checkDatabaseUniqueness ? Rule::unique('users', 'email')->ignore($subject?->id) : null,
            ])),
            'institutional_identifier' => array_values(array_filter([
                'required',
                'string',
                'max:50',
                'regex:/^[A-Z0-9][A-Z0-9._-]*$/i',
                $checkDatabaseUniqueness ? Rule::unique('users', 'institutional_identifier')->ignore($subject?->id) : null,
            ])),
            'phone_number' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+().\s-]+$/'],
            'institution' => [
                'nullable',
                'string',
                'max:150',
                Rule::in($this->profileOptions->values(ProfileOptionField::Institution, $subject?->institution)),
            ],
            'department' => [
                'nullable',
                'string',
                'max:150',
                Rule::in($this->profileOptions->values(ProfileOptionField::Department, $subject?->department)),
            ],
            'program' => [
                'nullable',
                'string',
                'max:150',
                Rule::in($this->profileOptions->values(ProfileOptionField::Program, $subject?->program)),
            ],
            'year_level' => [
                Rule::requiredIf($targetRole === UserRole::Applicant && $applicantType === ApplicantType::Student),
                'nullable',
                'string',
                'max:30',
                Rule::in($this->profileOptions->values(ProfileOptionField::YearLevel, $subject?->year_level)),
            ],
            'position_title' => [Rule::requiredIf($targetRole === UserRole::Adviser), 'nullable', 'string', 'max:150'],
            'reviewer_classification' => [
                Rule::requiredIf($targetRole === UserRole::Reviewer),
                'nullable',
                Rule::enum(ReviewerClassification::class),
            ],
            'reviewer_capacity' => [
                Rule::requiredIf($targetRole === UserRole::Reviewer),
                'nullable',
                'integer',
                'between:1,30',
            ],
        ];
    }

    /** @param array<string, mixed> $attributes @return array<string, mixed> */
    private function normalizeProfile(array $attributes): array
    {
        if (($attributes['reviewer_classification'] ?? null) instanceof ReviewerClassification) {
            $attributes['reviewer_classification'] = $attributes['reviewer_classification']->value;
        }

        foreach (['first_name', 'middle_name', 'last_name', 'suffix', 'phone_number', 'institution', 'department', 'program', 'year_level', 'position_title', 'reviewer_classification'] as $field) {
            $attributes[$field] = filled($attributes[$field] ?? null)
                ? Str::squish((string) $attributes[$field])
                : null;
        }

        if (filled($attributes['reviewer_classification'] ?? null)) {
            $classification = Str::of((string) $attributes['reviewer_classification'])
                ->lower()
                ->replace(['-', ' '], '_')
                ->value();
            $attributes['reviewer_classification'] = match ($classification) {
                'expedited_review' => ReviewerClassification::Expedited->value,
                'full_board_review' => ReviewerClassification::FullBoard->value,
                default => $classification,
            };
        }

        $attributes['email'] = Str::lower(trim((string) ($attributes['email'] ?? '')));
        $attributes['institutional_identifier'] = Str::upper(trim((string) ($attributes['institutional_identifier'] ?? '')));

        return $attributes;
    }

    /** @return array<string, string> */
    private function profileValidationMessages(): array
    {
        return [
            'email.email' => 'Email must be a valid address such as name@example.com.',
            'institutional_identifier.regex' => 'Use only letters, numbers, periods, underscores, and hyphens for the institutional identifier.',
            'institution.in' => $this->profileOptions->validationMessage(ProfileOptionField::Institution),
            'department.in' => $this->profileOptions->validationMessage(ProfileOptionField::Department),
            'program.in' => $this->profileOptions->validationMessage(ProfileOptionField::Program),
            'year_level.in' => $this->profileOptions->validationMessage(ProfileOptionField::YearLevel),
            'reviewer_classification.enum' => 'Reviewer Classification must be Expedited, Full Board, or Exempted.',
            'reviewer_capacity.between' => 'Reviewer Capacity must be between 1 and 30.',
        ];
    }

    /** @param array<string, mixed> $validated @return array<string, mixed> */
    private function profileValues(
        array $validated,
        UserRole $targetRole,
        ?ApplicantType $applicantType,
    ): array {
        $values = collect($validated)->only([
            'first_name',
            'middle_name',
            'last_name',
            'suffix',
            'email',
            'institutional_identifier',
            'phone_number',
            'institution',
            'department',
            'program',
            'year_level',
            'position_title',
            'reviewer_classification',
            'reviewer_capacity',
        ])->all();

        if ($targetRole !== UserRole::Applicant) {
            $values['program'] = null;
            $values['year_level'] = null;
        } elseif ($applicantType !== ApplicantType::Student) {
            $values['year_level'] = null;
        }

        if ($targetRole !== UserRole::Reviewer) {
            $values['reviewer_classification'] = null;
            $values['reviewer_capacity'] = null;
        }

        return $values;
    }
}
