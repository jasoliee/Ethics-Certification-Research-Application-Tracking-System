<?php

namespace App\Enums;

enum CertificateStatus: string
{
    case PendingRelease = 'pending_release';
    case GenerationFailed = 'generation_failed';
    case Released = 'released';
    case Claimed = 'claimed';

    public function label(): string
    {
        return match ($this) {
            self::PendingRelease => 'Pending RES Release',
            self::GenerationFailed => 'Generation Failed',
            self::Released => 'Released',
            self::Claimed => 'Claimed',
        };
    }
}
