<?php

namespace App\Enums;

enum ReviewCommentCategory: string
{
    case General = 'general';
    case Clarification = 'clarification';
    case RequiredRevision = 'required_revision';

    public function label(): string
    {
        return match ($this) {
            self::General => 'General Comment',
            self::Clarification => 'Clarification',
            self::RequiredRevision => 'Required Revision',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::General => 'neutral',
            self::Clarification => 'green',
            self::RequiredRevision => 'orange',
        };
    }
}
