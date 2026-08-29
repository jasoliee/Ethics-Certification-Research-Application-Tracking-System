<?php

namespace App\Enums;

enum AcademicTermStatus: string
{
    case Active = 'active';
    case Paused = 'paused';
    case Ended = 'ended';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Paused => 'Paused',
            self::Ended => 'Ended',
        };
    }
}
