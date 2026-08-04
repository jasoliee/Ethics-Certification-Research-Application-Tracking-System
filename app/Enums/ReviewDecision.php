<?php

namespace App\Enums;

enum ReviewDecision: string
{
    case Approved = 'approved';
    case MinorRevision = 'minor_revision';
    case MajorRevision = 'major_revision';
    case Disapproved = 'disapproved';

    public function label(): string
    {
        return match ($this) {
            self::Approved => 'Approved',
            self::MinorRevision => 'Minor Revision',
            self::MajorRevision => 'Major Revision',
            self::Disapproved => 'Disapproved',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Approved => 'success',
            self::MinorRevision => 'amber',
            self::MajorRevision => 'purple',
            self::Disapproved => 'red',
        };
    }
}
