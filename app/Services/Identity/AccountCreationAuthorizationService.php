<?php

namespace App\Services\Identity;

use App\Enums\UserRole;
use App\Models\User;

class AccountCreationAuthorizationService
{
    public function canCreate(User $actor, UserRole|string $targetRole): bool
    {
        $targetRole = $targetRole instanceof UserRole ? $targetRole : UserRole::tryFrom($targetRole);

        return match ($actor->role) {
            UserRole::ResLead => in_array($targetRole, [UserRole::Adviser, UserRole::Reviewer], true),
            UserRole::Adviser => $targetRole === UserRole::Applicant,
            default => false,
        };
    }
}
