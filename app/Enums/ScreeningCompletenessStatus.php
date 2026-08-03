<?php

namespace App\Enums;

/**
 * Records the RES Lead's administrative completeness finding at classification time.
 */
enum ScreeningCompletenessStatus: string
{
    case Complete = 'complete';
    case Incomplete = 'incomplete';

    public function label(): string
    {
        return match ($this) {
            self::Complete => 'Complete',
            self::Incomplete => 'Incomplete',
        };
    }
}
