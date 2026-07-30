<?php

namespace App\Enums;

/**
 * Provides source-grounded return categories while remarks carry the exact correction instructions.
 */
enum AdviserReturnReason: string
{
    case RequirementCorrection = 'requirement_correction';
    case PaymentProofCorrection = 'payment_proof_correction';
    case ResearchInformationClarification = 'research_information_clarification';
    case OtherRequiredCorrection = 'other_required_correction';

    public function label(): string
    {
        return match ($this) {
            self::RequirementCorrection => 'Submitted requirement needs correction',
            self::PaymentProofCorrection => 'Payment proof needs correction',
            self::ResearchInformationClarification => 'Research information needs clarification',
            self::OtherRequiredCorrection => 'Other required correction',
        };
    }
}
