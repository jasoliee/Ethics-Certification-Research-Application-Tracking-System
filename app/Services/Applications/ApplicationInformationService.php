<?php

namespace App\Services\Applications;

use App\Enums\AccountStatus;
use App\Enums\ApplicantType;
use App\Enums\ProfileOptionField;
use App\Enums\ResearchType;
use App\Enums\UserRole;
use App\Models\ResearchApplication;
use App\Models\User;
use App\Services\Identity\ProfileOptionCatalog;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Keeps applicant form options and server-side information validation synchronized.
 */
class ApplicationInformationService
{
    public function __construct(private readonly ProfileOptionCatalog $profileOptions) {}

    /**
     * Return database-backed form options while retaining the application's current historical values.
     *
     * @return array<string, array<int, string>>
     */
    public function profileOptions(User $applicant, ?ResearchApplication $application = null): array
    {
        // Use the application snapshot first and the account profile second for legacy-value continuity.
        return [
            ProfileOptionField::Institution->value => $this->profileOptions->values(
                ProfileOptionField::Institution,
                $application?->institution ?? $applicant->institution,
            ),
            ProfileOptionField::Department->value => $this->profileOptions->values(
                ProfileOptionField::Department,
                $application?->department ?? $applicant->department,
            ),
            ProfileOptionField::Program->value => $this->profileOptions->values(
                ProfileOptionField::Program,
                $application?->program ?? $applicant->program,
            ),
        ];
    }

    /**
     * Return active Adviser records that applicants may select.
     *
     * @return Collection<int, User>
     */
    public function advisers(User $applicant): Collection
    {
        $isStudent = ($applicant->applicant_type ?? ApplicantType::Student) === ApplicantType::Student;

        if ($isStudent && blank($applicant->department)) {
            return collect();
        }

        // Exclude archived, inactive, and incomplete-setup accounts from the trusted Adviser selector.
        return User::query()
            ->select(['id', 'name', 'email', 'institution', 'department'])
            ->where('role', UserRole::Adviser->value)
            ->where('account_status', AccountStatus::Active->value)
            ->whereNotNull('password_setup_completed_at')
            ->whereKeyNot($applicant->id)
            ->when($isStudent, fn ($query) => $query->where('department', $applicant->department))
            ->orderBy('name')
            ->get();
    }

    /**
     * Build the shared validation contract for draft updates and final submission.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(
        User $applicant,
        ?ResearchApplication $application = null,
        bool $allowLegacyDuration = false,
    ): array {
        $options = $this->profileOptions($applicant, $application);
        $isStudent = ($applicant->applicant_type ?? ApplicantType::Student) === ApplicantType::Student;

        // Adviser eligibility is rechecked against the authoritative active user row.
        $adviserRule = Rule::exists('users', 'id')->where(
            function (Builder $query) use ($applicant, $isStudent): Builder {
                $query
                    ->where('role', UserRole::Adviser->value)
                    ->where('account_status', AccountStatus::Active->value)
                    ->whereNull('deleted_at')
                    ->whereNotNull('password_setup_completed_at')
                    ->where('id', '!=', $applicant->id);

                if ($isStudent) {
                    if (blank($applicant->department)) {
                        return $query->whereRaw('1 = 0');
                    }

                    $query->where('department', $applicant->department);
                }

                return $query;
            },
        );

        // New writes require dates; persisted legacy records may retain their historical duration text.
        $durationRules = $allowLegacyDuration
            ? [
                'expected_start_date' => [
                    'nullable',
                    'date',
                    'required_without:expected_duration',
                    'required_with:expected_end_date',
                ],
                'expected_end_date' => [
                    'nullable',
                    'date',
                    'required_without:expected_duration',
                    'required_with:expected_start_date',
                    'after_or_equal:expected_start_date',
                ],
                'expected_duration' => ['nullable', 'string', 'max:120'],
            ]
            : [
                'expected_start_date' => ['required', 'date'],
                'expected_end_date' => ['required', 'date', 'after_or_equal:expected_start_date'],
            ];

        // Every required information field is shared by save and final-submit validation.
        return [
            'research_title' => ['required', 'string', 'max:255'],
            'research_type' => ['required', Rule::enum(ResearchType::class)],
            'research_category' => ['required', 'string', 'max:150'],
            'institution' => ['required', 'string', 'max:150', Rule::in($options[ProfileOptionField::Institution->value])],
            'department' => ['required', 'string', 'max:150', Rule::in($options[ProfileOptionField::Department->value])],
            'program' => [
                Rule::requiredIf($isStudent),
                'nullable',
                'string',
                'max:150',
                Rule::in($options[ProfileOptionField::Program->value]),
            ],
            'adviser_user_id' => ['required', 'integer', $adviserRule],
            'abstract' => ['required', 'string', 'max:5000'],
            'target_participants' => ['required', 'string', 'max:2000'],
            ...$durationRules,
        ];
    }

    /**
     * Revalidate the persisted application snapshot immediately before final submission.
     *
     * @return array<string, mixed>
     */
    public function validateApplication(ResearchApplication $application): array
    {
        $application->loadMissing('applicant');

        // Prefixing is handled by the caller's error display; validation keeps ordinary field keys.
        return Validator::make(
            $this->applicationData($application),
            $this->rules($application->applicant, $application, true),
            $this->messages(),
        )->validate();
    }

    /**
     * Report persisted information readiness without throwing during checklist rendering.
     *
     * @return array{complete: bool, adviser_ready: bool, invalid_fields: array<int, string>}
     */
    public function summary(ResearchApplication $application): array
    {
        $application->loadMissing('applicant');
        $validator = Validator::make(
            $this->applicationData($application),
            $this->rules($application->applicant, $application, true),
            $this->messages(),
        );
        $complete = $validator->passes();
        $invalidFields = $validator->errors()->keys();

        return [
            'complete' => $complete,
            'adviser_ready' => ! in_array('adviser_user_id', $invalidFields, true),
            'invalid_fields' => $invalidFields,
        ];
    }

    /**
     * Return concise messages that explain database-backed and Adviser eligibility failures.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'institution.in' => 'Select an active Institution option.',
            'department.in' => 'Select an active Department option.',
            'program.in' => 'Select an active Program option.',
            'adviser_user_id.exists' => 'Select an active eligible Research Adviser. Student applicants must select one from their department.',
            'expected_start_date.required' => 'Enter the expected research starting date.',
            'expected_end_date.required' => 'Enter the expected research ending date.',
            'expected_end_date.after_or_equal' => 'The ending date must be on or after the starting date.',
        ];
    }

    /**
     * Convert enum-backed attributes to request-equivalent scalar values.
     *
     * @return array<string, mixed>
     */
    private function applicationData(ResearchApplication $application): array
    {
        return [
            'research_title' => $application->research_title,
            'research_type' => $application->research_type?->value,
            'research_category' => $application->research_category,
            'institution' => $application->institution,
            'department' => $application->department,
            'program' => $application->program,
            'adviser_user_id' => $application->adviser_user_id,
            'abstract' => $application->abstract,
            'target_participants' => $application->target_participants,
            'expected_duration' => $application->expected_duration,
            'expected_start_date' => $application->expected_start_date?->format('Y-m-d'),
            'expected_end_date' => $application->expected_end_date?->format('Y-m-d'),
        ];
    }
}
