<?php

namespace App\Enums;

enum ReviewCommentScope: string
{
    case Overall = 'overall';
    case Document = 'document';
    case Page = 'page';

    public function label(): string
    {
        return match ($this) {
            self::Overall => 'Overall',
            self::Document => 'Document',
            self::Page => 'Page',
        };
    }
}
