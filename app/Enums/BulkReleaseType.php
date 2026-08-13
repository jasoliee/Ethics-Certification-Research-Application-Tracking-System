<?php

namespace App\Enums;

enum BulkReleaseType: string
{
    case Certificate = 'certificate';
    case Decision = 'decision';
    case Both = 'both';

    public function label(): string
    {
        return match ($this) {
            self::Certificate => 'Certificate',
            self::Decision => 'Decision',
            self::Both => 'Both Certificate and Decision',
        };
    }
}
