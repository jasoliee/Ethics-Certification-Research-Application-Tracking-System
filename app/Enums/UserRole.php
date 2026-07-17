<?php

namespace App\Enums;

enum UserRole: string
{
    case Applicant = 'student_faculty_researcher';
    case Adviser = 'adviser';
    case Reviewer = 'reviewer';
    case ResLead = 'res_lead';

    public function label(): string
    {
        return match ($this) {
            self::Applicant => 'Student/Faculty Researcher',
            self::Adviser => 'Adviser',
            self::Reviewer => 'Reviewer',
            self::ResLead => 'RES Lead',
        };
    }

    public function landingTitle(): string
    {
        return $this->label().' Landing Page';
    }
}
