<?php

namespace App\Enums;

enum ReviewFormStatus: string
{
    case Draft = 'draft';
    case Final = 'final';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Final => 'Complete',
        };
    }
}
