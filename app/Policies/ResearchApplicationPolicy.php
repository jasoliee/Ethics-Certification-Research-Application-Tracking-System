<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\ResearchApplication;
use App\Models\User;

class ResearchApplicationPolicy
{
    public function view(User $user, ResearchApplication $researchApplication): bool
    {
        return match ($user->role) {
            UserRole::Applicant => $researchApplication->applicant_user_id === $user->id,
            UserRole::Adviser => $researchApplication->adviser_user_id === $user->id,
            UserRole::Reviewer => $researchApplication->reviewerAssignments()
                ->where('reviewer_user_id', $user->id)
                ->exists(),
            UserRole::ResLead => true,
        };
    }
}
