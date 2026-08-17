<?php

namespace App\Enums;

enum ReviewConsensusStatus: string
{
    case AwaitingSubmissions = 'awaiting_submissions';
    case Consensus = 'consensus';
    case Conflicted = 'conflicted';

    public function label(): string
    {
        return match ($this) {
            self::AwaitingSubmissions => 'Awaiting Reviewer Submissions',
            self::Consensus => 'Pending Decision Release',
            self::Conflicted => 'Conflicted Reviewer Decisions',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::AwaitingSubmissions => 'orange',
            self::Consensus => 'green',
            self::Conflicted => 'red',
        };
    }
}
