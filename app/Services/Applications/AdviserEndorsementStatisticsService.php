<?php

namespace App\Services\Applications;

use App\Enums\ApplicationStatus;
use App\Enums\EndorsementStatus;
use App\Enums\UserRole;
use App\Models\ResearchApplication;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Calculates the Adviser endorsement workload from authoritative workflow records.
 */
class AdviserEndorsementStatisticsService
{
    /**
     * @return array{declared: int, endorsed: int, awaiting: int, remaining: int, not_received: int}|null
     */
    public function for(User $user, ?int $academicTermId = null): ?array
    {
        if ($user->role !== UserRole::Adviser) {
            return null;
        }

        $declared = max(0, (int) ($user->expected_endorsement_count ?? 0));
        // Workload is account-scoped: several applications from one Applicant still
        // represent one expected, completed, or awaiting endorsement relationship.
        $endorsedApplicantIds = ResearchApplication::query()
            ->whereHas('endorsements', fn (Builder $endorsements) => $endorsements
                ->where('adviser_user_id', $user->id)
                ->where('endorsement_status', EndorsementStatus::Endorsed->value))
            ->when($academicTermId, fn (Builder $applications) => $applications
                ->where('academic_term_id', $academicTermId))
            ->whereNotNull('applicant_user_id')
            ->distinct()
            ->pluck('applicant_user_id');
        $endorsed = $endorsedApplicantIds->count();
        $awaiting = $user->advisedApplications()
            ->whereNotNull('submitted_at')
            ->where('application_status', ApplicationStatus::SubmittedToAdviser->value)
            ->when($academicTermId, fn ($applications) => $applications
                ->where('academic_term_id', $academicTermId))
            ->whereNotIn('applicant_user_id', $endorsedApplicantIds)
            ->whereNotNull('applicant_user_id')
            ->distinct('applicant_user_id')
            ->count('applicant_user_id');
        $remaining = max(0, $declared - $endorsed);

        return [
            'declared' => $declared,
            'endorsed' => $endorsed,
            'awaiting' => $awaiting,
            'remaining' => $remaining,
            'not_received' => max(0, $remaining - $awaiting),
        ];
    }
}
