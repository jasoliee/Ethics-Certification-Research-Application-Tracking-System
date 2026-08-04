<?php

namespace App\Enums;

enum ReviewFormType: string
{
    case Protocol = 'protocol';
    case InformedConsent = 'informed_consent';

    public function code(): string
    {
        return match ($this) {
            self::Protocol => 'KLD-RES-04-001',
            self::InformedConsent => 'KLD-RES-04-002',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Protocol => 'Protocol Review Worksheet',
            self::InformedConsent => 'Informed Consent Checklist',
        };
    }
}
