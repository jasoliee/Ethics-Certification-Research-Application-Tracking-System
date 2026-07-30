<?php

namespace App\Services\Applications;

use App\Models\ResearchApplication;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Enforces the approved per-applicant limit using formal submission history, not drafts.
 */
class ApplicationSubmissionLimit
{
    public const MAX_APPLICATIONS = 3;

    public const REACHED_MESSAGE = 'Application Submission Limit Reached. A maximum of 3 formally submitted applications is allowed.';

    public function submittedCount(User $applicant): int
    {
        return ResearchApplication::query()
            ->where('applicant_user_id', $applicant->id)
            ->whereNotNull('submitted_at')
            ->count();
    }

    /**
     * @return array{submitted_count: int, maximum: int, reached: bool}
     */
    public function status(User $applicant): array
    {
        $submittedCount = $this->submittedCount($applicant);

        return [
            'submitted_count' => $submittedCount,
            'maximum' => self::MAX_APPLICATIONS,
            'reached' => $submittedCount >= self::MAX_APPLICATIONS,
        ];
    }

    public function canCreate(User $applicant): bool
    {
        return $this->submittedCount($applicant) < self::MAX_APPLICATIONS;
    }

    /**
     * Exclude the current record so a returned application keeps its original slot.
     */
    public function canSubmit(User $applicant, ResearchApplication $application): bool
    {
        return ResearchApplication::query()
            ->where('applicant_user_id', $applicant->id)
            ->whereKeyNot($application->id)
            ->whereNotNull('submitted_at')
            ->count() < self::MAX_APPLICATIONS;
    }

    public function assertCanCreate(User $applicant): void
    {
        if (! $this->canCreate($applicant)) {
            $this->throwLimitReached();
        }
    }

    public function assertCanSubmit(User $applicant, ResearchApplication $application): void
    {
        if (! $this->canSubmit($applicant, $application)) {
            $this->throwLimitReached();
        }
    }

    private function throwLimitReached(): never
    {
        throw ValidationException::withMessages([
            'application_limit' => self::REACHED_MESSAGE,
        ]);
    }
}
