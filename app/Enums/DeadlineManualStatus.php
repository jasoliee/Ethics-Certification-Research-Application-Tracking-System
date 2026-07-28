<?php

namespace App\Enums;

/**
 * Overrides automatic deadline-date evaluation when the RES Lead intervenes.
 */
enum DeadlineManualStatus: string
{
    case Open = 'open';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Manually Open',
            self::Closed => 'Manually Closed',
        };
    }
}
