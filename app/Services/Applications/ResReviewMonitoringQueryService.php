<?php

namespace App\Services\Applications;

use App\Enums\AccountStatus;
use App\Enums\ApplicationStatus;
use App\Enums\EndorsementStatus;
use App\Enums\ReviewConsensusStatus;
use App\Enums\ReviewerAssignmentStatus;
use App\Enums\ReviewType;
use App\Enums\UserRole;
use App\Models\ResearchApplication;
use App\Models\ReviewerAssignment;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;

/**
 * Builds the privacy-limited operational view used by the RES review monitor.
 *
 * Applicant identities and review comments are deliberately absent from every
 * projection and relationship in this service. Staff identities are loaded only
 * for the separate capacity/workload panels; application rows stay anonymous.
 */
class ResReviewMonitoringQueryService
{
    public function __construct(
        private readonly AdviserEndorsementStatisticsService $endorsementStatistics,
    ) {}

    /**
     * @param  array{q?: string|null, review_type?: string|null, assignment_status?: string|null, deadline?: string|null, consensus?: string|null, adviser_q?: string|null, adviser_department?: string|null, adviser_workload?: string|null}  $filters
     * @return array{
     *     metrics: array{active_applications: int, active_assignments: int, completed_assignments: int, total_assignments: int, completion_rate: int, overdue_assignments: int, conflicted_applications: int},
     *     applications: LengthAwarePaginator<int, ResearchApplication>,
     *     conflicts: Collection<int, ResearchApplication>,
     *     reviewerWorkloads: LengthAwarePaginator<int, User>,
     *     adviserWorkloads: LengthAwarePaginator<int, User>,
     *     adviserDepartments: Collection<int, string>
     * }
     */
    public function dashboard(array $filters): array
    {
        return [
            'metrics' => $this->metrics(),
            'applications' => $this->applications($filters),
            'conflicts' => $this->conflicts(),
            'reviewerWorkloads' => $this->reviewerWorkloads(),
            'adviserWorkloads' => $this->adviserWorkloads($filters),
            'adviserDepartments' => $this->adviserDepartments(),
        ];
    }

    /**
     * Show active Adviser endorsement workload while keeping Applicant data outside the projection.
     *
     * The shared statistics service remains the source of truth for every displayed count. SQL
     * predicates below only narrow the requested page and mirror those authoritative definitions.
     *
     * @param  array{adviser_q?: string|null, adviser_department?: string|null, adviser_workload?: string|null}  $filters
     * @return LengthAwarePaginator<int, User>
     */
    private function adviserWorkloads(array $filters): LengthAwarePaginator
    {
        $endorsedCount = '(SELECT COUNT(DISTINCT endorsements.research_application_id)'
            .' FROM endorsements'
            .' WHERE endorsements.adviser_user_id = users.id'
            .' AND endorsements.endorsement_status = ?)';
        $awaitingCount = '(SELECT COUNT(*)'
            .' FROM research_applications'
            .' WHERE research_applications.adviser_user_id = users.id'
            .' AND research_applications.submitted_at IS NOT NULL'
            .' AND research_applications.application_status = ?)';

        $query = $this->authorizedAdvisersQuery()
            ->select([
                'id',
                'name',
                'role',
                'position_title',
                'department',
                'expected_endorsement_count',
            ])
            ->withCount([
                'advisedApplications as awaiting_endorsement_count' => fn (Builder $applications) => $applications
                    ->whereNotNull('submitted_at')
                    ->where('application_status', ApplicationStatus::SubmittedToAdviser->value),
            ])
            ->with([
                'advisedApplications' => fn (Builder|Relation $applications) => $applications
                    ->select([
                        'id',
                        'adviser_user_id',
                        'application_code',
                        'application_status',
                        'submitted_at',
                        'status_updated_at',
                    ])
                    ->whereNotNull('submitted_at')
                    ->orderByDesc('status_updated_at')
                    ->orderByDesc('id')
                    ->limit(4),
            ])
            ->when(filled($filters['adviser_q'] ?? null), function (Builder $advisers) use ($filters): void {
                $search = trim((string) $filters['adviser_q']);

                $advisers->where(function (Builder $matching) use ($search): void {
                    $matching
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('position_title', 'like', "%{$search}%")
                        ->orWhere('department', 'like', "%{$search}%");
                });
            })
            ->when(filled($filters['adviser_department'] ?? null), fn (Builder $advisers) => $advisers
                ->whereRaw('LOWER(department) = ?', [mb_strtolower(trim((string) $filters['adviser_department']))]))
            ->when(($filters['adviser_workload'] ?? null) === 'awaiting_action', fn (Builder $advisers) => $advisers
                ->whereHas('advisedApplications', fn (Builder $applications) => $applications
                    ->whereNotNull('submitted_at')
                    ->where('application_status', ApplicationStatus::SubmittedToAdviser->value)))
            ->when(($filters['adviser_workload'] ?? null) === 'remaining_expected', fn (Builder $advisers) => $advisers
                ->whereRaw(
                    "COALESCE(users.expected_endorsement_count, 0) > {$endorsedCount}",
                    [EndorsementStatus::Endorsed->value],
                ))
            ->when(($filters['adviser_workload'] ?? null) === 'not_received', fn (Builder $advisers) => $advisers
                ->whereRaw(
                    "COALESCE(users.expected_endorsement_count, 0) - {$endorsedCount} - {$awaitingCount} > 0",
                    [
                        EndorsementStatus::Endorsed->value,
                        ApplicationStatus::SubmittedToAdviser->value,
                    ],
                ))
            ->when(($filters['adviser_workload'] ?? null) === 'target_met', fn (Builder $advisers) => $advisers
                ->where('expected_endorsement_count', '>', 0)
                ->whereRaw(
                    "users.expected_endorsement_count <= {$endorsedCount}",
                    [EndorsementStatus::Endorsed->value],
                ))
            ->when(($filters['adviser_workload'] ?? null) === 'no_target', fn (Builder $advisers) => $advisers
                ->where(fn (Builder $target) => $target
                    ->whereNull('expected_endorsement_count')
                    ->orWhere('expected_endorsement_count', '<=', 0)));

        $advisers = $query
            ->orderByDesc('awaiting_endorsement_count')
            ->orderByDesc('expected_endorsement_count')
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(12, ['*'], 'advisers_page')
            ->withQueryString();

        $advisers->getCollection()->each(function (User $adviser): void {
            $adviser->setAttribute(
                'endorsement_statistics',
                $this->endorsementStatistics->for($adviser),
            );
        });

        return $advisers;
    }

    /**
     * Return the active Adviser Department filter catalog without identity or application joins.
     *
     * @return Collection<int, string>
     */
    private function adviserDepartments(): Collection
    {
        return $this->authorizedAdvisersQuery()
            ->whereNotNull('department')
            ->where('department', '!=', '')
            ->orderBy('department')
            ->pluck('department')
            ->unique(fn (string $department): string => mb_strtolower(trim($department)))
            ->values();
    }

    private function authorizedAdvisersQuery(): Builder
    {
        return User::query()
            ->where('role', UserRole::Adviser->value)
            ->where('account_status', AccountStatus::Active->value);
    }

    /**
     * Return application-level progress without loading Applicant or Reviewer identities.
     *
     * @param  array{q?: string|null, review_type?: string|null, assignment_status?: string|null, deadline?: string|null, consensus?: string|null}  $filters
     * @return LengthAwarePaginator<int, ResearchApplication>
     */
    private function applications(array $filters): LengthAwarePaginator
    {
        $query = ResearchApplication::query()
            ->select([
                'id',
                'application_code',
                'research_title',
                'review_type',
                'application_status',
                'current_revision_cycle',
                'review_consensus_status',
                'review_consensus_cycle',
                'review_consensus_decision',
                'review_conflicted_at',
                'status_updated_at',
            ])
            ->whereHas('reviewerAssignments', fn (Builder $assignments) => $this->currentAssignments($assignments))
            ->withCount([
                'reviewerAssignments as current_assignments_count' => fn (Builder $assignments) => $this->currentAssignments($assignments),
                'reviewerAssignments as submitted_assignments_count' => fn (Builder $assignments) => $this
                    ->currentAssignments($assignments)
                    ->where('assignment_status', ReviewerAssignmentStatus::DecisionSubmitted->value),
                'reviewerAssignments as overdue_assignments_count' => fn (Builder $assignments) => $this
                    ->currentAssignments($assignments)
                    ->whereIn('assignment_status', ReviewerAssignmentStatus::activeValues())
                    ->whereNotNull('review_deadline_at')
                    ->where('review_deadline_at', '<', now()),
            ])
            ->with([
                'reviewerAssignments' => fn (Builder|Relation $assignments) => $this
                    ->currentAssignments($assignments)
                    ->select([
                        'id',
                        'research_application_id',
                        'review_type',
                        'review_cycle',
                        'assignment_status',
                        'assignment_sequence',
                        'assigned_at',
                        'review_deadline_at',
                        'submitted_at',
                    ])
                    ->with([
                        'reviewSubmission:id,reviewer_assignment_id,current_version_id,status,decision,submitted_at',
                        'reviewSubmission.currentVersion:id,review_submission_id,decision,submitted_at',
                    ])
                    ->orderBy('assignment_sequence')
                    ->orderBy('id'),
            ]);

        $query
            ->when(filled($filters['q'] ?? null), function (Builder $applications) use ($filters): void {
                $search = trim((string) $filters['q']);

                $applications->where(function (Builder $matching) use ($search): void {
                    $matching
                        ->where('application_code', 'like', "%{$search}%")
                        ->orWhere('research_title', 'like', "%{$search}%");
                });
            })
            ->when(filled($filters['review_type'] ?? null), fn (Builder $applications) => $applications
                ->where('review_type', $filters['review_type']))
            ->when(filled($filters['consensus'] ?? null), fn (Builder $applications) => $applications
                ->where('review_consensus_status', $filters['consensus']))
            ->when(filled($filters['assignment_status'] ?? null), fn (Builder $applications) => $applications
                ->whereHas('reviewerAssignments', function (Builder $assignments) use ($filters): void {
                    $this->currentAssignments($assignments)
                        ->where('assignment_status', $filters['assignment_status']);
                }))
            ->when(filled($filters['deadline'] ?? null), function (Builder $applications) use ($filters): void {
                $applications->whereHas('reviewerAssignments', function (Builder $assignments) use ($filters): void {
                    $this->currentAssignments($assignments)
                        ->whereIn('assignment_status', ReviewerAssignmentStatus::activeValues());

                    match ($filters['deadline']) {
                        'overdue' => $assignments
                            ->whereNotNull('review_deadline_at')
                            ->where('review_deadline_at', '<', now()),
                        'due_soon' => $assignments
                            ->whereBetween('review_deadline_at', [now(), now()->addDays(3)]),
                        'on_track' => $assignments
                            ->where('review_deadline_at', '>', now()->addDays(3)),
                        'no_deadline' => $assignments->whereNull('review_deadline_at'),
                        default => null,
                    };
                });
            });

        return $query
            ->orderByRaw(
                'CASE WHEN review_consensus_status = ? THEN 0 ELSE 1 END',
                [ReviewConsensusStatus::Conflicted->value],
            )
            ->orderByDesc('overdue_assignments_count')
            ->orderByDesc('status_updated_at')
            ->orderByDesc('id')
            ->paginate(15, ['*'], 'monitoring_page')
            ->withQueryString();
    }

    /**
     * Keep unresolved Full Board disagreement visible regardless of table filters.
     *
     * @return Collection<int, ResearchApplication>
     */
    private function conflicts(): Collection
    {
        return ResearchApplication::query()
            ->select([
                'id',
                'application_code',
                'research_title',
                'review_type',
                'application_status',
                'review_consensus_status',
                'review_consensus_cycle',
                'review_conflicted_at',
            ])
            ->where('review_type', ReviewType::FullBoard->value)
            ->where('review_consensus_status', ReviewConsensusStatus::Conflicted->value)
            ->whereHas('reviewerAssignments', fn (Builder $assignments) => $this->currentAssignments($assignments))
            ->with([
                'reviewerAssignments' => fn (Builder|Relation $assignments) => $this
                    ->currentAssignments($assignments)
                    ->select([
                        'id',
                        'research_application_id',
                        'review_type',
                        'review_cycle',
                        'assignment_status',
                        'assignment_sequence',
                        'submitted_at',
                    ])
                    ->with([
                        'reviewSubmission:id,reviewer_assignment_id,current_version_id,status,decision,submitted_at',
                        'reviewSubmission.currentVersion:id,review_submission_id,decision,submitted_at',
                    ])
                    ->orderBy('assignment_sequence')
                    ->orderBy('id'),
            ])
            ->orderByDesc('review_conflicted_at')
            ->orderByDesc('id')
            ->limit(6)
            ->get();
    }

    /**
     * Show only reviewer-enabled Adviser staffing data and operational counts.
     *
     * @return LengthAwarePaginator<int, User>
     */
    private function reviewerWorkloads(): LengthAwarePaginator
    {
        return User::query()
            ->reviewerEnabled()
            ->select([
                'id',
                'name',
                'position_title',
                'department',
                'reviewer_classification',
                'reviewer_classifications',
                'reviewer_capacity',
                'reviewer_enabled',
            ])
            ->withCount([
                'reviewerAssignments as active_assignment_count' => fn (Builder $assignments) => $this
                    ->currentAssignments($assignments)
                    ->whereIn('assignment_status', ReviewerAssignmentStatus::activeValues()),
                'reviewerAssignments as overdue_assignment_count' => fn (Builder $assignments) => $this
                    ->currentAssignments($assignments)
                    ->whereIn('assignment_status', ReviewerAssignmentStatus::activeValues())
                    ->whereNotNull('review_deadline_at')
                    ->where('review_deadline_at', '<', now()),
            ])
            ->orderByRaw(
                'CASE WHEN reviewer_capacity IS NOT NULL AND reviewer_capacity > 0 AND active_assignment_count >= reviewer_capacity THEN 0 ELSE 1 END',
            )
            ->orderByDesc('active_assignment_count')
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(12, ['*'], 'reviewers_page')
            ->withQueryString();
    }

    /**
     * @return array{active_applications: int, active_assignments: int, completed_assignments: int, total_assignments: int, completion_rate: int, overdue_assignments: int, conflicted_applications: int}
     */
    private function metrics(): array
    {
        $current = ReviewerAssignment::query();
        $this->currentAssignments($current);

        $totalAssignments = (clone $current)->count();
        $completedAssignments = (clone $current)
            ->where('assignment_status', ReviewerAssignmentStatus::DecisionSubmitted->value)
            ->count();
        $activeAssignments = (clone $current)
            ->whereIn('assignment_status', ReviewerAssignmentStatus::activeValues());

        return [
            'active_applications' => (clone $activeAssignments)
                ->distinct()
                ->count('research_application_id'),
            'active_assignments' => (clone $activeAssignments)->count(),
            'completed_assignments' => $completedAssignments,
            'total_assignments' => $totalAssignments,
            'completion_rate' => $totalAssignments > 0
                ? (int) round(($completedAssignments / $totalAssignments) * 100)
                : 0,
            'overdue_assignments' => (clone $activeAssignments)
                ->whereNotNull('review_deadline_at')
                ->where('review_deadline_at', '<', now())
                ->count(),
            'conflicted_applications' => ResearchApplication::query()
                ->where('review_type', ReviewType::FullBoard->value)
                ->where('review_consensus_status', ReviewConsensusStatus::Conflicted->value)
                ->count(),
        ];
    }

    /**
     * Apply the shared non-superseded assignment boundary.
     */
    private function currentAssignments(Builder|Relation $query): Builder|Relation
    {
        return $query
            ->whereNull('superseded_at')
            ->where('assignment_status', '!=', ReviewerAssignmentStatus::Superseded->value);
    }
}
