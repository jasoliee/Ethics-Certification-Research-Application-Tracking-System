<?php

namespace App\Enums;

enum EndorsementStatus: string
{
    case Returned = 'returned';
    case Endorsed = 'endorsed';

    public function label(): string
    {
        return match ($this) {
            self::Returned => 'Returned',
            self::Endorsed => 'Endorsed',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Returned => 'red',
            self::Endorsed => 'success',
        };
    }
}
