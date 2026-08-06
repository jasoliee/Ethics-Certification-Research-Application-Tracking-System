<?php

namespace App\Enums;

/**
 * Identifies the current high-level workflow section without duplicating application statuses.
 */
enum ApplicationStage: string
{
    case ApplicationInformation = 'application_information';
    case DocumentSubmission = 'document_submission';
    case AdviserReview = 'adviser_review';
    case ResScreening = 'res_screening';
    case EthicsReview = 'ethics_review';
    case Revision = 'revision';
    case DecisionRelease = 'decision_release';
    case Completed = 'completed';

    /**
     * Return the applicant-facing stage label used by dashboards and detail pages.
     */
    public function label(): string
    {
        return match ($this) {
            self::ApplicationInformation => 'Application Information',
            self::DocumentSubmission => 'Document Submission',
            self::AdviserReview => 'Adviser Review',
            self::ResScreening => 'RES Screening',
            self::EthicsReview => 'Ethics Review',
            self::Revision => 'Revision',
            self::DecisionRelease => 'Decision Release',
            self::Completed => 'Completed',
        };
    }
}
