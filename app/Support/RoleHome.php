<?php

namespace App\Support;

use App\Enums\UserRole;

class RoleHome
{
    public static function routeNameFor(UserRole|string|null $role): string
    {
        $role = $role instanceof UserRole ? $role : UserRole::tryFrom((string) $role);

        return match ($role) {
            UserRole::Applicant => 'applicant.landing',
            UserRole::Adviser => 'adviser.landing',
            UserRole::Reviewer => 'reviewer.landing',
            UserRole::ResLead => 'res.landing',
            default => 'login',
        };
    }
}
