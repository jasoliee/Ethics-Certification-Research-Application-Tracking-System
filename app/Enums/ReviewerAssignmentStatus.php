<?php

namespace App\Enums;

enum ReviewerAssignmentStatus: string
{
    case Pending = 'pending';
    case InReview = 'in_review';
    case RevisionReview = 'revision_review';
    case DecisionSubmitted = 'decision_submitted';
    case Superseded = 'superseded';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending Review',
            self::InReview => 'In Review',
            self::RevisionReview => 'Revision Review',
            self::DecisionSubmitted => 'Decision Submitted',
            self::Superseded => 'Reassigned',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Pending => 'blue',
            self::InReview => 'orange',
            self::RevisionReview => 'violet',
            self::DecisionSubmitted => 'success',
            self::Superseded => 'neutral',
        };
    }

    /** @return array<int, string> */
    public static function activeValues(): array
    {
        return [self::Pending->value, self::InReview->value, self::RevisionReview->value];
    }
}
