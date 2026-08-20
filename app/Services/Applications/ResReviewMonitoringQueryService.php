<?php

namespace App\Services\Applications;

use App\Enums\AccountStatus;
use App\Enums\ApplicationStatus;
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
     *     conflicts: Collection<int, ResearchApplication>,
     *     reviewerWorkloads: LengthAwarePaginator<int, User>,
     *     adviserWorkloads: LengthAwarePaginator<int, User>,
     *     adviserDepartments: Collection<int, string>
     * }
     */
    public function dashboard(array $filters): array
    {
        return [
            'metrics' => $this->metrics($filters),
            'conflicts' => $this->conflicts($filters),
            'reviewerWorkloads' => $this->reviewerWorkloads($filters),
            'adviserWorkloads' => $this->adviserWorkloads($filters),
            'adviserDepartments' => $this->adviserDepartments(),
            'adviserInstitutions' => $this->adviserInstitutions(),
            'reviewerDepartments' => $this->reviewerDepartments(),
            'reviewerInstitutions' => $this->reviewerInstitutions(),
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
        $query = $this->authorizedAdvisersQuery()
            ->select([
                'id',
                'name',
                'role',
                'position_title',
                'department',
                'institution',
                'expected_endorsement_count',
            ])
            ->withCount([
                'advisedApplications as awaiting_endorsement_count' => fn (Builder $applications) => $applications
                    ->whereNotNull('submitted_at')
                    ->where('application_status', ApplicationStatus::SubmittedToAdviser->value)
                    ->when(filled($filters['academic_term_id'] ?? null), fn (Builder $applications) => $applications
                        ->where('academic_term_id', (int) $filters['academic_term_id'])),
            ])
            ->when(filled($filters['adviser_q'] ?? null), function (Builder $advisers) use ($filters): void {
                $search = trim((string) $filters['adviser_q']);
                $advisers->where('name', 'like', "%{$search}%");
            })
            ->when(filled($filters['adviser_department'] ?? null), fn (Builder $advisers) => $advisers
                ->whereRaw('LOWER(department) = ?', [mb_strtolower(trim((string) $filters['adviser_department']))]))
            ->when(filled($filters['adviser_institution'] ?? null), fn (Builder $advisers) => $advisers
                ->whereRaw('LOWER(institution) = ?', [mb_strtolower(trim((string) $filters['adviser_institution']))]));

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
                $this->endorsementStatistics->for(
                    $adviser,
                    filled($filters['academic_term_id'] ?? null) ? (int) $filters['academic_term_id'] : null,
                ),
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

    /** @return Collection<int, string> */
    private function adviserInstitutions(): Collection
    {
        return $this->staffFilterOptions($this->authorizedAdvisersQuery(), 'institution');
    }

    /** @return Collection<int, string> */
    private function reviewerDepartments(): Collection
    {
        return $this->staffFilterOptions(User::query()->reviewerEnabled(), 'department');
    }

    /** @return Collection<int, string> */
    private function reviewerInstitutions(): Collection
    {
        return $this->staffFilterOptions(User::query()->reviewerEnabled(), 'institution');
    }

    /** @return Collection<int, string> */
    private function staffFilterOptions(Builder $query, string $column): Collection
    {
        return $query
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->orderBy($column)
            ->pluck($column)
            ->unique(fn (string $value): string => mb_strtolower(trim($value)))
            ->values();
    }

    private function authorizedAdvisersQuery(): Builder
    {
        return User::query()
            ->where('role', UserRole::Adviser->value)
            ->where('account_status', AccountStatus::Active->value);
    }

    /**
     * Keep unresolved Full Board disagreement visible regardless of table filters.
     *
     * @return Collection<int, ResearchApplication>
     */
    private function conflicts(array $filters): Collection
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
            ->when(filled($filters['academic_term_id'] ?? null), fn (Builder $applications) => $applications
                ->where('academic_term_id', (int) $filters['academic_term_id']))
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
    private function reviewerWorkloads(array $filters): LengthAwarePaginator
    {
        $reviewers = User::query()
            ->reviewerEnabled()
            ->select([
                'id',
                'name',
                'position_title',
                'department',
                'institution',
                'reviewer_capacity',
                'reviewer_enabled',
            ])
            ->when(filled($filters['reviewer_q'] ?? null), fn (Builder $query) => $query
                ->where('name', 'like', '%'.trim((string) $filters['reviewer_q']).'%'))
            ->when(filled($filters['reviewer_department'] ?? null), fn (Builder $query) => $query
                ->whereRaw('LOWER(department) = ?', [mb_strtolower(trim((string) $filters['reviewer_department']))]))
            ->when(filled($filters['reviewer_institution'] ?? null), fn (Builder $query) => $query
                ->whereRaw('LOWER(institution) = ?', [mb_strtolower(trim((string) $filters['reviewer_institution']))]))
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(12, ['*'], 'reviewers_page')
            ->withQueryString();

        $termId = filled($filters['academic_term_id'] ?? null) ? (int) $filters['academic_term_id'] : null;
        $reviewers->getCollection()->each(function (User $reviewer) use ($termId): void {
            $assignments = $reviewer->reviewerAssignments()
                ->whereNull('superseded_at')
                ->when($termId, fn (Builder $query) => $query
                    ->whereHas('researchApplication', fn (Builder $applications) => $applications
                        ->where('academic_term_id', $termId)));
            $reviewer->setAttribute('active_assignment_count', (clone $assignments)
                ->whereIn('assignment_status', ReviewerAssignmentStatus::activeValues())
                ->distinct('research_application_id')
                ->count('research_application_id'));
            $reviewer->setAttribute('completed_application_count', (clone $assignments)
                ->whereHas('researchApplication', fn (Builder $applications) => $applications
                    ->whereIn('application_status', [
                        ApplicationStatus::ForCertificateRelease->value,
                        ApplicationStatus::CertificateReleased->value,
                    ])
                    ->whereHas('decisionReleases', fn (Builder $releases) => $releases
                        ->where('decision', 'approved')))
                ->distinct('research_application_id')
                ->count('research_application_id'));
        });

        return $reviewers;
    }

    /**
     * @return array{active_applications: int, active_assignments: int, completed_assignments: int, total_assignments: int, completion_rate: int, overdue_assignments: int, conflicted_applications: int}
     */
    private function metrics(array $filters): array
    {
        $current = ReviewerAssignment::query();
        $this->currentAssignments($current);
        if (filled($filters['academic_term_id'] ?? null)) {
            $current->whereHas('researchApplication', fn (Builder $applications) => $applications
                ->where('academic_term_id', (int) $filters['academic_term_id']));
        }

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
                ->when(filled($filters['academic_term_id'] ?? null), fn (Builder $applications) => $applications
                    ->where('academic_term_id', (int) $filters['academic_term_id']))
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
