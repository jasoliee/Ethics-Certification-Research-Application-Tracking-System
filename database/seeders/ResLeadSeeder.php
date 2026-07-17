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
                'email' => 'reslead@ecrats.test',
                'password' => Hash::make('12345kld'),
                'role' => UserRole::ResLead,
                'account_status' => 'active',
            ],
        );
    }
}
