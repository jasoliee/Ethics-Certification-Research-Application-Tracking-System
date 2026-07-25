<?php

namespace App\Enums;

enum ProfileOptionField: string
{
    case YearLevel = 'year_level';
    case Institution = 'institution';
    case Department = 'department';
    case Program = 'program';
    case ReviewerClassification = 'reviewer_classification';

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
