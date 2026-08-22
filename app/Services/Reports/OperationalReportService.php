<?php

namespace App\Services\Reports;

use App\Enums\AccountStatus;
use App\Enums\ApplicationRevisionStatus;
use App\Enums\ApplicationStatus;
use App\Enums\CertificateStatus;
use App\Enums\EndorsementStatus;
use App\Enums\ReviewConsensusStatus;
use App\Enums\ReviewDecision;
use App\Enums\ReviewerAssignmentStatus;
use App\Enums\ReviewType;
use App\Enums\UserRole;
use App\Models\AcademicTerm;
use App\Models\ApplicationDecisionRelease;
use App\Models\ApplicationRevision;
use App\Models\Certificate;
use App\Models\DeadlineConfiguration;
use App\Models\Endorsement;
use App\Models\ResearchApplication;
use App\Models\ReviewerAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Produces privacy-limited operational aggregates from authoritative ECRATS records.
 */
class OperationalReportService
{
    private const DUE_SOON_DAYS = 7;

    /** @param array<string, mixed> $filters */
    public function report(array $filters): array
    {
        $applications = $this->applicationQuery($filters);
        $applicationIds = (clone $applications)->pluck('id');

        return [
            'summary' => $this->summary($applications, $filters),
            'pipeline' => $this->pipeline($applications),
            'submission_trend' => $this->submissionTrend($applications, $filters),
            'classifications' => $this->classifications($applications),
            'decisions' => $this->decisions($applications),
            'turnaround' => $this->turnaround($applications),
            'reviewer_workload' => $this->reviewerWorkload($filters),
            'adviser_workload' => $this->adviserWorkload($filters),
            'certificate_operations' => $this->certificateOperations($filters),
            'action_required' => $this->actionRequired($filters),
            'certificate_follow_up' => $this->certificateFollowUp($filters),
            'data_quality' => $this->dataQuality($applications, $filters),
            'has_data' => $applicationIds->isNotEmpty(),
        ];
    }

    /** @param array<string, mixed> $filters */
    public function applicationQuery(array $filters): Builder
    {
        $query = ResearchApplication::query()->whereNotNull('submitted_at');

        return $this->applyApplicationFilters($query, $filters);
    }

    /** @param array<string, mixed> $filters */
    private function applyApplicationFilters(Builder $query, array $filters): Builder
    {
        return $query
            ->when(filled($filters['academic_term_id'] ?? null), fn (Builder $q) => $q
                ->where('academic_term_id', (int) $filters['academic_term_id']))
            ->when(filled($filters['date_from'] ?? null), fn (Builder $q) => $q
                ->whereDate('submitted_at', '>=', $filters['date_from']))
            ->when(filled($filters['date_to'] ?? null), fn (Builder $q) => $q
                ->whereDate('submitted_at', '<=', $filters['date_to']))
            ->when(filled($filters['research_type'] ?? null), fn (Builder $q) => $q
                ->where('research_type', $filters['research_type']))
            ->when(filled($filters['applicant_type'] ?? null), fn (Builder $q) => $q
                ->where('applicant_type', $filters['applicant_type']))
            ->when(filled($filters['review_type'] ?? null), fn (Builder $q) => $q
                ->where('review_type', $filters['review_type']))
            ->when(filled($filters['department'] ?? null), fn (Builder $q) => $q
                ->where('department', $filters['department']))
            ->when(filled($filters['institution'] ?? null), fn (Builder $q) => $q
                ->where('institution', $filters['institution']))
            ->when(filled($filters['application_status'] ?? null), fn (Builder $q) => $q
                ->where('application_status', $filters['application_status']));
    }

    /** @param array<string, mixed> $filters */
    private function relatedApplications(Builder $query, array $filters, string $relation = 'researchApplication'): Builder
    {
        return $query->whereHas($relation, fn (Builder $applications) => $this
            ->applyApplicationFilters($applications->whereNotNull('submitted_at'), $filters));
    }

    /** @param array<string, mixed> $filters */
    private function summary(Builder $applications, array $filters): array
    {
        $certificates = $this->relatedApplications(Certificate::query(), $filters);
        $dueItems = $this->dueAssignments($filters)->count() + $this->dueRevisions($filters)->count();

        return [
            'submitted' => (clone $applications)->count(),
            'screening' => (clone $applications)->whereIn('application_status', [
                ApplicationStatus::AdviserEndorsed->value,
                ApplicationStatus::UnderResScreening->value,
            ])->count(),
            'assignment' => (clone $applications)->where('application_status', ApplicationStatus::AwaitingReviewerAssignment->value)->count(),
            'review' => (clone $applications)->whereIn('application_status', ApplicationStatus::values(ApplicationStatus::underReview()))->count(),
            'decision_release' => (clone $applications)->where('application_status', ApplicationStatus::ReviewSubmittedPendingRelease->value)->count(),
            'certificate_release' => (clone $applications)->whereIn('application_status', [
                ApplicationStatus::ResultReleasedAccepted->value,
                ApplicationStatus::ForCertificateRelease->value,
            ])->count(),
            'certificates_released' => $this->fullyReleasedApplications(clone $applications)->count(),
            'due_items' => $dueItems,
            'certificate_records' => (clone $certificates)->count(),
        ];
    }

    private function pipeline(Builder $applications): array
    {
        $stages = [
            'Submitted' => fn (Builder $q) => $q,
            'Screening' => fn (Builder $q) => $q->whereIn('application_status', [ApplicationStatus::AdviserEndorsed->value, ApplicationStatus::UnderResScreening->value]),
            'Assignment' => fn (Builder $q) => $q->where('application_status', ApplicationStatus::AwaitingReviewerAssignment->value),
            'Under Review' => fn (Builder $q) => $q->whereIn('application_status', ApplicationStatus::values(ApplicationStatus::underReview())),
            'Decision Release' => fn (Builder $q) => $q->where('application_status', ApplicationStatus::ReviewSubmittedPendingRelease->value),
            'Certificate Release' => fn (Builder $q) => $q->whereIn('application_status', [ApplicationStatus::ResultReleasedAccepted->value, ApplicationStatus::ForCertificateRelease->value]),
            'Completed' => fn (Builder $q) => $this->fullyReleasedApplications($q),
        ];

        return collect($stages)->map(fn (callable $scope, string $label) => [
            'label' => $label,
            'count' => $scope(clone $applications)->count(),
        ])->values()->all();
    }

    /** @param array<string, mixed> $filters */
    private function submissionTrend(Builder $applications, array $filters): array
    {
        $dates = (clone $applications)->orderBy('submitted_at')->pluck('submitted_at')->map(fn ($date) => Carbon::parse($date));

        if ($dates->isEmpty()) {
            return ['interval' => 'week', 'rows' => []];
        }

        $from = filled($filters['date_from'] ?? null) ? Carbon::parse($filters['date_from']) : $dates->first();
        $to = filled($filters['date_to'] ?? null) ? Carbon::parse($filters['date_to']) : $dates->last();
        $interval = $from->diffInDays($to) <= 120 ? 'week' : 'month';
        $grouped = $dates->groupBy(fn (Carbon $date) => $interval === 'week'
            ? $date->copy()->startOfWeek()->format('Y-m-d')
            : $date->format('Y-m'));

        return [
            'interval' => $interval,
            'rows' => $grouped->map(fn (Collection $items, string $key) => [
                'label' => $interval === 'week'
                    ? Carbon::parse($key)->format('M j, Y')
                    : Carbon::createFromFormat('Y-m', $key)->format('M Y'),
                'count' => $items->count(),
            ])->values()->all(),
        ];
    }

    private function classifications(Builder $applications): array
    {
        return collect(ReviewType::cases())->map(fn (ReviewType $type) => [
            'label' => $type->label(),
            'count' => (clone $applications)->where('review_type', $type->value)->count(),
        ])->all();
    }

    private function decisions(Builder $applications): array
    {
        $latest = ApplicationDecisionRelease::query()
            ->whereHas('researchApplication', fn (Builder $q) => $q->whereIn('id', (clone $applications)->select('id')))
            ->whereNotExists(fn ($newer) => $newer
                ->selectRaw('1')
                ->from('application_decision_releases as newer_releases')
                ->whereColumn('newer_releases.research_application_id', 'application_decision_releases.research_application_id')
                ->whereColumn('newer_releases.id', '>', 'application_decision_releases.id'));

        return collect(ReviewDecision::cases())->map(fn (ReviewDecision $decision) => [
            'label' => $decision->label(),
            'count' => (clone $latest)->where('decision', $decision->value)->count(),
        ])->all();
    }

    private function turnaround(Builder $applications): array
    {
        $records = (clone $applications)->with([
            'screening:id,research_application_id,classified_at',
            'reviewerAssignments:id,research_application_id,assigned_at,submitted_at',
            'decisionReleases:id,research_application_id,released_at',
            'certificates:id,research_application_id,released_at',
        ])->get(['id', 'submitted_at']);
        $samples = [
            'Submission to screening' => [],
            'Screening to assignment' => [],
            'Assignment to review submission' => [],
            'Review submission to decision' => [],
            'Decision to certificate release' => [],
            'Submission to decision' => [],
        ];

        foreach ($records as $application) {
            $submitted = $application->submitted_at;
            $screened = $application->screening?->classified_at;
            $assigned = $application->reviewerAssignments->min('assigned_at');
            $reviewed = $application->reviewerAssignments->max('submitted_at');
            $decided = $application->decisionReleases->max('released_at');
            $certified = $application->certificates->max('released_at');
            $this->addDuration($samples['Submission to screening'], $submitted, $screened);
            $this->addDuration($samples['Screening to assignment'], $screened, $assigned);
            $this->addDuration($samples['Assignment to review submission'], $assigned, $reviewed);
            $this->addDuration($samples['Review submission to decision'], $reviewed, $decided);
            $this->addDuration($samples['Decision to certificate release'], $decided, $certified);
            $this->addDuration($samples['Submission to decision'], $submitted, $decided);
        }

        return collect($samples)->map(function (array $values, string $label): array {
            sort($values);
            $count = count($values);
            $median = $count === 0 ? null : ($count % 2 === 1
                ? $values[intdiv($count, 2)]
                : ($values[$count / 2 - 1] + $values[$count / 2]) / 2);

            return [
                'label' => $label,
                'average_days' => $count > 0 ? round(array_sum($values) / $count, 2) : null,
                'median_days' => $median === null ? null : round($median, 2),
                'sample_count' => $count,
            ];
        })->values()->all();
    }

    private function addDuration(array &$samples, mixed $from, mixed $to): void
    {
        if (! $from || ! $to) {
            return;
        }

        $seconds = Carbon::parse($from)->diffInSeconds(Carbon::parse($to), false);
        if ($seconds >= 0) {
            $samples[] = $seconds / 86400;
        }
    }

    /** @param array<string, mixed> $filters */
    private function reviewerWorkload(array $filters): Collection
    {
        $activeStatuses = ReviewerAssignmentStatus::activeValues();
        $placeholders = implode(', ', array_fill(0, count($activeStatuses), '?'));
        $now = now();
        $dueSoon = $now->copy()->addDays(self::DUE_SOON_DAYS);
        $aggregates = $this->relatedApplications(
            ReviewerAssignment::query()->whereNull('superseded_at'),
            $filters,
        )
            ->select('reviewer_user_id')
            ->selectRaw("SUM(CASE WHEN assignment_status IN ({$placeholders}) THEN 1 ELSE 0 END) AS active_count", $activeStatuses)
            ->selectRaw('SUM(CASE WHEN assignment_status = ? THEN 1 ELSE 0 END) AS completed_count', [ReviewerAssignmentStatus::DecisionSubmitted->value])
            ->selectRaw("SUM(CASE WHEN assignment_status IN ({$placeholders}) AND review_deadline_at < ? THEN 1 ELSE 0 END) AS overdue_count", [...$activeStatuses, $now])
            ->selectRaw("SUM(CASE WHEN assignment_status IN ({$placeholders}) AND review_deadline_at BETWEEN ? AND ? THEN 1 ELSE 0 END) AS due_soon_count", [...$activeStatuses, $now, $dueSoon])
            ->groupBy('reviewer_user_id')
            ->get()
            ->keyBy('reviewer_user_id');

        return User::query()->reviewerEnabled()->where('account_status', AccountStatus::Active->value)
            ->get(['id', 'name', 'reviewer_capacity'])
            ->map(function (User $reviewer) use ($aggregates): array {
                $aggregate = $aggregates->get($reviewer->id);
                $active = (int) ($aggregate?->active_count ?? 0);
                $capacity = max(0, (int) $reviewer->reviewer_capacity);

                return [
                    'reviewer' => $reviewer,
                    'active' => $active,
                    'capacity' => $capacity,
                    'remaining' => max(0, $capacity - $active),
                    'completed' => (int) ($aggregate?->completed_count ?? 0),
                    'overdue' => (int) ($aggregate?->overdue_count ?? 0),
                    'due_soon' => (int) ($aggregate?->due_soon_count ?? 0),
                ];
            })->sortByDesc('active')->values();
    }

    /** @param array<string, mixed> $filters */
    private function adviserWorkload(array $filters): Collection
    {
        $deadline = $this->endorsementDeadline($filters);
        $applicationAggregates = $this->applyApplicationFilters(
            ResearchApplication::query()->whereNotNull('submitted_at'),
            $filters,
        )
            ->select('adviser_user_id')
            ->selectRaw('COUNT(*) AS received_count')
            ->selectRaw('SUM(CASE WHEN application_status = ? THEN 1 ELSE 0 END) AS awaiting_count', [ApplicationStatus::SubmittedToAdviser->value])
            ->groupBy('adviser_user_id')
            ->get()
            ->keyBy('adviser_user_id');
        $endorsementAggregates = $this->relatedApplications(
            Endorsement::query()->where('endorsement_status', EndorsementStatus::Endorsed->value),
            $filters,
        )
            ->select('adviser_user_id')
            ->selectRaw('COUNT(DISTINCT research_application_id) AS endorsed_count')
            ->groupBy('adviser_user_id')
            ->get()
            ->keyBy('adviser_user_id');

        return User::query()->where('role', UserRole::Adviser->value)->where('account_status', AccountStatus::Active->value)
            ->get(['id', 'name', 'expected_endorsement_count'])
            ->map(function (User $adviser) use ($applicationAggregates, $endorsementAggregates, $deadline): array {
                $applicationAggregate = $applicationAggregates->get($adviser->id);
                $received = (int) ($applicationAggregate?->received_count ?? 0);
                $endorsed = (int) ($endorsementAggregates->get($adviser->id)?->endorsed_count ?? 0);
                $awaiting = (int) ($applicationAggregate?->awaiting_count ?? 0);

                return [
                    'adviser' => $adviser,
                    'expected' => max(0, (int) $adviser->expected_endorsement_count),
                    'received' => $received,
                    'endorsed' => $endorsed,
                    'awaiting' => $awaiting,
                    'delayed' => $deadline?->isPast() ? $awaiting : 0,
                ];
            })->sortByDesc('awaiting')->values();
    }

    /** @param array<string, mixed> $filters */
    private function certificateOperations(array $filters): array
    {
        $query = $this->relatedApplications(Certificate::query(), $filters);
        $notGenerated = $this->applicationQuery($filters)
            ->where('application_status', ApplicationStatus::ForCertificateRelease->value)
            ->whereDoesntHave('certificates')
            ->count();

        return [
            ['label' => 'Pending generation/release', 'count' => $notGenerated + (clone $query)->whereIn('status', [CertificateStatus::PendingRelease->value, CertificateStatus::GenerationFailed->value])->count()],
            ['label' => 'Released', 'count' => (clone $query)->where('status', CertificateStatus::Released->value)->count()],
            ['label' => 'Claimed', 'count' => (clone $query)->where('status', CertificateStatus::Claimed->value)->count()],
            ['label' => 'Unclaimed / ageing', 'count' => (clone $query)->where('status', CertificateStatus::Released->value)->whereNull('claimed_at')->count()],
            ['label' => 'Unclaimed over 30 days', 'count' => (clone $query)->where('status', CertificateStatus::Released->value)->whereNull('claimed_at')->where('released_at', '<=', now()->subDays(30))->count()],
        ];
    }

    /** @param array<string, mixed> $filters */
    private function actionRequired(array $filters): Collection
    {
        $assignments = $this->dueAssignments($filters)
            ->with('researchApplication:id,application_code,current_stage')
            ->get()
            ->map(fn (ReviewerAssignment $assignment) => [
                'application' => $assignment->researchApplication,
                'stage' => $assignment->researchApplication?->current_stage?->label() ?? 'Ethics Review',
                'deadline' => $assignment->review_deadline_at,
                'responsible_role' => 'Reviewer',
            ]);
        $revisions = $this->dueRevisions($filters)
            ->with('researchApplication:id,application_code,current_stage')
            ->get()
            ->map(fn (ApplicationRevision $revision) => [
                'application' => $revision->researchApplication,
                'stage' => $revision->researchApplication?->current_stage?->label() ?? 'Revision',
                'deadline' => $revision->due_at,
                'responsible_role' => 'Applicant',
            ]);

        return $assignments->concat($revisions)->sortBy('deadline')->take(25)->values();
    }

    /** @param array<string, mixed> $filters */
    private function dueAssignments(array $filters): Builder
    {
        return $this->relatedApplications(
            ReviewerAssignment::query()->whereNull('superseded_at')
                ->whereIn('assignment_status', ReviewerAssignmentStatus::activeValues())
                ->whereNotNull('review_deadline_at')
                ->where('review_deadline_at', '<=', now()->addDays(self::DUE_SOON_DAYS)),
            $filters,
        );
    }

    /** @param array<string, mixed> $filters */
    private function dueRevisions(array $filters): Builder
    {
        return $this->relatedApplications(
            ApplicationRevision::query()->where('status', ApplicationRevisionStatus::PendingUploads->value)
                ->where('due_at', '<=', now()->addDays(self::DUE_SOON_DAYS)),
            $filters,
        );
    }

    /** @param array<string, mixed> $filters */
    private function certificateFollowUp(array $filters): Collection
    {
        return (clone $this->applicationQuery($filters))
            ->where(fn (Builder $applications) => $applications
                ->whereHas('certificates')
                ->orWhereIn('application_status', [
                    ApplicationStatus::ResultReleasedAccepted->value,
                    ApplicationStatus::ForCertificateRelease->value,
                ]))
            ->with(['certificates:id,research_application_id,status,released_at,claimed_at'])
            ->withCount('certificateRecipients')
            ->orderBy('application_code')
            ->limit(50)
            ->get(['id', 'application_code'])
            ->map(function (ResearchApplication $application): array {
                $certificates = $application->certificates;
                $recipientCount = max((int) $application->certificate_recipients_count, $certificates->count());
                $releasedAt = $certificates->whereNotNull('released_at')->min('released_at');
                $claimed = $certificates->where('status', CertificateStatus::Claimed)->count();
                $status = $certificates->contains(fn (Certificate $certificate) => $certificate->status === CertificateStatus::GenerationFailed)
                    ? 'Generation Failed'
                    : ($certificates->count() < $recipientCount
                        ? 'Pending Generation'
                        : ($certificates->isNotEmpty() && $certificates->every(fn (Certificate $certificate) => $certificate->status === CertificateStatus::Claimed)
                        ? 'Claimed'
                        : ($certificates->isNotEmpty() && $certificates->every(fn (Certificate $certificate) => in_array($certificate->status, [CertificateStatus::Released, CertificateStatus::Claimed], true))
                            ? 'Released'
                            : ($certificates->contains(fn (Certificate $certificate) => in_array($certificate->status, [CertificateStatus::Released, CertificateStatus::Claimed], true)) ? 'Partial Release' : 'Pending'))));

                return [
                    'application' => $application,
                    'recipient_count' => $recipientCount,
                    'status' => $status,
                    'released_at' => $releasedAt,
                    'claim_status' => "{$claimed} of {$recipientCount} claimed",
                    'ageing_days' => $releasedAt
                        ? intdiv(max(0, Carbon::parse($releasedAt)->diffInSeconds(now(), false)), 86400)
                        : null,
                ];
            });
    }

    private function fullyReleasedApplications(Builder $applications): Builder
    {
        return $applications
            ->whereHas('certificates')
            ->whereDoesntHave('certificates', fn (Builder $certificates) => $certificates
                ->whereNotIn('status', [CertificateStatus::Released->value, CertificateStatus::Claimed->value]))
            ->whereRaw('(SELECT COUNT(*) FROM certificates WHERE certificates.research_application_id = research_applications.id) = (SELECT COUNT(*) FROM application_certificate_recipients WHERE application_certificate_recipients.research_application_id = research_applications.id)');
    }

    /** @param array<string, mixed> $filters */
    private function dataQuality(Builder $applications, array $filters): array
    {
        $termId = filled($filters['academic_term_id'] ?? null) ? (int) $filters['academic_term_id'] : null;
        $deadlineTermId = $termId ?? AcademicTerm::query()->current()->value('id');
        $missingDeadline = $deadlineTermId
            ? max(0, 6 - DeadlineConfiguration::query()->where('academic_term_id', $deadlineTermId)->distinct()->count('deadline_key'))
            : 6;

        return [
            ['label' => 'Missing active term', 'count' => AcademicTerm::query()->current()->exists() ? 0 : 1],
            ['label' => 'Missing deadline configuration', 'count' => $missingDeadline],
            ['label' => 'Unassigned applications', 'count' => (clone $applications)->where('application_status', ApplicationStatus::AwaitingReviewerAssignment->value)->whereDoesntHave('reviewerAssignments', fn (Builder $q) => $q->whereNull('superseded_at'))->count()],
            ['label' => 'Failed certificate generation', 'count' => $this->relatedApplications(Certificate::query()->where('status', CertificateStatus::GenerationFailed->value), $filters)->count()],
            ['label' => 'Unresolved conflicts', 'count' => (clone $applications)->where('review_consensus_status', ReviewConsensusStatus::Conflicted->value)->count()],
        ];
    }

    /** @param array<string, mixed> $filters */
    private function endorsementDeadline(array $filters): ?Carbon
    {
        $dueAt = DeadlineConfiguration::query()
            ->when(filled($filters['academic_term_id'] ?? null), fn (Builder $q) => $q->where('academic_term_id', (int) $filters['academic_term_id']))
            ->where('deadline_key', 'adviser-endorsement')
            ->where('is_active', true)
            ->latest('due_at')
            ->value('due_at');

        return $dueAt ? Carbon::parse($dueAt) : null;
    }
}
