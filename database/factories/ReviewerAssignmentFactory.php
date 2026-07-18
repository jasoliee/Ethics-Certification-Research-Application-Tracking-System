<?php

namespace Database\Factories;

use App\Enums\ApplicationStatus;
use App\Enums\ReviewerAssignmentStatus;
use App\Enums\UserRole;
use App\Models\ResearchApplication;
use App\Models\ReviewerAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ReviewerAssignment> */
class ReviewerAssignmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'research_application_id' => ResearchApplication::factory()->state([
                'application_status' => ApplicationStatus::UnderExpeditedReview,
                'submitted_at' => now()->subDays(3),
            ]),
            'reviewer_user_id' => User::factory()->state(['role' => UserRole::Reviewer]),
            'review_type' => 'initial_review',
            'assignment_status' => ReviewerAssignmentStatus::Pending,
            'assigned_at' => now()->subDay(),
            'review_deadline_at' => now()->addDays(5),
            'submitted_at' => null,
        ];
    }
}
