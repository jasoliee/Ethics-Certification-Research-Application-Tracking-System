<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Seed stable application requirements in every environment without guessing an administrative deadline.
        $this->call([
            ResLeadSeeder::class,
            ApplicationConfigurationSeeder::class,
        ]);

        if (app()->environment(['local', 'testing'])) {
            $this->call(TestingUserSeeder::class);
        }
    }
}
