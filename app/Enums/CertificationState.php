<?php

namespace App\Enums;

enum CertificationState: string
{
    case NotEligible = 'not_eligible';
    case PendingFinalApproval = 'pending_final_approval';
    case Eligible = 'eligible';
    case PendingResRelease = 'pending_res_release';
    case GenerationFailed = 'generation_failed';
    case SurveyRequired = 'survey_required';
    case Claimable = 'claimable';
    case Claimed = 'claimed';

    public function label(): string
    {
        return match ($this) {
            self::NotEligible => 'Not Eligible',
            self::PendingFinalApproval => 'Pending Decision Release',
            self::Eligible => 'Eligible for Certification',
            self::PendingResRelease => 'Pending Certificate Release',
            self::GenerationFailed => 'Generation Failed',
            self::SurveyRequired => 'Survey Required',
            self::Claimable => 'Ready to Claim',
            self::Claimed => 'Claimed',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::NotEligible => 'neutral',
            self::PendingFinalApproval, self::PendingResRelease => 'orange',
            self::Eligible, self::SurveyRequired => 'blue',
            self::GenerationFailed => 'red',
            self::Claimable, self::Claimed => 'success',
        };
    }
}
