<?php

namespace App\Policies;

use App\Enums\ReviewerAssignmentStatus;
use App\Enums\ReviewerConflictStatus;
use App\Enums\ReviewSubmissionStatus;
use App\Enums\UserRole;
use App\Models\ReviewerAssignment;
use App\Models\User;

class ReviewerAssignmentPolicy
{
    public function view(User $user, ReviewerAssignment $reviewerAssignment): bool
    {
        return $this->owns($user, $reviewerAssignment);
    }

    public function declareConflict(User $user, ReviewerAssignment $reviewerAssignment): bool
    {
        return $this->owns($user, $reviewerAssignment)
            && $reviewerAssignment->conflict_status === ReviewerConflictStatus::Pending
            && in_array($reviewerAssignment->assignment_status, $this->activeStatuses(), true);
    }

    public function openWorkspace(User $user, ReviewerAssignment $reviewerAssignment): bool
    {
        return $this->owns($user, $reviewerAssignment)
            && $reviewerAssignment->conflict_status === ReviewerConflictStatus::Cleared;
    }

    public function work(User $user, ReviewerAssignment $reviewerAssignment): bool
    {
        return $this->owns($user, $reviewerAssignment)
            && $reviewerAssignment->conflict_status === ReviewerConflictStatus::Cleared
            && in_array($reviewerAssignment->assignment_status, $this->activeStatuses(), true)
            && ! $reviewerAssignment->reviewSubmission()
                ->where('status', ReviewSubmissionStatus::Submitted->value)
                ->exists();
    }

    private function owns(User $user, ReviewerAssignment $reviewerAssignment): bool
    {
        return $user->role === UserRole::Reviewer
            && $reviewerAssignment->reviewer_user_id === $user->id;
    }

    /** @return array<int, ReviewerAssignmentStatus> */
    private function activeStatuses(): array
    {
        return [
            ReviewerAssignmentStatus::Pending,
            ReviewerAssignmentStatus::InReview,
            ReviewerAssignmentStatus::RevisionReview,
        ];
    }
}
