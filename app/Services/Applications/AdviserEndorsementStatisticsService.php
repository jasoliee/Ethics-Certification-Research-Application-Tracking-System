<?php

namespace App\Services\Applications;

use App\Enums\ApplicationStatus;
use App\Enums\EndorsementStatus;
use App\Enums\UserRole;
use App\Models\User;

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
        $endorsed = $user->endorsements()
            ->where('endorsement_status', EndorsementStatus::Endorsed->value)
            ->when($academicTermId, fn ($endorsements) => $endorsements
                ->whereHas('researchApplication', fn ($applications) => $applications
                    ->where('academic_term_id', $academicTermId)))
            ->distinct('research_application_id')
            ->count('research_application_id');
        $awaiting = $user->advisedApplications()
            ->whereNotNull('submitted_at')
            ->where('application_status', ApplicationStatus::SubmittedToAdviser->value)
            ->when($academicTermId, fn ($applications) => $applications
                ->where('academic_term_id', $academicTermId))
            ->count();
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
