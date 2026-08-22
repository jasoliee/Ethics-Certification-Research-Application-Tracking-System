<?php

namespace Database\Seeders;

use App\Enums\ApplicantType;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TestingUserSeeder extends Seeder
{
    /**
     * @var array<int, array{name: string, first_name: string, last_name: string, institutional_identifier: string, username: string, email: string, password: string, role: UserRole, applicant_type?: ApplicantType, reviewer_enabled?: bool}>
     */
    private array $users = [
        [
            'name' => 'Applicant Test',
            'first_name' => 'Applicant',
            'last_name' => 'Test',
            'institutional_identifier' => 'KLD-STU-001',
            'username' => 'applicanttest',
            'email' => 'applicanttest@ecrats.test',
            'password' => '12345678',
            'role' => UserRole::Applicant,
            'applicant_type' => ApplicantType::Student,
        ],
        [
            'name' => 'Adviser Test',
            'first_name' => 'Adviser',
            'last_name' => 'Test',
            'institutional_identifier' => 'KLD-EMP-001',
            'username' => 'advisertest',
            'email' => 'advisertest@ecrats.test',
            'password' => '12345678',
            'role' => UserRole::Adviser,
        ],
        [
            'name' => 'Reviewer Test',
            'first_name' => 'Reviewer',
            'last_name' => 'Test',
            'institutional_identifier' => 'KLD-EMP-002',
            'username' => 'reviewertest',
            'email' => 'reviewertest@ecrats.test',
            'password' => '12345678',
            'role' => UserRole::Adviser,
            'reviewer_enabled' => true,
        ],
    ];

    public function run(): void
    {
        foreach ($this->users as $user) {
            $reviewerEnabled = $user['reviewer_enabled'] ?? false;

            User::updateOrCreate(
                ['username' => $user['username']],
                [
                    'name' => $user['name'],
                    'first_name' => $user['first_name'],
                    'middle_name' => 'R.',
                    'last_name' => $user['last_name'],
                    'suffix' => null,
                    'email' => $user['email'],
                    'institutional_identifier' => $user['institutional_identifier'],
                    'institution' => 'Institute of Computing and Digital Innovation',
                    'phone_number' => '09170000001',
                    // The compatibility review account is now an Adviser with supplementary access.
                    'department' => 'Computer Studies',
                    'program' => 'Bachelor of Science in Computer Science',
                    'year_level' => $user['role'] === UserRole::Applicant ? 'First Year' : null,
                    'position_title' => $user['role'] === UserRole::Adviser
                        ? ($reviewerEnabled ? 'Ethics Reviewer' : 'Research Adviser')
                        : 'Student Researcher',
                    'expected_endorsement_count' => $user['role'] === UserRole::Adviser ? 6 : null,
                    'reviewer_classification' => $reviewerEnabled ? 'Expedited' : null,
                    'reviewer_classifications' => $reviewerEnabled ? ['Expedited'] : null,
                    'reviewer_capacity' => $reviewerEnabled ? 6 : null,
                    'reviewer_enabled' => $reviewerEnabled,
                    'password' => Hash::make($user['password']),
                    'password_changed_at' => now(),
                    'password_setup_completed_at' => now(),
                    'onboarding_completed_at' => now(),
                    'setup_email_status' => 'not_required',
                    'role' => $user['role'],
                    'applicant_type' => $user['applicant_type'] ?? null,
                    'account_status' => 'active',
                ],
            );
        }
    }
}
