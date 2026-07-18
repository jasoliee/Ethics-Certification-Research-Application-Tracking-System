<?php

namespace App\Enums;

enum ApplicantType: string
{
    case Student = 'student';
    case Faculty = 'faculty';

    public function label(): string
    {
        return match ($this) {
            self::Student => 'Student Researcher',
            self::Faculty => 'Faculty Researcher',
        };
    }
}
