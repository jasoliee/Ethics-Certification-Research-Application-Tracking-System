<?php

namespace App\Services\Identity;

use App\Enums\ApplicantType;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Str;

class AccountTypeCatalog
{
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

    /** @param array<string, mixed> $type @param array<string, string> $row */
    public function isExampleRow(array $type, array $row): bool
    {
        foreach ($type['template_headers'] as $header) {
            $provided = Str::lower(Str::squish((string) ($row[$header] ?? '')));
            $example = Str::lower(Str::squish((string) ($type['example_row'][$header] ?? '')));

            if ($provided !== $example) {
                return false;
            }
        }

        return true;
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
                'required_headers' => ['first_name', 'last_name', 'email', 'student_number', 'year_level'],
                'optional_headers' => ['middle_name', 'suffix', 'phone_number', 'institution', 'department', 'program'],
                'template_headers' => ['first_name', 'middle_name', 'last_name', 'suffix', 'email', 'student_number', 'phone_number', 'year_level', 'institution', 'department', 'program'],
                'example_row' => [
                    'first_name' => 'Alexandra',
                    'middle_name' => 'M.',
                    'last_name' => 'Reyes',
                    'suffix' => '',
                    'email' => 'alexandra.reyes@example.com',
                    'student_number' => '2026-0001',
                    'phone_number' => '09171234567',
                    'year_level' => 'Fourth Year',
                    'institution' => 'Institute of Computing and Digital Innovation',
                    'department' => 'Computer Studies',
                    'program' => 'Bachelor of Science in Information Systems',
                ],
            ],
            'faculty_researcher' => [
                'key' => $key,
                'label' => 'Faculty Researcher',
                'description' => 'Can prepare and manage faculty research applications.',
                'role' => UserRole::Applicant->value,
                'applicant_type' => ApplicantType::Faculty->value,
                'icon' => 'user-check',
                'identifier_header' => 'employee_id',
                'required_headers' => ['first_name', 'last_name', 'email', 'employee_id'],
                'optional_headers' => ['middle_name', 'suffix', 'phone_number', 'institution', 'department', 'program', 'position_title'],
                'template_headers' => ['first_name', 'middle_name', 'last_name', 'suffix', 'email', 'employee_id', 'phone_number', 'institution', 'department', 'program', 'position_title'],
                'example_row' => [
                    'first_name' => 'Marian',
                    'middle_name' => 'L.',
                    'last_name' => 'Santos',
                    'suffix' => '',
                    'email' => 'marian.santos@example.com',
                    'employee_id' => 'EMP-2026-001',
                    'phone_number' => '09181234567',
                    'institution' => 'Institute of Science and Mathematics',
                    'department' => 'Natural Sciences',
                    'program' => 'Bachelor of Science in Biology',
                    'position_title' => 'Faculty Researcher',
                ],
            ],
            'adviser' => [
                'key' => $key,
                'label' => 'Research Adviser',
                'description' => 'Can review and endorse assigned applicant submissions.',
                'role' => UserRole::Adviser->value,
                'applicant_type' => null,
                'icon' => 'user-check',
                'identifier_header' => 'employee_id',
                'required_headers' => ['first_name', 'last_name', 'email', 'employee_id', 'position_title'],
                'optional_headers' => ['middle_name', 'suffix', 'phone_number', 'institution', 'department'],
                'template_headers' => ['first_name', 'middle_name', 'last_name', 'suffix', 'email', 'employee_id', 'phone_number', 'institution', 'department', 'position_title'],
                'example_row' => [
                    'first_name' => 'Roberto',
                    'middle_name' => 'D.',
                    'last_name' => 'Garcia',
                    'suffix' => 'Jr.',
                    'email' => 'roberto.garcia@example.com',
                    'employee_id' => 'EMP-2026-002',
                    'phone_number' => '09191234567',
                    'institution' => 'Institute of Engineering',
                    'department' => 'Engineering Studies',
                    'position_title' => 'Research Adviser',
                ],
            ],
            'reviewer' => [
                'key' => $key,
                'label' => 'Ethics Reviewer',
                'description' => 'Can evaluate assigned anonymized ethics applications.',
                'role' => UserRole::Reviewer->value,
                'applicant_type' => null,
                'icon' => 'users',
                'identifier_header' => 'employee_id',
                'required_headers' => ['first_name', 'last_name', 'email', 'employee_id', 'reviewer_classification'],
                'optional_headers' => ['middle_name', 'suffix', 'phone_number', 'institution', 'department', 'position_title'],
                'template_headers' => ['first_name', 'middle_name', 'last_name', 'suffix', 'email', 'employee_id', 'phone_number', 'institution', 'department', 'position_title', 'reviewer_classification'],
                'example_row' => [
                    'first_name' => 'Lourdes',
                    'middle_name' => 'P.',
                    'last_name' => 'Navarro',
                    'suffix' => '',
                    'email' => 'lourdes.navarro@example.com',
                    'employee_id' => 'EMP-2026-003',
                    'phone_number' => '09201234567',
                    'institution' => 'Institute of Behavioral Sciences',
                    'department' => 'Behavioral Sciences',
                    'position_title' => 'Ethics Reviewer',
                    'reviewer_classification' => 'Expedited',
                ],
            ],
            default => throw new AuthorizationException('Unknown account type.'),
        };
    }
}
