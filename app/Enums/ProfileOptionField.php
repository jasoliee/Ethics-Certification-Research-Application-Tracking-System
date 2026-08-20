<?php

namespace App\Enums;

enum ProfileOptionField: string
{
    case YearLevel = 'year_level';
    case Institution = 'institution';
    case Department = 'department';
    case Program = 'program';
    case ReviewerClassification = 'reviewer_classification';

    /** @return array<int, self> */
    public static function managedCases(): array
    {
        return [self::YearLevel, self::Institution, self::Department, self::Program];
    }

    public function label(): string
    {
        return match ($this) {
            self::YearLevel => 'Year Level',
            self::Institution => 'Institution',
            self::Department => 'Department',
            self::Program => 'Program',
            self::ReviewerClassification => 'Reviewer Classification',
        };
    }
}
