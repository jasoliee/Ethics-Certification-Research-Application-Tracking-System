<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\ReviewerAssignment;
use App\Models\User;

class ReviewerAssignmentPolicy
{
    public function view(User $user, ReviewerAssignment $reviewerAssignment): bool
    {
        return $user->role === UserRole::Reviewer
            && $reviewerAssignment->reviewer_user_id === $user->id;
    }
}
