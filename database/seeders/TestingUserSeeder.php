<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TestingUserSeeder extends Seeder
{
    /**
     * @var array<int, array{name: string, username: string, email: string, password: string, role: UserRole}>
     */
    private array $users = [
        [
            'name' => 'Applicant Test',
            'username' => 'applicanttest',
            'email' => 'applicanttest@ecrats.test',
            'password' => '12345678',
            'role' => UserRole::Applicant,
        ],
        [
            'name' => 'Adviser Test',
            'username' => 'advisertest',
            'email' => 'advisertest@ecrats.test',
            'password' => '12345678',
            'role' => UserRole::Adviser,
        ],
        [
            'name' => 'Reviewer Test',
            'username' => 'reviewertest',
            'email' => 'reviewertest@ecrats.test',
            'password' => '12345678',
            'role' => UserRole::Reviewer,
        ],
    ];

    public function run(): void
    {
        foreach ($this->users as $user) {
            User::updateOrCreate(
                ['username' => $user['username']],
                [
                    'name' => $user['name'],
                    'email' => $user['email'],
                    'password' => Hash::make($user['password']),
                    'role' => $user['role'],
                    'account_status' => 'active',
                ],
            );
        }
    }
}
