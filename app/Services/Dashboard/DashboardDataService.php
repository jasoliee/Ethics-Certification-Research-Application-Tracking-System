<?php

namespace App\Services\Dashboard;

use App\Enums\ApplicationStatus;
use App\Enums\RequirementStatus;
use App\Enums\ReviewerAssignmentStatus;
use App\Enums\UserRole;
use App\Models\DeadlineConfiguration;
use App\Models\DocumentRequirement;
use App\Models\ResearchApplication;
use App\Models\ReviewerAssignment;
use App\Models\TimelineCalendarEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class DashboardDataService
{
    /** @return array<string, mixed> */
    public function applicant(User $user): array
    {
        $activeApplication = ResearchApplication::query()
            ->select([
                'id',
                'application_code',
                'applicant_user_id',
                'adviser_user_id',
                'research_title',
                'application_type',
                'application_status',
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

        $requirements = collect();

        if ($activeApplication) {
            $requirements = DocumentRequirement::query()
                ->select(['id', 'code', 'name', 'sort_order'])
                ->where('is_active', true)
                ->with(['applicationDocuments' => fn ($query) => $query
                    ->select([
                        'id',
                        'research_application_id',
                        'document_requirement_id',
                        'original_file_name',
                        'document_version',
                        'validation_status',
                    ])
                    ->where('research_application_id', $activeApplication->id)
                    ->where('is_current', true)
                    ->latest('document_version')])
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get()
                ->map(function (DocumentRequirement $requirement): array {
                    $document = $requirement->applicationDocuments->first();
                    $status = $document?->validation_status ?? RequirementStatus::Pending;

                    return [
                        'code' => $requirement->code,
                        'name' => $requirement->name,
                        'status' => $status,
                        'file_name' => $document?->original_file_name,
                    ];
                });
        }

        return [
            'activeApplication' => $activeApplication,
            'requirements' => $requirements,
            'completedRequirementCount' => $requirements
                ->where('status', RequirementStatus::Completed)
                ->count(),
            'deadline' => $this->nextDeadline(UserRole::Applicant),
            ...$this->timelineData(),
        ];
    }

    /** @return array<string, mixed> */
    public function adviser(User $user): array
    {
        $base = ResearchApplication::query()->where('adviser_user_id', $user->id);
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

    /** @return array{timeline: Collection<int, array<string, mixed>>, termLabel: string|null} */
    private function timelineData(): array
    {
        $events = TimelineCalendarEvent::query()
            ->select(['label', 'term_label', 'starts_at', 'ends_at', 'sort_order'])
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('starts_at')
            ->get();

        return [
            'timeline' => $events->map(fn (TimelineCalendarEvent $event): array => [
                'label' => $event->label,
                'starts_at' => $event->starts_at,
                'ends_at' => $event->ends_at,
                'date_label' => $event->starts_at->isSameDay($event->ends_at)
                    ? $event->starts_at->format('M j, Y')
                    : $event->starts_at->format('M j, Y').' - '.$event->ends_at->format('M j, Y'),
                'is_complete' => $event->ends_at->isPast(),
                'is_current' => now()->between($event->starts_at, $event->ends_at),
            ]),
            'termLabel' => $events->first()?->term_label,
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
