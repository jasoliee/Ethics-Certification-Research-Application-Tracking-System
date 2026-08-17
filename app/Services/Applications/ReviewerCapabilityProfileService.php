<?php

namespace App\Services\Applications;

use App\Enums\ReviewerAssignmentStatus;
use App\Enums\UserRole;
use App\Models\User;

/**
 * Presents the RES-managed Reviewer entitlement and its live capacity state.
 */
class ReviewerCapabilityProfileService
{
    /**
     * @return array{
     *     enabled: bool,
     *     access_active: bool,
     *     classifications: array<int, string>,
     *     capacity: int,
     *     active_load: int,
     *     available_capacity: int,
     *     setup_complete: bool,
     *     eligible: bool,
     *     eligibility_label: string
     * }|null
     */
    public function for(User $user): ?array
    {
        if ($user->role !== UserRole::Adviser) {
            return null;
        }

        $activeLoad = $user->reviewerAssignments()
            ->whereNull('superseded_at')
            ->whereIn('assignment_status', ReviewerAssignmentStatus::activeValues())
            ->count();
        $capacity = max(0, (int) ($user->reviewer_capacity ?? 0));
        $classifications = $user->reviewerClassificationLabels();
        $accessActive = $user->hasReviewerAccess();
        $setupComplete = $user->password_setup_completed_at !== null;
        $eligible = $accessActive
            && $setupComplete
            && $classifications !== []
            && $capacity > 0
            && $activeLoad < $capacity;

        $eligibilityLabel = match (true) {
            ! $user->reviewer_enabled => 'Reviewer access disabled',
            ! $accessActive => 'Account unavailable',
            ! $setupComplete => 'Account setup incomplete',
            $classifications === [] => 'Classification not configured',
            $capacity < 1 => 'Capacity not configured',
            $activeLoad >= $capacity => 'At active capacity',
            default => 'Eligible for assignment',
        };

        return [
            'enabled' => (bool) $user->reviewer_enabled,
            'access_active' => $accessActive,
            'classifications' => $classifications,
            'capacity' => $capacity,
            'active_load' => $activeLoad,
            'available_capacity' => max(0, $capacity - $activeLoad),
            'setup_complete' => $setupComplete,
            'eligible' => $eligible,
            'eligibility_label' => $eligibilityLabel,
        ];
    }
}
