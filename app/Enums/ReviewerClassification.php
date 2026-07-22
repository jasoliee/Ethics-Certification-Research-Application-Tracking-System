<?php

namespace App\Enums;

enum ReviewerClassification: string
{
    case Expedited = 'expedited';
    case FullBoard = 'full_board';

    public function label(): string
    {
        return match ($this) {
            self::Expedited => 'Expedited Review',
            self::FullBoard => 'Full Board Review',
        };
    }
}
