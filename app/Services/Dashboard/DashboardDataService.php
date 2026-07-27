<?php

namespace App\Services\Dashboard;

use App\Enums\ApplicationStatus;
use App\Enums\ReviewerAssignmentStatus;
use App\Enums\UserRole;
use App\Models\DeadlineConfiguration;
use App\Models\ResearchApplication;
use App\Models\ReviewerAssignment;
use App\Models\TimelineCalendarEvent;
use App\Models\User;
use App\Services\Applications\ApplicationRequirementService;
use App\Services\Applications\ApplicationSubmissionWindow;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Builds bounded, role-scoped dashboard payloads outside Blade templates.
 */
class DashboardDataService
{
    public function __construct(
        private readonly ApplicationRequirementService $requirementService,
        private readonly ApplicationSubmissionWindow $submissionWindow,
    ) {}

    /** @return array<string, mixed> */
    public function applicant(User $user): array
    {
        // Select the newest non-archived applicant-owned application and its assigned Adviser in one query.
        $activeApplication = ResearchApplication::query()
            ->select([
                'id',
                'application_code',
                'applicant_user_id',
                'adviser_user_id',
                'applicant_type',
                'research_title',
                'research_type',
                'application_type',
                'application_status',
                'current_stage',
                'submitted_at',
                'status_updated_at',
                'updated_at',
            ])
            ->where('applicant_user_id', $user->id)
            ->where('application_status', '!=', ApplicationStatus::Archived->value)
            ->with('adviser:id,name')
            ->latest('status_updated_at')
            ->latest('id')
            ->first();

        // Reuse the same mandatory-requirement calculation used by final submission.
        $requirementSummary = $activeApplication
            ? $this->requirementService->summary($activeApplication)
            : $this->emptyRequirementSummary();
        $submissionWindow = $this->submissionWindow->status();

        // Share one deadline snapshot between the alert payload and final-submission availability.
        return [
            'activeApplication' => $activeApplication,
            'hasSubmittedApplication' => $activeApplication?->isFormallySubmitted() ?? false,
            'requirements' => $requirementSummary['items'],
            'requirementSummary' => $requirementSummary,
            'completedRequirementCount' => $requirementSummary['completed_count'],
            'deadline' => $this->submissionWindow->dashboardPayload($submissionWindow),
            'submissionWindow' => $submissionWindow,
            ...$this->timelineData($activeApplication, true),
        ];
    }

    /** @return array<string, mixed> */
    public function adviser(User $user): array
    {
        // Adviser dashboards exclude drafts, incomplete records, archived records, and other Advisers' assignments.
        $base = ResearchApplication::query()
            ->where('adviser_user_id', $user->id)
            ->whereNotNull('submitted_at')
            ->whereNotIn('application_status', [
                ApplicationStatus::Draft->value,
                ApplicationStatus::Incomplete->value,
                ApplicationStatus::Archived->value,
            ]);
        $statusCounts = $this->groupedCounts($base, 'application_status');

        return [
            'counts' => [
                'pending' => $statusCounts->get(ApplicationStatus::SubmittedToAdviser->value, 0),
                'in_review' => $this->sumCounts($statusCounts, ApplicationStatus::values(ApplicationStatus::underReview())),
                'endorsed' => $this->sumCounts($statusCounts, ApplicationStatus::values(ApplicationStatus::afterAdviserEndorsement())),
                'returned' => $statusCounts->get(ApplicationStatus::ReturnedByAdviser->value, 0),
            ],
            'applications' => (clone $base)
                ->select([
                    'id',
                    'application_code',
                    'applicant_user_id',
                    'research_title',
                    'application_status',
                    'current_stage',
                    'submitted_at',
                ])
                ->with('applicant:id,name')
                ->latest('submitted_at')
                ->latest('id')
                ->limit(5)
                ->get(),
            'deadline' => $this->nextDeadline(UserRole::Adviser),
            ...$this->timelineData(),
        ];
    }

    /** @return array<string, mixed> */
    public function reviewer(User $user): array
    {
        $base = ReviewerAssignment::query()->where('reviewer_user_id', $user->id);
        $activeValues = ReviewerAssignmentStatus::activeValues();
        $statusCounts = $this->groupedCounts($base, 'assignment_status');

        $nextAssignmentDeadline = (clone $base)
            ->whereIn('assignment_status', $activeValues)
            ->whereNotNull('review_deadline_at')
            ->where('review_deadline_at', '>=', now())
            ->orderBy('review_deadline_at')
            ->first(['id', 'review_deadline_at']);

        return [
            'counts' => [
                'pending' => $statusCounts->get(ReviewerAssignmentStatus::Pending->value, 0),
                'near_deadline' => (clone $base)
                    ->whereIn('assignment_status', $activeValues)
                    ->whereBetween('review_deadline_at', [now(), now()->addDays(3)])
                    ->count(),
                'revision' => $statusCounts->get(ReviewerAssignmentStatus::RevisionReview->value, 0),
                'completed' => $statusCounts->get(ReviewerAssignmentStatus::DecisionSubmitted->value, 0),
            ],
            'assignments' => (clone $base)
                ->select([
                    'id',
                    'research_application_id',
                    'review_type',
                    'assignment_status',
                    'assigned_at',
                    'review_deadline_at',
                ])
                ->with('researchApplication:id,application_code,research_title,submitted_at')
                ->latest('assigned_at')
                ->latest('id')
                ->limit(5)
                ->get(),
            'deadline' => $nextAssignmentDeadline
                ? $this->deadlinePayload('Remaining days to complete review period', $nextAssignmentDeadline->review_deadline_at)
                : $this->nextDeadline(UserRole::Reviewer),
            ...$this->timelineData(),
        ];
    }

    /** @return array<string, mixed> */
    public function resLead(): array
    {
        $base = ResearchApplication::query();
        $underReview = ApplicationStatus::values(ApplicationStatus::underReview());
        $administrativeStatuses = [
            ApplicationStatus::AdviserEndorsed->value,
            ApplicationStatus::UnderResScreening->value,
            ApplicationStatus::AwaitingReviewerAssignment->value,
            ...$underReview,
            ApplicationStatus::ReviewSubmittedPendingRelease->value,
        ];
        $statusCounts = $this->groupedCounts($base, 'application_status');

        return [
            'counts' => [
                'for_screening' => $statusCounts->get(ApplicationStatus::AdviserEndorsed->value, 0),
                'screening' => $statusCounts->get(ApplicationStatus::UnderResScreening->value, 0),
                'awaiting_assignment' => $statusCounts->get(ApplicationStatus::AwaitingReviewerAssignment->value, 0),
                'under_review' => $this->sumCounts($statusCounts, $underReview),
                'for_release' => $statusCounts->get(ApplicationStatus::ReviewSubmittedPendingRelease->value, 0),
            ],
            'applications' => (clone $base)
                ->select([
                    'id',
                    'application_code',
                    'applicant_type',
                    'review_type',
                    'application_status',
                    'submitted_at',
                ])
                ->whereIn('application_status', $administrativeStatuses)
                ->latest('submitted_at')
                ->latest('id')
                ->limit(5)
                ->get(),
            'deadlines' => $this->availableDeadlines(UserRole::ResLead)
                ->limit(5)
                ->get(['title', 'due_at'])
                ->map(fn (DeadlineConfiguration $deadline): array => $this->deadlinePayload($deadline->title, $deadline->due_at)),
            ...$this->timelineData(),
        ];
    }

    /** @return array<string, mixed>|null */
    private function nextDeadline(UserRole $role): ?array
    {
        $deadline = $this->availableDeadlines($role)->first(['title', 'due_at']);

        return $deadline ? $this->deadlinePayload($deadline->title, $deadline->due_at) : null;
    }

    private function availableDeadlines(UserRole $role): Builder
    {
        return DeadlineConfiguration::query()
            ->where('is_active', true)
            ->where('due_at', '>=', now())
            ->where(function (Builder $query) use ($role): void {
                $query->whereNull('audience_role')->orWhere('audience_role', $role->value);
            })
            ->orderByDesc('priority')
            ->orderBy('due_at');
    }

    /** @return array{title: string, days: int, due_at: Carbon, due_label: string} */
    private function deadlinePayload(string $title, $dueAt): array
    {
        $days = max(0, (int) ceil(now()->diffInDays($dueAt, false)));

        return [
            'title' => $title,
            'days' => $days,
            'due_at' => $dueAt,
            'due_label' => $dueAt->format('M j, Y (g:i A)'),
        ];
    }

    /**
     * Return either calendar-relative milestones or application-stage-relative applicant milestones.
     *
     * @return array{timeline: Collection<int, array<string, mixed>>, termLabel: string|null}
     */
    private function timelineData(
        ?ResearchApplication $application = null,
        bool $applicationScoped = false,
    ): array {
        // Applicants without an application receive the explicit unavailable timeline state.
        if ($applicationScoped && ! $application) {
            return ['timeline' => collect(), 'termLabel' => null];
        }

        // Retrieve the configured six-stage calendar once and preserve its administrative ordering.
        $events = TimelineCalendarEvent::query()
            ->select(['milestone_key', 'label', 'term_label', 'starts_at', 'ends_at', 'sort_order'])
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('starts_at')
            ->get();
        $applicationTimelineIndex = $applicationScoped
            ? ($application?->current_stage?->timelineIndex() ?? 0)
            : null;

        return [
            'timeline' => $events->values()->map(
                function (TimelineCalendarEvent $event, int $index) use ($applicationScoped, $applicationTimelineIndex): array {
                    // Applicant milestones follow actual workflow progress; general calendars remain date-relative.
                    $isComplete = $applicationScoped
                        ? $index < $applicationTimelineIndex
                        : $event->ends_at->isPast();
                    $isCurrent = $applicationScoped
                        ? $index === $applicationTimelineIndex
                        : now()->between($event->starts_at, $event->ends_at);

                    return [
                        'label' => $event->label,
                        'starts_at' => $event->starts_at,
                        'ends_at' => $event->ends_at,
                        'date_label' => $event->starts_at->isSameDay($event->ends_at)
                            ? $event->starts_at->format('M j, Y')
                            : $event->starts_at->format('M j, Y').' - '.$event->ends_at->format('M j, Y'),
                        'is_complete' => $isComplete,
                        'is_current' => $isCurrent,
                    ];
                },
            ),
            'termLabel' => $events->first()?->term_label,
        ];
    }

    /**
     * Return the same shape as a configured requirement summary for empty applicant dashboards.
     *
     * @return array<string, mixed>
     */
    private function emptyRequirementSummary(): array
    {
        return [
            'items' => collect(),
            'mandatory_total' => 0,
            'completed_count' => 0,
            'pending_count' => 0,
            'rejected_count' => 0,
            'missing_count' => 0,
            'percentage' => 0,
            'ready' => false,
        ];
    }

    /** @return Collection<string, int> */
    private function groupedCounts(Builder $query, string $column): Collection
    {
        // One grouped aggregate replaces several per-status count queries on role dashboards.
        return (clone $query)
            ->select($column)
            ->selectRaw('COUNT(*) AS aggregate')
            ->groupBy($column)
            ->pluck('aggregate', $column)
            ->map(fn ($count): int => (int) $count);
    }

    /** @param array<int, string> $statuses */
    private function sumCounts(Collection $counts, array $statuses): int
    {
        return array_sum(array_map(
            fn (string $status): int => $counts->get($status, 0),
            $statuses,
        ));
    }
}
