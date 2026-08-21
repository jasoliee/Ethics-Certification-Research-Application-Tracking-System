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
    public function applicant(User $user, ?int $academicTermId = null): array
    {
        // Select the newest non-archived applicant-owned application across current and historical term links.
        $activeApplicationQuery = ResearchApplication::query()
            ->select([
                'id',
                'application_code',
                'academic_term_id',
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
            ->when($academicTermId !== null, fn (Builder $query) => $query->where('academic_term_id', $academicTermId))
            ->where('application_status', '!=', ApplicationStatus::Archived->value);
        $activeApplication = $activeApplicationQuery
            ->with(['adviser:id,name', 'academicTerm:id,semester,academic_year'])
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
                ], true) => $this->nextDeadline(UserRole::Applicant, ['revision-period'], $academicTermId),
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
    public function adviser(User $user, ?int $academicTermId = null): array
    {
        // Adviser dashboards exclude drafts, incomplete records, archived records, and other Advisers' assignments.
        $base = ResearchApplication::query()
            ->where('adviser_user_id', $user->id)
            ->when($academicTermId !== null, fn (Builder $query) => $query->where('academic_term_id', $academicTermId))
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
            'deadline' => $this->nextDeadline(UserRole::Adviser, ['adviser-endorsement'], $academicTermId),
            ...$this->timelineData(academicTermId: $academicTermId),
        ];
    }

    /** @return array<string, mixed> */
    public function reviewer(User $user, ?int $academicTermId = null): array
    {
        // Current assignment history, not academic-term cache state, is authoritative for Reviewer visibility.
        $base = ReviewerAssignment::query()
            ->current()
            ->latestCycleForReviewer()
            ->where('reviewer_user_id', $user->id)
            ->where('assignment_status', '!=', ReviewerAssignmentStatus::Superseded->value)
            ->whereHas('researchApplication', fn (Builder $applications) => $applications
                ->when($academicTermId !== null, fn (Builder $query) => $query->where('academic_term_id', $academicTermId))
                ->where('application_status', '!=', ApplicationStatus::Archived->value));
        $statusCounts = $this->groupedCounts($base, 'assignment_status');

        return [
            'counts' => [
                'pending' => $statusCounts->get(ReviewerAssignmentStatus::Pending->value, 0),
                'revision' => $statusCounts->get(ReviewerAssignmentStatus::RevisionReview->value, 0),
                'completed' => (clone $base)->completedFinalApproval()->count(),
            ],
            'assignments' => (clone $base)
                ->select([
                    'id',
                    'research_application_id',
                    'review_type',
                    'assignment_status',
                    'assigned_at',
                    'review_deadline_at',
                    'updated_at',
                ])
                ->with('researchApplication:id,application_code,research_title,submitted_at')
                ->latest('updated_at')
                ->latest('assigned_at')
                ->latest('id')
                ->limit(5)
                ->get(),
            'deadline' => $this->nextDeadline(
                UserRole::Reviewer,
                $statusCounts->get(ReviewerAssignmentStatus::RevisionReview->value, 0) > 0
                    ? ['reviewing-revision-period']
                    : ['reviewer-submission'],
                $academicTermId,
            ),
            ...$this->timelineData(academicTermId: $academicTermId),
        ];
    }

    /** @return array<string, mixed> */
    public function resLead(?int $academicTermId = null): array
    {
        $base = ResearchApplication::query()
            ->when($academicTermId !== null, fn (Builder $query) => $query->where('academic_term_id', $academicTermId));
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
                    'adviser_user_id',
                    'application_status',
                    'submitted_at',
                ])
                // Eager-load the displayed Adviser once for the bounded dashboard collection.
                ->with('adviser:id,name')
                ->whereIn('application_status', $administrativeStatuses)
                ->latest('submitted_at')
                ->latest('id')
                ->limit(5)
                ->get(),
            'deadlines' => $this->availableDeadlines(UserRole::ResLead, academicTermId: $academicTermId)
                ->take(5)
                ->map(fn (DeadlineConfiguration $deadline): array => $this->configuredDeadlinePayload($deadline)),
            ...$this->timelineData(academicTermId: $academicTermId),
        ];
    }

    /** @return array<string, mixed>|null */
    private function nextDeadline(
        UserRole $role,
        ?array $processKeys = null,
        ?int $academicTermId = null,
    ): ?array {
        $deadline = $this->availableDeadlines($role, $processKeys, $academicTermId)->first();

        return $deadline ? $this->configuredDeadlinePayload($deadline) : null;
    }

    /**
     * @param  array<int, string>|null  $processKeys
     * @return Collection<int, DeadlineConfiguration>
     */
    private function availableDeadlines(
        UserRole $role,
        ?array $processKeys = null,
        ?int $academicTermId = null,
    ): Collection {
        $query = DeadlineConfiguration::query()
            ->where('is_active', true)
            ->where('deadline_key', 'not like', '%result-release')
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

        $currentTerm = $academicTermId === null ? $this->terms->current() : null;

        if ($academicTermId !== null) {
            $query->where('academic_term_id', $academicTermId);
        } elseif ($currentTerm) {
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
        $message = match (true) {
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
        ?int $academicTermId = null,
    ): array {
        // Applicants without an application receive the explicit unavailable timeline state.
        if ($applicationScoped && ! $application) {
            return ['timeline' => collect(), 'termLabel' => null];
        }

        if ($applicationScoped && $application) {
            return $this->applicationTimelineData($application);
        }

        $eventsQuery = TimelineCalendarEvent::query()
            ->select(['milestone_key', 'label', 'term_label', 'starts_at', 'ends_at', 'sort_order'])
            ->where('is_active', true);
        $currentTerm = $academicTermId === null ? $this->terms->current() : null;

        if ($academicTermId !== null) {
            $eventsQuery->where('academic_term_id', $academicTermId);
        } elseif ($currentTerm) {
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

        return [
            'timeline' => $events->values()->map(
                function (TimelineCalendarEvent $event): array {
                    return [
                        'label' => $event->label,
                        'starts_at' => $event->starts_at,
                        'ends_at' => $event->ends_at,
                        'date_label' => $event->starts_at->isSameDay($event->ends_at)
                            ? $event->starts_at->format('M j, Y')
                            : $event->starts_at->format('M j, Y').' - '.$event->ends_at->format('M j, Y'),
                        'is_complete' => $event->ends_at->isPast(),
                        'is_current' => now()->between($event->starts_at, $event->ends_at),
                        'is_skipped' => false,
                    ];
                },
            ),
            'termLabel' => $academicTermId !== null
                ? ($events->first()?->term_label ?? AcademicTermResolver::FALLBACK_LABEL)
                : ($currentTerm?->label()
                    ?? $events->first()?->term_label
                    ?? AcademicTermResolver::FALLBACK_LABEL),
        ];
    }

    /** Build a stable, term-scoped Applicant timeline from canonical milestone keys. */
    private function applicationTimelineData(ResearchApplication $application): array
    {
        $definitions = collect(DeadlineProcessCatalog::definitions())->mapWithKeys(
            fn (array $definition): array => [$definition['timeline_key'] => $definition['timeline_label']],
        );
        $events = TimelineCalendarEvent::query()
            ->select(['milestone_key', 'label', 'term_label', 'starts_at', 'ends_at'])
            ->where('is_active', true)
            ->when(
                $application->academic_term_id !== null,
                fn (Builder $query) => $query->where('academic_term_id', $application->academic_term_id),
                fn (Builder $query) => $query->whereNull('academic_term_id'),
            )
            ->get()
            ->keyBy(fn (TimelineCalendarEvent $event): string => $this->timelineEventKey($event->milestone_key));
        $currentKey = $this->applicationCurrentMilestone($application->application_status);
        $order = $definitions->keys()->values();
        $currentIndex = $currentKey === null ? null : $order->search($currentKey, true);
        $terminalInitialReview = in_array($application->application_status, [
            ApplicationStatus::Exempted,
            ApplicationStatus::ReviewSubmittedPendingRelease,
            ApplicationStatus::ResultReleasedAccepted,
            ApplicationStatus::ResultReleasedDisapproved,
            ApplicationStatus::FeedbackRequired,
            ApplicationStatus::CertificateReleased,
            ApplicationStatus::Archived,
        ], true);

        $timeline = $definitions->map(function (string $label, string $key) use (
            $events,
            $order,
            $currentKey,
            $currentIndex,
            $application,
            $terminalInitialReview,
        ): array {
            $event = $events->get($key);
            $index = $order->search($key, true);
            $isSkipped = $terminalInitialReview && match ($application->application_status) {
                ApplicationStatus::Exempted => $index >= 3,
                default => $index >= 4,
            };
            $isComplete = ! $isSkipped && ($currentIndex !== null
                ? $index < $currentIndex
                : $terminalInitialReview && $index < ($application->application_status === ApplicationStatus::Exempted ? 3 : 4));

            return [
                'key' => $key,
                'label' => $event?->label ?? $label,
                'starts_at' => $event?->starts_at,
                'ends_at' => $event?->ends_at,
                'date_label' => ! $event
                    ? 'Not scheduled'
                    : ($event->starts_at->isSameDay($event->ends_at)
                        ? $event->starts_at->format('M j, Y')
                        : $event->starts_at->format('M j, Y').' - '.$event->ends_at->format('M j, Y')),
                'is_complete' => $isComplete,
                'is_current' => $key === $currentKey,
                'is_skipped' => $isSkipped,
            ];
        })->values();

        return [
            'timeline' => $timeline,
            'termLabel' => $application->academicTerm?->label()
                ?? $events->first()?->term_label
                ?? AcademicTermResolver::FALLBACK_LABEL,
        ];
    }

    private function timelineEventKey(string $milestoneKey): string
    {
        foreach (['reviewing-revision', 'res-screening', 'submission', 'endorsement', 'reviewing', 'revision'] as $key) {
            if ($milestoneKey === $key || str_ends_with($milestoneKey, '-'.$key)) {
                return $key;
            }
        }

        return $milestoneKey;
    }

    private function applicationCurrentMilestone(ApplicationStatus $status): ?string
    {
        return match ($status) {
            ApplicationStatus::Draft, ApplicationStatus::Incomplete => 'submission',
            ApplicationStatus::SubmittedToAdviser, ApplicationStatus::ReturnedByAdviser => 'endorsement',
            ApplicationStatus::AdviserEndorsed,
            ApplicationStatus::UnderResScreening,
            ApplicationStatus::AwaitingReviewerAssignment => 'res-screening',
            ApplicationStatus::UnderExpeditedReview, ApplicationStatus::UnderFullBoardReview => 'reviewing',
            ApplicationStatus::ResultReleasedMinorRevision,
            ApplicationStatus::ResultReleasedMajorRevision,
            ApplicationStatus::RevisionWindowOpen => 'revision',
            ApplicationStatus::RevisionSubmitted, ApplicationStatus::UnderReReview => 'reviewing-revision',
            default => null,
        };
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
