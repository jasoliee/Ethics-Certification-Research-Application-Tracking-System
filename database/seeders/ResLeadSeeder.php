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
        $attributes = [
            'username' => 'reulead',
            'name' => 'REU Lead',
            'first_name' => 'REU',
            'middle_name' => 'E.',
            'last_name' => 'Lead',
            'suffix' => null,
            'email' => 'reulead@ecrats.test',
            'institutional_identifier' => 'KLD-RES-001',
            'institution' => 'Institute of Computing and Digital Innovation',
            'program' => 'Bachelor of Science in Computer Science',
            'phone_number' => '09170000000',
            'position_title' => 'REU Lead',
            'password' => Hash::make('12345kld'),
            'password_changed_at' => now(),
            'password_setup_completed_at' => now(),
            'onboarding_completed_at' => now(),
            'setup_email_status' => 'not_required',
            'role' => UserRole::ResLead,
            'account_status' => 'active',
        ];

        $user = User::query()
            ->where('role', UserRole::ResLead->value)
            ->orderBy('id')
            ->first();

        if ($user) {
            $user->forceFill($attributes)->save();

            return;
        }

        User::create($attributes);
    }
}
