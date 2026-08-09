<?php

namespace App\Policies;

use App\Enums\ReviewFormArtifactStatus;
use App\Enums\UserRole;
use App\Models\ReviewFormArtifact;
use App\Models\User;

class ReviewFormArtifactPolicy
{
    public function view(User $user, ReviewFormArtifact $artifact): bool
    {
        if ($artifact->status !== ReviewFormArtifactStatus::Ready) {
            return false;
        }

        if ($user->role === UserRole::ResLead) {
            return true;
        }

        $assignment = $artifact->formSubmission->assignment;

        return $user->role === UserRole::Reviewer
            && $assignment->reviewer_user_id === $user->id
            && $assignment->isCurrent();
    }
}
