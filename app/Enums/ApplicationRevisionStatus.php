<?php

namespace App\Enums;

enum ApplicationRevisionStatus: string
{
    case PendingUploads = 'pending_uploads';
    case UnderReview = 'under_review';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::PendingUploads => 'Awaiting Revised Documents',
            self::UnderReview => 'Under Re-review',
            self::Completed => 'Completed',
        };
    }
}
