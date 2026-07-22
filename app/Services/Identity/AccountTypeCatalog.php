<?php

namespace App\Services\Identity;

use App\Enums\ApplicantType;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

class AccountTypeCatalog
{
    public const TEMPLATE_VERSION = 'ECRATS-ACCOUNT-1.0';

    public function __construct(private readonly AccountCreationAuthorizationService $authorization) {}

    /** @return array<int, array<string, mixed>> */
    public function allowedFor(User $actor): array
    {
        $allowedRoles = $this->authorization->allowedRoles($actor);
        $types = [];

        if (in_array(UserRole::Applicant, $allowedRoles, true)) {
            $types[] = $this->definition('student_researcher');
            $types[] = $this->definition('faculty_researcher');
        }

        if (in_array(UserRole::Adviser, $allowedRoles, true)) {
            $types[] = $this->definition('adviser');
        }

        if (in_array(UserRole::Reviewer, $allowedRoles, true)) {
            $types[] = $this->definition('reviewer');
        }

        return $types;
    }

    /** @return array<string, mixed> */
    public function authorized(User $actor, string $key): array
    {
        $type = collect($this->allowedFor($actor))->firstWhere('key', $key);

        if (! $type) {
            throw new AuthorizationException('You are not allowed to create this account type.');
        }

        return $type;
    }

    /** @return array<string, mixed> */
    private function definition(string $key): array
    {
        return match ($key) {
            'student_researcher' => [
                'key' => $key,
                'label' => 'Student Researcher',
                'description' => 'Can prepare and manage student research applications.',
                'role' => UserRole::Applicant->value,
                'applicant_type' => ApplicantType::Student->value,
                'icon' => 'user',
                'identifier_header' => 'student_number',
                'required_headers' => ['template_version', 'applicant_type', 'first_name', 'last_name', 'email', 'student_number', 'year_level'],
                'optional_headers' => ['middle_name', 'suffix', 'phone_number', 'institution', 'program', 'department'],
            ],
            'faculty_researcher' => [
                'key' => $key,
                'label' => 'Faculty Researcher',
                'description' => 'Can prepare and manage faculty research applications.',
                'role' => UserRole::Applicant->value,
                'applicant_type' => ApplicantType::Faculty->value,
                'icon' => 'user-check',
                'identifier_header' => 'employee_id',
                'required_headers' => ['template_version', 'applicant_type', 'first_name', 'last_name', 'email', 'employee_id'],
                'optional_headers' => ['middle_name', 'suffix', 'phone_number', 'institution', 'program', 'department', 'position_title'],
            ],
            'adviser' => [
                'key' => $key,
                'label' => 'Research Adviser',
                'description' => 'Can review and endorse assigned applicant submissions.',
                'role' => UserRole::Adviser->value,
                'applicant_type' => null,
                'icon' => 'user-check',
                'identifier_header' => 'employee_id',
                'required_headers' => ['template_version', 'first_name', 'last_name', 'email', 'employee_id', 'position_title'],
                'optional_headers' => ['middle_name', 'suffix', 'phone_number', 'institution', 'department'],
            ],
            'reviewer' => [
                'key' => $key,
                'label' => 'Ethics Reviewer',
                'description' => 'Can evaluate assigned anonymized ethics applications.',
                'role' => UserRole::Reviewer->value,
                'applicant_type' => null,
                'icon' => 'users',
                'identifier_header' => 'employee_id',
                'required_headers' => ['template_version', 'first_name', 'last_name', 'email', 'employee_id', 'reviewer_classification', 'reviewer_capacity'],
                'optional_headers' => ['middle_name', 'suffix', 'phone_number', 'institution', 'department', 'position_title'],
            ],
            default => throw new AuthorizationException('Unknown account type.'),
        };
    }
}
