<?php

namespace App\Services\Applications;

use App\Enums\AccountStatus;
use App\Enums\ReviewerAssignmentStatus;
use App\Enums\ReviewType;
use App\Models\ResearchApplication;
use App\Models\ReviewerConflict;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use App\Support\ReviewerCapacity;

/**
 * Resolves and revalidates reviewer-enabled Advisers against account state, conflicts, and workload.
 */
class ReviewerEligibilityService
{
    /**
     * Return a bounded reviewer page while retaining full-load rows as visibly unavailable choices.
     *
     * @param  array{reviewer_q?: string|null, institute?: string|null}  $filters
     * @return LengthAwarePaginator<int, User>
     */
    public function paginateCandidates(
        ResearchApplication $application,
        ReviewType $reviewType,
        array $filters,
    ): LengthAwarePaginator {
        $query = $this->eligibleAccountsQuery($application, $reviewType)
            ->select([
                'id',
                'name',
                'position_title',
                'institution',
                'program',
                'reviewer_capacity',
                'reviewer_enabled',
            ])
            ->withCount(['reviewerAssignments as active_assignment_count' => fn (Builder $assignments) => $assignments
                ->whereIn('assignment_status', ReviewerAssignmentStatus::activeValues())]);

        $query->when(filled($filters['reviewer_q'] ?? null), function (Builder $reviewers) use ($filters): void {
            $search = trim((string) $filters['reviewer_q']);

            $reviewers->where(function (Builder $matching) use ($search): void {
                $matching
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('position_title', 'like', "%{$search}%")
                    ->orWhere('institution', 'like', "%{$search}%");
            });
        });

        $query->when(filled($filters['institute'] ?? null), fn (Builder $reviewers) => $reviewers
            ->whereRaw('LOWER(institution) = ?', [mb_strtolower(trim((string) $filters['institute']))]));

        $this->prioritizeApplicationMatch($query, $application);

        return $query
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(15, ['*'], 'reviewers_page')
            ->withQueryString();
    }

    /** @return Collection<int, string> */
    public function instituteOptions(
        ResearchApplication $application,
        ReviewType $reviewType,
    ): Collection {
        return $this->eligibleAccountsQuery($application, $reviewType)
            ->whereNotNull('institution')
            ->where('institution', '!=', '')
            ->orderBy('institution')
            ->pluck('institution')
            ->unique(fn (string $institute): string => mb_strtolower(trim($institute)))
            ->values();
    }

    /**
     * Repeat every reviewer eligibility rule after reviewer rows are locked for assignment.
     */
    public function assertEligible(
        User $reviewer,
        ResearchApplication $application,
        ReviewType $reviewType,
    ): void {
        $endorsedThisApplication = $reviewer->endorsements()
            ->where('research_application_id', $application->id)
            ->exists();
        $declaredConflict = ReviewerConflict::query()
            ->where('research_application_id', $application->id)
            ->where('reviewer_user_id', $reviewer->id)
            ->whereNull('cleared_at')
            ->exists();

        if (! $reviewer->hasReviewerAccess()
            || $reviewer->password_setup_completed_at === null
            || $endorsedThisApplication
            || $declaredConflict) {
            throw ValidationException::withMessages([
                'reviewer_ids' => 'One or more selected reviewers are no longer eligible for this application.',
            ])->errorBag('reviewerAssignment');
        }

        $activeLoad = $reviewer->reviewerAssignments()
            ->whereIn('assignment_status', ReviewerAssignmentStatus::activeValues())
            ->count();
        $capacity = ReviewerCapacity::MAX_ACTIVE_ASSIGNMENTS;

        if ($capacity < 1 || $activeLoad >= $capacity) {
            throw ValidationException::withMessages([
                'reviewer_ids' => 'One or more selected reviewers have reached their active review capacity.',
            ])->errorBag('reviewerAssignment');
        }
    }

    /**
     * Build the shared active-account boundary used by candidate rows.
     */
    private function eligibleAccountsQuery(
        ResearchApplication $application,
        ReviewType $reviewType,
    ): Builder {
        return User::query()
            ->reviewerEnabled()
            ->where('account_status', AccountStatus::Active->value)
            ->whereNotNull('password_setup_completed_at')
            ->whereDoesntHave('endorsements', fn (Builder $endorsements) => $endorsements
                ->where('research_application_id', $application->id))
            ->whereDoesntHave('reviewerConflicts', fn (Builder $conflicts) => $conflicts
                ->where('research_application_id', $application->id)
                ->whereNull('cleared_at'));
    }

    /**
     * Sort exact Institute matches first without excluding other reviewers.
     */
    private function prioritizeApplicationMatch(
        Builder $query,
        ResearchApplication $application,
    ): void {
        $institution = mb_strtolower(trim((string) $application->institution));

        if ($institution !== '') {
            $query->orderByRaw("CASE WHEN LOWER(COALESCE(institution, '')) = ? THEN 0 ELSE 1 END", [$institution]);
        }
    }
}
