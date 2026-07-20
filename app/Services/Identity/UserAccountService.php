<?php

namespace App\Services\Identity;

use App\Enums\AccountStatus;
use App\Enums\ApplicantType;
use App\Enums\UserRole;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UserAccountService
{
    public function __construct(
        private readonly AccountCreationAuthorizationService $authorization,
        private readonly UsernameGenerator $usernameGenerator,
        private readonly AuditLogService $auditLog,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     *
     * @throws AuthorizationException|ValidationException
     */
    public function create(User $actor, array $attributes): User
    {
        $validated = $this->validateCreation($actor, $attributes);

        return DB::transaction(function () use ($actor, $validated): User {
            $targetRole = UserRole::from($validated['role']);
            $applicantType = $targetRole === UserRole::Applicant
                ? ApplicantType::from($validated['applicant_type'])
                : null;
            $username = $this->usernameGenerator->generate(
                $validated['first_name'],
                $validated['last_name'],
                $targetRole,
                $applicantType,
            );

            // Compatibility name and username are generated server-side and cannot be overridden by the request.
            $user = User::create([
                ...$this->profileValues($validated),
                'name' => User::formatName(
                    $validated['first_name'],
                    $validated['middle_name'] ?? null,
                    $validated['last_name'],
                    $validated['suffix'] ?? null,
                ),
                'username' => $username,
                'password' => Hash::make($validated['password']),
                'password_changed_at' => now(),
                'role' => $targetRole,
                'applicant_type' => $applicantType,
                'account_status' => AccountStatus::Active->value,
                'created_by_user_id' => $actor->id,
            ]);

            $this->auditLog->record($actor, 'user.created', $user, [
                'role' => $targetRole->value,
                'applicant_type' => $applicantType?->value,
            ]);

            return $user;
        });
    }

    /** @param array<string, mixed> $attributes */
    public function updateProfile(User $actor, User $subject, array $attributes): User
    {
        Gate::forUser($actor)->authorize('update', $subject);
        $validated = validator($this->normalizeProfile($attributes), $this->profileRules($subject))->validate();

        return DB::transaction(function () use ($actor, $subject, $validated): User {
            $subject->fill([
                ...$this->profileValues($validated),
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

    public function changeStatus(User $actor, User $subject, AccountStatus|string $status): User
    {
        Gate::forUser($actor)->authorize('changeStatus', $subject);
        $status = $status instanceof AccountStatus ? $status : AccountStatus::tryFrom($status);

        if (! $status) {
            throw ValidationException::withMessages(['account_status' => 'Select a valid account status.']);
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
    public function validateCreation(User $actor, array $attributes): array
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

        $rules = [
            ...$this->profileRules(),
            'password' => ['required', 'string', 'min:8', 'max:64', 'confirmed'],
            'password_confirmation' => ['required', 'string', 'max:64'],
            'role' => ['required', Rule::enum(UserRole::class)],
            'applicant_type' => [
                Rule::requiredIf($targetRole === UserRole::Applicant),
                'nullable',
                Rule::enum(ApplicantType::class),
            ],
        ];

        return validator($attributes, $rules, [
            'password.required' => 'Enter an initial password.',
            'password.min' => 'Password must be at least 8 characters.',
            'password.confirmed' => 'Password confirmation does not match.',
            'institutional_identifier.regex' => 'Use only letters, numbers, periods, underscores, and hyphens for the institutional identifier.',
        ])->validate();
    }

    /** @return array<string, array<int, mixed>> */
    private function profileRules(?User $subject = null): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'suffix' => ['nullable', 'string', 'max:30'],
            'email' => [
                'required',
                'email:rfc',
                'max:255',
                Rule::unique('users', 'email')->ignore($subject?->id),
            ],
            'institutional_identifier' => [
                'required',
                'string',
                'max:50',
                'regex:/^[A-Z0-9][A-Z0-9._-]*$/i',
                Rule::unique('users', 'institutional_identifier')->ignore($subject?->id),
            ],
            'phone_number' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+().\s-]+$/'],
            'institution' => ['nullable', 'string', 'max:150'],
            'department' => ['nullable', 'string', 'max:150'],
            'position_title' => ['nullable', 'string', 'max:150'],
        ];
    }

    /** @param array<string, mixed> $attributes @return array<string, mixed> */
    private function normalizeProfile(array $attributes): array
    {
        foreach (['first_name', 'middle_name', 'last_name', 'suffix', 'phone_number', 'institution', 'department', 'position_title'] as $field) {
            $attributes[$field] = filled($attributes[$field] ?? null)
                ? Str::squish((string) $attributes[$field])
                : null;
        }

        $attributes['email'] = Str::lower(trim((string) ($attributes['email'] ?? '')));
        $attributes['institutional_identifier'] = Str::upper(trim((string) ($attributes['institutional_identifier'] ?? '')));

        return $attributes;
    }

    /** @param array<string, mixed> $validated @return array<string, mixed> */
    private function profileValues(array $validated): array
    {
        return collect($validated)->only([
            'first_name',
            'middle_name',
            'last_name',
            'suffix',
            'email',
            'institutional_identifier',
            'phone_number',
            'institution',
            'department',
            'position_title',
        ])->all();
    }
}
