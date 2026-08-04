<?php

namespace App\Enums;

enum ReviewerConflictStatus: string
{
    case Pending = 'pending';
    case Cleared = 'cleared';
    case Declared = 'declared';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Declaration Required',
            self::Cleared => 'No Conflict Declared',
            self::Declared => 'Conflict Declared',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Pending => 'orange',
            self::Cleared => 'success',
            self::Declared => 'red',
        };
    }
}
