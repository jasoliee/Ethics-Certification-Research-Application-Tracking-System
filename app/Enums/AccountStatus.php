<?php

namespace App\Enums;

enum AccountStatus: string
{
    case PendingSetup = 'pending_setup';
    case Active = 'active';
    case Inactive = 'inactive';

    public function label(): string
    {
        return match ($this) {
            self::PendingSetup => 'Pending Setup',
            self::Active => 'Active',
            self::Inactive => 'Inactive',
        };
    }
}
