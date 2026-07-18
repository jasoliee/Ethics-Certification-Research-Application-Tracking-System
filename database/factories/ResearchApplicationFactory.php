<?php

namespace Database\Factories;

use App\Enums\ApplicationStatus;
use App\Enums\UserRole;
use App\Models\ResearchApplication;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ResearchApplication> */
class ResearchApplicationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'application_code' => 'ECRATS-'.now()->format('Y').'-'.fake()->unique()->numerify('####'),
            'applicant_user_id' => User::factory()->state(['role' => UserRole::Applicant]),
            'adviser_user_id' => null,
            'applicant_type' => fake()->randomElement(['student', 'faculty']),
            'research_title' => fake()->sentence(7),
            'application_type' => 'new_application',
            'application_status' => ApplicationStatus::Draft,
            'review_type' => null,
            'submitted_at' => null,
            'status_updated_at' => now(),
        ];
    }

    public function submittedToAdviser(?User $adviser = null): static
    {
        return $this->state(fn (): array => [
            'adviser_user_id' => $adviser?->id ?? User::factory()->state(['role' => UserRole::Adviser]),
            'application_status' => ApplicationStatus::SubmittedToAdviser,
            'submitted_at' => now()->subDay(),
        ]);
    }
}
