<?php

namespace App\Enums;

/**
 * Defines the approved research types used by application forms and requirement rules.
 */
enum ResearchType: string
{
    case Thesis = 'thesis';
    case Capstone = 'capstone';

    /**
     * Return the human-readable value displayed in forms and application details.
     */
    public function label(): string
    {
        return match ($this) {
            self::Thesis => 'Thesis',
            self::Capstone => 'Capstone',
        };
    }
}
