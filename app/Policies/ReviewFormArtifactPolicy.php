<?php

namespace App\Policies;

use App\Enums\ReviewFormArtifactStatus;
use App\Enums\ReviewSubmissionStatus;
use App\Enums\UserRole;
use App\Models\ReviewFormArtifact;
use App\Models\User;

class ReviewFormArtifactPolicy
{
    public function view(User $user, ReviewFormArtifact $artifact): bool
    {
        if (! in_array($artifact->status, [
            ReviewFormArtifactStatus::Ready,
            ReviewFormArtifactStatus::Superseded,
        ], true)) {
            return false;
        }

        $assignment = $artifact->formSubmission->assignment;

        if ($assignment->reviewSubmission?->status !== ReviewSubmissionStatus::Submitted) {
            return false;
        }

        if ($user->role === UserRole::ResLead) {
            return true;
        }

        return $user->hasReviewerAccess()
            && $assignment->reviewer_user_id === $user->id
            && $assignment->isCurrent();
    }
}
