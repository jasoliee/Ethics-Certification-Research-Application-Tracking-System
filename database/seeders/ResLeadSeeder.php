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
                'middle_name' => null,
                'last_name' => 'Lead',
                'suffix' => null,
                'email' => 'reslead@ecrats.test',
                'institutional_identifier' => 'KLD-RES-001',
                'institution' => 'Kolehiyo ng Lungsod ng Dasmarinas',
                'department' => 'Research Ethics Section',
                'position_title' => 'RES Lead',
                'password' => Hash::make('12345kld'),
                'password_changed_at' => now(),
                'role' => UserRole::ResLead,
                'account_status' => 'active',
            ],
        );
    }
}
