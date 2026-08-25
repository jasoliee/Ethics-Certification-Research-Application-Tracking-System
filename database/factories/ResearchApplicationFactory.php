<?php

namespace Database\Factories;

use App\Enums\ApplicationStage;
use App\Enums\ApplicationStatus;
use App\Enums\ResearchType;
use App\Enums\UserRole;
use App\Models\ResearchApplication;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<ResearchApplication> */
class ResearchApplicationFactory extends Factory
{
    public function configure(): static
    {
        return $this->afterCreating(function (ResearchApplication $application): void {
            $recipientName = Str::squish($application->applicant()->value('name') ?? 'Applicant Name');

            $application->certificateRecipients()->create([
                'recipient_name' => $recipientName,
                'normalized_name' => mb_strtolower($recipientName),
                'sort_order' => 1,
            ]);
        });
    }

    public function definition(): array
    {
        return [
            'application_code' => 'ECRATS-'.now()->format('Y').'-'.fake()->unique()->numerify('####'),
            'applicant_user_id' => User::factory()->state(['role' => UserRole::Applicant]),
            'adviser_user_id' => null,
            'applicant_type' => fake()->randomElement(['student', 'faculty']),
            'research_title' => fake()->sentence(7),
            'research_type' => ResearchType::Thesis,
            'research_category' => 'Social and Behavioral Research',
            'institution' => 'Institute of Computing and Digital Innovation',
            'program' => 'Bachelor of Science in Computer Science',
            'abstract' => fake()->paragraphs(2, true),
            'target_participants' => 'KLD students who meet the approved inclusion criteria.',
            'expected_duration' => 'August 2026 to May 2027',
            'expected_start_date' => now()->addMonth()->startOfDay(),
            'expected_end_date' => now()->addMonths(10)->startOfDay(),
            'application_type' => 'new_application',
            'application_status' => ApplicationStatus::Draft,
            'current_stage' => ApplicationStage::ApplicationInformation,
            'review_type' => null,
            'current_revision_cycle' => 1,
            'submitted_at' => null,
            'status_updated_at' => now(),
        ];
    }

    public function submittedToAdviser(?User $adviser = null): static
    {
        return $this->state(fn (): array => [
            'adviser_user_id' => $adviser?->id ?? User::factory()->state(['role' => UserRole::Adviser]),
            'application_status' => ApplicationStatus::SubmittedToAdviser,
            'current_stage' => ApplicationStage::AdviserReview,
            'submitted_at' => now()->subDay(),
        ]);
    }
}
