<?php

namespace App\Enums;

/**
 * Defines the RES classification that controls reviewer count and the next workflow state.
 */
enum ReviewType: string
{
    case Expedited = 'expedited';
    case FullBoard = 'full_board';
    case Exempted = 'exempted';

    public function label(): string
    {
        return match ($this) {
            self::Expedited => 'Expedited Review',
            self::FullBoard => 'Full Board Review',
            self::Exempted => 'Exempted',
        };
    }

    public function reviewerCount(): int
    {
        return match ($this) {
            self::Expedited => 1,
            self::FullBoard => 3,
            self::Exempted => 0,
        };
    }

    public function reviewerClassification(): ?string
    {
        return match ($this) {
            self::Expedited => 'Expedited',
            self::FullBoard => 'Full Board',
            self::Exempted => null,
        };
    }

    public function requiresReviewers(): bool
    {
        return $this->reviewerCount() > 0;
    }
}
