<?php

namespace App\Enums;

enum ProfileOptionField: string
{
    case YearLevel = 'year_level';
    case Institute = 'institution';
    case Program = 'program';
    case ReviewerClassification = 'reviewer_classification';

    /** @return array<int, self> */
    public static function managedCases(): array
    {
        return [self::YearLevel, self::Institute, self::Program];
    }

    public function label(): string
    {
        return match ($this) {
            self::YearLevel => 'Year Level',
            self::Institute => 'Institute',
            self::Program => 'Program',
            self::ReviewerClassification => 'Reviewer Classification',
        };
    }
}
