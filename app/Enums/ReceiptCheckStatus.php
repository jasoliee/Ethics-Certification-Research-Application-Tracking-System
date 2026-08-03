<?php

namespace App\Enums;

/**
 * Captures the administrative payment-receipt check without storing receipt contents.
 */
enum ReceiptCheckStatus: string
{
    case Accepted = 'accepted';
    case Pending = 'pending';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Accepted => 'Checked / Accepted',
            self::Pending => 'Pending Check',
            self::Rejected => 'Not Accepted',
        };
    }
}
