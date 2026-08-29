<?php

namespace App\Services\Privacy;

use App\Enums\CertificateStatus;
use App\Enums\ReviewDecision;
use App\Models\ResearchApplication;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Centralizes the double-blind boundary for REU-facing applicant identity.
 *
 * An application may identify its Applicant only after every configured
 * recipient has an issued certificate in a released or claimed state.
 */
class ApplicationIdentityVisibilityService
{
    public function visibleApplications(Builder $applications): Builder
    {
        return $applications
            ->whereHas('decisionReleases', fn (Builder $decisions) => $decisions
                ->where('decision', ReviewDecision::Approved->value))
            ->whereHas('certificates')
            ->whereDoesntHave('certificates', fn (Builder $certificates) => $certificates
                ->whereNotIn('status', [
                    CertificateStatus::Released->value,
                    CertificateStatus::Claimed->value,
                ]))
            ->whereRaw(
                '(SELECT COUNT(*) FROM certificates WHERE certificates.research_application_id = research_applications.id) '
                .'= (SELECT COUNT(*) FROM application_certificate_recipients WHERE application_certificate_recipients.research_application_id = research_applications.id)',
            );
    }

    public function applicationIsVisible(ResearchApplication $application): bool
    {
        return $this->visibleApplications(
            ResearchApplication::query()->whereKey($application->id),
        )->exists();
    }

    public function applicantIsVisible(User $applicant): bool
    {
        return $this->visibleApplications(
            ResearchApplication::query()->where('applicant_user_id', $applicant->id),
        )->exists();
    }

    public function forApplicant(User $applicant): Builder
    {
        return $this->visibleApplications(
            ResearchApplication::query()->where('applicant_user_id', $applicant->id),
        );
    }
}
