<?php

namespace App\Enums;

enum ReviewerClassification: string
{
    case Expedited = 'expedited';
    case FullBoard = 'full_board';
    case Exempted = 'exempted';

    public function label(): string
    {
        return match ($this) {
            self::Expedited => 'Expedited',
            self::FullBoard => 'Full Board',
            self::Exempted => 'Exempted',
        };
    }
}
