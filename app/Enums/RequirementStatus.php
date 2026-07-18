<?php

namespace App\Enums;

enum RequirementStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Completed => 'Completed',
            self::Rejected => 'Rejected',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Pending => 'orange',
            self::Completed => 'success',
            self::Rejected => 'red',
        };
    }
}
