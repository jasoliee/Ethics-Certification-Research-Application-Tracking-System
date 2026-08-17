<?php

namespace App\Policies;

use App\Enums\ReviewerAssignmentStatus;
use App\Models\ApplicationDecisionRelease;
use App\Models\ReviewerAssignment;
use App\Models\User;

class ReviewerAssignmentPolicy
{
    public function view(User $user, ReviewerAssignment $reviewerAssignment): bool
    {
        return $this->owns($user, $reviewerAssignment);
    }

    public function openWorkspace(User $user, ReviewerAssignment $reviewerAssignment): bool
    {
        return $this->owns($user, $reviewerAssignment) && $reviewerAssignment->isCurrent();
    }

    public function work(User $user, ReviewerAssignment $reviewerAssignment): bool
    {
        return $this->owns($user, $reviewerAssignment)
            && $reviewerAssignment->isCurrent()
            && in_array($reviewerAssignment->assignment_status, $this->activeStatuses(), true)
            && ! ApplicationDecisionRelease::query()
                ->where('research_application_id', $reviewerAssignment->research_application_id)
                ->where('review_cycle', $reviewerAssignment->review_cycle)
                ->exists();
    }

    private function owns(User $user, ReviewerAssignment $reviewerAssignment): bool
    {
        return $user->hasReviewerAccess()
            && $reviewerAssignment->reviewer_user_id === $user->id
            && $reviewerAssignment->isCurrent();
    }

    /** @return array<int, ReviewerAssignmentStatus> */
    private function activeStatuses(): array
    {
        return [
            ReviewerAssignmentStatus::Pending,
            ReviewerAssignmentStatus::InReview,
            ReviewerAssignmentStatus::RevisionReview,
            ReviewerAssignmentStatus::DecisionSubmitted,
        ];
    }
}
