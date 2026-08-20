<?php

namespace App\Services\Identity;

use App\Enums\ApplicantType;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

class AccountTypeCatalog
{
    public const EXAMPLE_MARKER = 'EXAMPLE-ROW-DO-NOT-IMPORT';

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
        $definition = match ($key) {
            'student_researcher' => [
                'key' => $key,
                'label' => 'Student Researcher',
                'description' => 'Can prepare and manage student research applications.',
                'role' => UserRole::Applicant->value,
                'applicant_type' => ApplicantType::Student->value,
                'icon' => 'user',
                'identifier_field' => 'student_number',
                'required_fields' => ['first_name', 'last_name', 'email', 'student_number', 'phone_number', 'year_level'],
                'template_columns' => [
                    'First Name' => 'first_name',
                    'Middle Name' => 'middle_name',
                    'Last Name' => 'last_name',
                    'Suffix' => 'suffix',
                    'Email' => 'email',
                    'Student Number' => 'student_number',
                    'Phone Number' => 'phone_number',
                    'Year Level' => 'year_level',
                    'Institution' => 'institution',
                    'Department' => 'department',
                    'Program' => 'program',
                ],
                'example_values' => [
                    'first_name' => 'Juan',
                    'middle_name' => 'Dela',
                    'last_name' => 'Cruz',
                    'suffix' => 'Jr.',
                    'email' => 'juandelacruz@example.com',
                    'student_number' => '20260000',
                    'phone_number' => '09999999999',
                    'year_level' => 'Fourth Year',
                    'institution' => 'Institute of Computing and Digital Innovation',
                    'department' => 'Computer Studies',
                    'program' => 'Bachelor of Science in Computer Science',
                ],
            ],
            'faculty_researcher' => [
                'key' => $key,
                'label' => 'Faculty Researcher',
                'description' => 'Can prepare and manage faculty research applications.',
                'role' => UserRole::Applicant->value,
                'applicant_type' => ApplicantType::Faculty->value,
                'icon' => 'user-check',
                'identifier_field' => 'employee_id',
                'required_fields' => ['first_name', 'last_name', 'email', 'employee_id', 'phone_number'],
                'template_columns' => [
                    'First Name' => 'first_name',
                    'Middle Name' => 'middle_name',
                    'Last Name' => 'last_name',
                    'Suffix' => 'suffix',
                    'Email' => 'email',
                    'Employee ID' => 'employee_id',
                    'Phone Number' => 'phone_number',
                    'Institution' => 'institution',
                    'Department' => 'department',
                    'Program' => 'program',
                    'Position / Designation' => 'position_title',
                ],
                'example_values' => [
                    'first_name' => 'Marian',
                    'middle_name' => 'L.',
                    'last_name' => 'Santos',
                    'suffix' => '',
                    'email' => 'marian.santos@example.com',
                    'employee_id' => 'KLD-EMP-1001',
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
                'identifier_field' => 'employee_id',
                'required_fields' => ['first_name', 'last_name', 'email', 'employee_id', 'phone_number'],
                'template_columns' => [
                    'First Name' => 'first_name',
                    'Middle Name' => 'middle_name',
                    'Last Name' => 'last_name',
                    'Suffix' => 'suffix',
                    'Email' => 'email',
                    'Employee ID' => 'employee_id',
                    'Phone Number' => 'phone_number',
                    'Institution' => 'institution',
                    'Department' => 'department',
                    'Position / Designation' => 'position_title',
                    'Reviewer Enabled' => 'reviewer_enabled',
                    'Reviewer Capacity' => 'reviewer_capacity',
                ],
                'example_values' => [
                    'first_name' => 'Roberto',
                    'middle_name' => 'D.',
                    'last_name' => 'Garcia',
                    'suffix' => 'Jr.',
                    'email' => 'roberto.garcia@example.com',
                    'employee_id' => 'KLD-EMP-1002',
                    'phone_number' => '09191234567',
                    'institution' => 'Institute of Engineering',
                    'department' => 'Engineering Studies',
                    'position_title' => 'Research Adviser',
                    'reviewer_enabled' => 'Yes',
                    'reviewer_capacity' => '6',
                ],
            ],
            default => throw new AuthorizationException('Unknown account type.'),
        };

        $definition['template_headers'] = array_keys($definition['template_columns']);
        $definition['required_headers'] = collect($definition['template_columns'])
            ->filter(fn (string $field): bool => in_array($field, $definition['required_fields'], true))
            ->keys()
            ->values()
            ->all();
        $definition['optional_headers'] = array_values(array_diff(
            $definition['template_headers'],
            $definition['required_headers'],
        ));
        $definition['identifier_header'] = array_search(
            $definition['identifier_field'],
            $definition['template_columns'],
            true,
        );
        $definition['example_row'] = collect($definition['template_columns'])
            ->mapWithKeys(fn (string $field, string $header): array => [
                $header => (string) ($definition['example_values'][$field] ?? ''),
            ])
            ->all();

        return $definition;
    }
}
