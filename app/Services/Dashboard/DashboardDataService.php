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
use App\Services\Settings\AcademicTermResolver;
use App\Services\Settings\DeadlineStateResolver;
use App\Support\DeadlineProcessCatalog;
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
        private readonly AcademicTermResolver $terms,
        private readonly DeadlineStateResolver $deadlineStates,
    ) {}

    /** @return array<string, mixed> */
    public function applicant(User $user): array
    {
        // Select the newest non-archived applicant-owned application across current and historical term links.
        $activeApplicationQuery = ResearchApplication::query()
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
                'created_at',
                'updated_at',
            ])
            ->where('applicant_user_id', $user->id)
            ->where('application_status', '!=', ApplicationStatus::Archived->value);
        $activeApplication = $activeApplicationQuery
            ->with('adviser:id,name')
            ->latest('created_at')
            ->latest('id')
            ->first();

        // Reuse the same mandatory-requirement calculation used by final submission.
        $requirementSummary = $activeApplication
            ? $this->requirementService->summary($activeApplication)
            : $this->emptyRequirementSummary();
        $submissionWindow = $this->submissionWindow->status();
        $applicantDeadline = match (true) {
            $activeApplication
                && in_array($activeApplication->application_status, [
                    ApplicationStatus::ResultReleasedMinorRevision,
                    ApplicationStatus::ResultReleasedMajorRevision,
                    ApplicationStatus::RevisionWindowOpen,
                ], true) => $this->nextDeadline(UserRole::Applicant, ['revision-period']),
            ! $activeApplication || ! $activeApplication->isFormallySubmitted() => $this->submissionWindow
                ->dashboardPayload($submissionWindow),
            default => null,
        };

        // Share one deadline snapshot between the alert payload and final-submission availability.
        return [
            'activeApplication' => $activeApplication,
            'hasSubmittedApplication' => $activeApplication?->isFormallySubmitted() ?? false,
            'requirements' => $requirementSummary['items'],
            'requirementSummary' => $requirementSummary,
            'completedRequirementCount' => $requirementSummary['completed_count'],
            'deadline' => $applicantDeadline,
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
            'deadline' => $this->nextDeadline(UserRole::Adviser, ['adviser-endorsement']),
            ...$this->timelineData(),
        ];
    }

    /** @return array<string, mixed> */
    public function reviewer(User $user): array
    {
        $base = ReviewerAssignment::query()->where('reviewer_user_id', $user->id);
        $this->scopeAssignmentsToCurrentTerm($base);
        $activeValues = ReviewerAssignmentStatus::activeValues();
        $statusCounts = $this->groupedCounts($base, 'assignment_status');

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
            'deadline' => $this->nextDeadline(
                UserRole::Reviewer,
                $statusCounts->get(ReviewerAssignmentStatus::RevisionReview->value, 0) > 0
                    ? ['reviewing-revision-period']
                    : ['reviewer-submission'],
            ),
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
                ->take(5)
                ->map(fn (DeadlineConfiguration $deadline): array => $this->configuredDeadlinePayload($deadline)),
            ...$this->timelineData(),
        ];
    }

    /** @return array<string, mixed>|null */
    private function nextDeadline(UserRole $role, ?array $processKeys = null): ?array
    {
        $deadline = $this->availableDeadlines($role, $processKeys)->first();

        return $deadline ? $this->configuredDeadlinePayload($deadline) : null;
    }

    /**
     * @param  array<int, string>|null  $processKeys
     * @return Collection<int, DeadlineConfiguration>
     */
    private function availableDeadlines(UserRole $role, ?array $processKeys = null): Collection
    {
        $query = DeadlineConfiguration::query()
            ->where('is_active', true)
            ->where(function (Builder $query) use ($role): void {
                $query->whereNull('audience_role')->orWhere('audience_role', $role->value);
            });

        if ($processKeys !== null) {
            $query->where(function (Builder $processQuery) use ($processKeys): void {
                foreach ($processKeys as $index => $processKey) {
                    $method = $index === 0 ? 'where' : 'orWhere';
                    $processQuery->{$method}('deadline_key', 'like', "%{$processKey}");
                }
            });
        }

        $currentTerm = $this->terms->current();

        if ($currentTerm) {
            $query->where('academic_term_id', $currentTerm->id);
        } elseif ($this->terms->hasConfiguredTerms()) {
            $query->whereRaw('1 = 0');
        } else {
            $query->whereNull('academic_term_id');
        }

        return $query
            ->orderByDesc('priority')
            ->orderBy('due_at')
            ->get()
            ->filter(function (DeadlineConfiguration $deadline): bool {
                $state = $this->deadlineStates->status($deadline);

                return $state['open'] || $state['state'] === 'upcoming';
            })
            ->values();
    }

    /** @return array<string, mixed> */
    private function configuredDeadlinePayload(DeadlineConfiguration $deadline): array
    {
        $state = $this->deadlineStates->status($deadline);
        $definition = $this->deadlineDefinition($deadline);
        $message = match (true) {
            ($definition['exact_date'] ?? false) => 'Release date: '.$deadline->due_at->format('M j, Y \a\t g:i A').'.',
            $state['state'] === 'manually_open' => 'Manually open. Configured deadline: '.$deadline->due_at->format('M j, Y \a\t g:i A').'.',
            $state['state'] === 'upcoming' => 'Opens '.$deadline->starts_at?->format('M j, Y \a\t g:i A').'. Deadline: '.$deadline->due_at->format('M j, Y \a\t g:i A').'.',
            default => 'Deadline: '.$deadline->due_at->format('M j, Y \a\t g:i A').'.',
        };

        return [
            ...$this->deadlinePayload($deadline->title, $deadline->due_at),
            'state' => $state['state'],
            'message' => $message,
        ];
    }

    /** @return array<string, mixed>|null */
    private function deadlineDefinition(DeadlineConfiguration $deadline): ?array
    {
        $key = DeadlineProcessCatalog::keyForDeadlineKey($deadline->deadline_key);

        return $key ? DeadlineProcessCatalog::definitions()[$key] : null;
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

        $eventsQuery = TimelineCalendarEvent::query()
            ->select(['milestone_key', 'label', 'term_label', 'starts_at', 'ends_at', 'sort_order'])
            ->where('is_active', true);
        $currentTerm = $this->terms->current();

        if ($currentTerm) {
            $eventsQuery->where('academic_term_id', $currentTerm->id);
        } elseif ($this->terms->hasConfiguredTerms()) {
            return ['timeline' => collect(), 'termLabel' => AcademicTermResolver::FALLBACK_LABEL];
        } else {
            $eventsQuery->whereNull('academic_term_id');
        }

        $events = $eventsQuery
            ->orderBy('sort_order')
            ->orderBy('starts_at')
            ->get();
        $applicationTimelineIndex = $applicationScoped
            ? ($application?->timelineIndex() ?? 0)
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
            'termLabel' => $currentTerm?->label()
                ?? $events->first()?->term_label
                ?? AcademicTermResolver::FALLBACK_LABEL,
        ];
    }

    private function scopeAssignmentsToCurrentTerm(Builder $query): void
    {
        $currentTerm = $this->terms->current();

        if ($currentTerm) {
            $query->whereHas('researchApplication', fn (Builder $applications) => $applications
                ->where('academic_term_id', $currentTerm->id));
        } elseif ($this->terms->hasConfiguredTerms()) {
            $query->whereRaw('1 = 0');
        }
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
