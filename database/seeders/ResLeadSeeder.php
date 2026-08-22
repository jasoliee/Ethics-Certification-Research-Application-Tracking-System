<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class ResLeadSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['username' => 'reslead'],
            [
                'name' => 'RES Lead',
                'first_name' => 'RES',
                'middle_name' => 'E.',
                'last_name' => 'Lead',
                'suffix' => null,
                'email' => 'reslead@ecrats.test',
                'institutional_identifier' => 'KLD-RES-001',
                'institution' => 'Institute of Computing and Digital Innovation',
                'department' => 'Computer Studies',
                'program' => 'Bachelor of Science in Computer Science',
                'phone_number' => '09170000000',
                'position_title' => 'RES Lead',
                'password' => Hash::make('12345kld'),
                'password_changed_at' => now(),
                'password_setup_completed_at' => now(),
                'onboarding_completed_at' => now(),
                'setup_email_status' => 'not_required',
                'role' => UserRole::ResLead,
                'account_status' => 'active',
            ],
        );
    }
}
