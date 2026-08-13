<?php

namespace App\Enums;

enum ReviewFormStatus: string
{
    case Draft = 'draft';
    case Completed = 'completed';
    case Final = 'final';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Completed => 'Completed',
            self::Final => 'Final',
        };
    }
}
