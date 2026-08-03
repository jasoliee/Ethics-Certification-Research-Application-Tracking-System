<?php

namespace App\Services\Applications;

use App\Enums\AccountStatus;
use App\Enums\ReviewerAssignmentStatus;
use App\Enums\ReviewType;
use App\Enums\UserRole;
use App\Models\ResearchApplication;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Resolves and revalidates active reviewers against classification, account state, conflicts, and workload.
 */
class ReviewerEligibilityService
{
    /**
     * Return a bounded reviewer page while retaining full-load rows as visibly unavailable choices.
     *
     * @param  array{reviewer_q?: string|null, department?: string|null}  $filters
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
                'department',
                'program',
                'reviewer_classification',
                'reviewer_capacity',
            ])
            ->withCount(['reviewerAssignments as active_assignment_count' => fn (Builder $assignments) => $assignments
                ->whereIn('assignment_status', ReviewerAssignmentStatus::activeValues())]);

        $query->when(filled($filters['reviewer_q'] ?? null), function (Builder $reviewers) use ($filters): void {
            $search = trim((string) $filters['reviewer_q']);

            $reviewers->where(function (Builder $matching) use ($search): void {
                $matching
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('position_title', 'like', "%{$search}%")
                    ->orWhere('department', 'like', "%{$search}%");
            });
        });

        // Department filtering is exact and optional; cross-discipline accounts remain visible by default.
        $query->when(filled($filters['department'] ?? null), fn (Builder $reviewers) => $reviewers
            ->whereRaw('LOWER(department) = ?', [mb_strtolower(trim((string) $filters['department']))]));

        $this->prioritizeApplicationMatch($query, $application);

        return $query
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(15, ['*'], 'reviewers_page')
            ->withQueryString();
    }

    /**
     * Return the bounded Department filter catalog from accounts eligible for the saved review classification.
     *
     * @return Collection<int, string>
     */
    public function departmentOptions(
        ResearchApplication $application,
        ReviewType $reviewType,
    ): Collection {
        return $this->eligibleAccountsQuery($application, $reviewType)
            ->whereNotNull('department')
            ->where('department', '!=', '')
            ->orderBy('department')
            ->pluck('department')
            ->unique(fn (string $department): string => mb_strtolower(trim($department)))
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
        $classification = mb_strtolower(trim((string) $reviewer->reviewer_classification));
        $expectedClassification = mb_strtolower((string) $reviewType->reviewerClassification());
        $knownConflict = in_array($reviewer->id, [
            $application->applicant_user_id,
            $application->adviser_user_id,
        ], true);

        if ($reviewer->role !== UserRole::Reviewer
            || $reviewer->account_status !== AccountStatus::Active->value
            || $reviewer->trashed()
            || $reviewer->password_setup_completed_at === null
            || $classification !== $expectedClassification
            || $knownConflict) {
            throw ValidationException::withMessages([
                'reviewer_ids' => 'One or more selected reviewers are no longer eligible for this application.',
            ])->errorBag('reviewerAssignment');
        }

        $activeLoad = $reviewer->reviewerAssignments()
            ->whereIn('assignment_status', ReviewerAssignmentStatus::activeValues())
            ->count();
        $capacity = (int) ($reviewer->reviewer_capacity ?? 0);

        if ($capacity < 1 || $activeLoad >= $capacity) {
            throw ValidationException::withMessages([
                'reviewer_ids' => 'One or more selected reviewers have reached their active review capacity.',
            ])->errorBag('reviewerAssignment');
        }
    }

    /**
     * Build the shared active-account boundary used by candidate rows and Department options.
     */
    private function eligibleAccountsQuery(
        ResearchApplication $application,
        ReviewType $reviewType,
    ): Builder {
        return User::query()
            ->where('role', UserRole::Reviewer->value)
            ->where('account_status', AccountStatus::Active->value)
            ->whereNotNull('password_setup_completed_at')
            ->whereNotIn('id', array_filter([
                $application->applicant_user_id,
                $application->adviser_user_id,
            ]))
            ->whereRaw('LOWER(reviewer_classification) = ?', [
                mb_strtolower((string) $reviewType->reviewerClassification()),
            ]);
    }

    /**
     * Sort exact Department matches first, then Institution matches, without excluding other reviewers.
     */
    private function prioritizeApplicationMatch(
        Builder $query,
        ResearchApplication $application,
    ): void {
        $department = mb_strtolower(trim((string) $application->department));
        $institution = mb_strtolower(trim((string) $application->institution));

        if ($department !== '' && $institution !== '') {
            $query->orderByRaw(
                "CASE WHEN LOWER(COALESCE(department, '')) = ? THEN 0 WHEN LOWER(COALESCE(institution, '')) = ? THEN 1 ELSE 2 END",
                [$department, $institution],
            );

            return;
        }

        if ($department !== '') {
            $query->orderByRaw("CASE WHEN LOWER(COALESCE(department, '')) = ? THEN 0 ELSE 1 END", [$department]);

            return;
        }

        if ($institution !== '') {
            $query->orderByRaw("CASE WHEN LOWER(COALESCE(institution, '')) = ? THEN 0 ELSE 1 END", [$institution]);
        }
    }
}
