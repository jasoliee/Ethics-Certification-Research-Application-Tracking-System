<?php

namespace App\Services\Applications;

use App\Enums\CertificateStatus;
use App\Enums\ReviewDecision;
use App\Models\ResearchApplication;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

/**
 * Enforces the approved per-applicant limit using formal submission history, not drafts.
 */
class ApplicationSubmissionLimit
{
    public const MAX_APPLICATIONS = 3;

    public const REACHED_MESSAGE = 'Application Submission Limit Reached. A maximum of 3 formally submitted applications is allowed.';

    public const CERTIFIED_MESSAGE = 'A new application cannot be created or submitted because this account already has an approved application with an issued certificate.';

    public function submittedCount(User $applicant): int
    {
        return ResearchApplication::query()
            ->where('applicant_user_id', $applicant->id)
            ->whereNotNull('submitted_at')
            ->count();
    }

    /**
     * @return array{submitted_count: int, maximum: int, reached: bool, certified_blocked: bool, block_message: string|null}
     */
    public function status(User $applicant): array
    {
        $submittedCount = $this->submittedCount($applicant);
        $certifiedBlocked = $this->hasCertifiedApplication($applicant);

        return [
            'submitted_count' => $submittedCount,
            'maximum' => self::MAX_APPLICATIONS,
            'reached' => $certifiedBlocked || $submittedCount >= self::MAX_APPLICATIONS,
            'certified_blocked' => $certifiedBlocked,
            'block_message' => $certifiedBlocked
                ? self::CERTIFIED_MESSAGE
                : ($submittedCount >= self::MAX_APPLICATIONS ? self::REACHED_MESSAGE : null),
        ];
    }

    public function canCreate(User $applicant): bool
    {
        return ! $this->hasCertifiedApplication($applicant)
            && $this->submittedCount($applicant) < self::MAX_APPLICATIONS;
    }

    /**
     * Exclude the current record so a returned application keeps its original slot.
     */
    public function canSubmit(User $applicant, ResearchApplication $application): bool
    {
        return ! $this->hasCertifiedApplication($applicant, $application)
            && ResearchApplication::query()
            ->where('applicant_user_id', $applicant->id)
            ->whereKeyNot($application->id)
            ->whereNotNull('submitted_at')
            ->count() < self::MAX_APPLICATIONS;
    }

    public function assertCanCreate(User $applicant): void
    {
        if ($this->hasCertifiedApplication($applicant)) {
            $this->throwCertifiedBlocked();
        }

        if (! $this->canCreate($applicant)) {
            $this->throwLimitReached();
        }
    }

    public function assertCanSubmit(User $applicant, ResearchApplication $application): void
    {
        if ($this->hasCertifiedApplication($applicant, $application)) {
            $this->throwCertifiedBlocked();
        }

        if (! $this->canSubmit($applicant, $application)) {
            $this->throwLimitReached();
        }
    }

    public function hasCertifiedApplication(User $applicant, ?ResearchApplication $except = null): bool
    {
        return ResearchApplication::query()
            ->where('applicant_user_id', $applicant->id)
            ->when($except, fn (Builder $applications) => $applications->whereKeyNot($except->id))
            ->whereHas('decisionReleases', fn (Builder $releases) => $releases
                ->where('decision', ReviewDecision::Approved->value))
            ->whereHas('certificates', fn (Builder $certificates) => $certificates
                ->whereIn('status', [CertificateStatus::Released->value, CertificateStatus::Claimed->value]))
            ->exists();
    }

    private function throwLimitReached(): never
    {
        throw ValidationException::withMessages([
            'application_limit' => self::REACHED_MESSAGE,
        ]);
    }

    private function throwCertifiedBlocked(): never
    {
        throw ValidationException::withMessages([
            'application_limit' => self::CERTIFIED_MESSAGE,
        ]);
    }
}
