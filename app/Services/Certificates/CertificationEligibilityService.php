<?php

namespace App\Services\Certificates;

use App\Enums\ApplicationStatus;
use App\Enums\CertificateStatus;
use App\Enums\CertificateVersionStatus;
use App\Enums\CertificationState;
use App\Enums\ReviewDecision;
use App\Models\ResearchApplication;

class CertificationEligibilityService
{
    public function isEligible(ResearchApplication $application): bool
    {
        if ($application->application_status === ApplicationStatus::Exempted) {
            return true;
        }

        if (in_array($application->application_status, [
            ApplicationStatus::ResultReleasedAccepted,
            ApplicationStatus::CertificateReleased,
        ], true)) {
            return $application->decisionReleases()
                ->where('decision', ReviewDecision::Approved->value)
                ->exists();
        }

        return false;
    }

    public function state(ResearchApplication $application): CertificationState
    {
        $certificate = $application->relationLoaded('certificate')
            ? $application->certificate
            : $application->certificate()->with('currentVersion')->first();

        if ($certificate?->status === CertificateStatus::GenerationFailed) {
            return CertificationState::GenerationFailed;
        }

        if ($certificate?->status === CertificateStatus::PendingRelease) {
            return CertificationState::PendingResRelease;
        }

        if ($certificate
            && in_array($certificate->status, [CertificateStatus::Released, CertificateStatus::Claimed], true)
            && $certificate->currentVersion?->status === CertificateVersionStatus::Ready) {
            if ($certificate->status === CertificateStatus::Claimed
                && $certificate->claimed_certificate_version_id === $certificate->current_certificate_version_id) {
                return CertificationState::Claimed;
            }

            $surveyComplete = $application->relationLoaded('surveyResponse')
                ? $application->surveyResponse !== null
                : $application->surveyResponse()->exists();

            return $surveyComplete
                ? CertificationState::Claimable
                : CertificationState::SurveyRequired;
        }

        if ($this->isEligible($application)) {
            return CertificationState::Eligible;
        }

        if (in_array($application->application_status, [
            ApplicationStatus::UnderExpeditedReview,
            ApplicationStatus::UnderFullBoardReview,
            ApplicationStatus::ReviewSubmittedPendingRelease,
            ApplicationStatus::RevisionWindowOpen,
            ApplicationStatus::RevisionSubmitted,
            ApplicationStatus::UnderReReview,
        ], true)) {
            return CertificationState::PendingFinalApproval;
        }

        return CertificationState::NotEligible;
    }
}
