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
use App\Services\Privacy\ApplicationIdentityVisibilityService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Produces privacy-limited operational aggregates from authoritative ECRATS records.
 */
class OperationalReportService
{
    private const DUE_SOON_DAYS = 7;

    public const REVIEWER_CAPACITY = 30;

    public function __construct(
        private readonly ApplicationIdentityVisibilityService $identityVisibility,
    ) {}

    /** @param array<string, mixed> $filters */
    public function report(array $filters, ?int $applicationPageSize = null): array
    {
        $applications = $this->applicationQuery($filters);

        return [
            'summary' => $this->summary($applications, $filters),
            'applications' => $this->applicationRows($applications, $applicationPageSize),
            'applicant_certification' => $this->applicantCertification($filters),
            'institute_summary' => $this->instituteSummary($applications, $filters),
            'adviser_reviewer_summary' => $this->adviserReviewerSummary($filters),
            'pipeline' => $this->pipeline($applications),
            'classifications' => $this->classifications($applications),
            'reviewer_workload' => $this->reviewerWorkload($filters),
            'adviser_workload' => $this->adviserWorkload($filters),
            'has_data' => (clone $applications)->exists(),
        ];
    }

    /** @param array<string, mixed> $filters */
    public function allApplicationRows(array $filters): Collection
    {
        return $this->applicationRows($this->applicationQuery($filters));
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
            ->when(filled($filters['institute'] ?? null), fn (Builder $q) => $q
                ->where('institution', $filters['institute']))
            ->when(filled($filters['application_status'] ?? null), fn (Builder $q) => $q
                ->where('application_status', $filters['application_status']))
            ->when(filled($filters['q'] ?? null), function (Builder $q) use ($filters): void {
                $search = '%'.trim((string) $filters['q']).'%';
                // Applicant identity is intentionally excluded from this pre-release search.
                $q->where(fn (Builder $matches) => $matches
                    ->whereLike('application_code', $search)
                    ->orWhereLike('research_title', $search));
            })
            ->when(filled($filters['certificate_status'] ?? null), function (Builder $q) use ($filters): void {
                $status = (string) $filters['certificate_status'];

                if ($status === 'unclaimed') {
                    $q->whereHas('certificates', fn (Builder $certificates) => $certificates
                        ->where('status', CertificateStatus::Released->value)
                        ->whereNull('claimed_at'));

                    return;
                }

                if ($status === 'issued') {
                    $q->whereHas('certificates', fn (Builder $certificates) => $certificates
                        ->whereIn('status', [CertificateStatus::Released->value, CertificateStatus::Claimed->value]));

                    return;
                }

                $q->whereHas('certificates', fn (Builder $certificates) => $certificates
                    ->where('status', $status));
            });
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

        return [
            'unique_applicants' => (clone $applications)->whereNotNull('applicant_user_id')->distinct()->count('applicant_user_id'),
            'submitted' => (clone $applications)->count(),
            'not_submitted' => $this->notSubmittedApplicants($filters),
            'failed' => (clone $applications)->where('application_status', ApplicationStatus::Failed->value)->count(),
            'certificates_claimed' => (clone $certificates)
                ->whereNotNull('applicant_user_id')
                ->where('status', CertificateStatus::Claimed->value)
                ->distinct()
                ->count('applicant_user_id'),
            'certificates_unclaimed' => (clone $certificates)
                ->whereNotNull('applicant_user_id')
                ->where('status', CertificateStatus::Released->value)
                ->whereNull('claimed_at')
                ->distinct()
                ->count('applicant_user_id'),
        ];
    }

    /** @param array<string, mixed> $filters */
    private function notSubmittedApplicants(array $filters): int
    {
        return User::query()
            ->where('role', UserRole::Applicant->value)
            ->where('account_status', AccountStatus::Active->value)
            ->when(filled($filters['institute'] ?? null), fn (Builder $users) => $users
                ->where('institution', $filters['institute']))
            ->when(filled($filters['applicant_type'] ?? null), fn (Builder $users) => $users
                ->where('applicant_type', $filters['applicant_type']))
            ->whereDoesntHave('researchApplications', fn (Builder $applications) => $this
                ->applyApplicationFilters($applications->whereNotNull('submitted_at'), $filters))
            ->count();
    }

    private function applicationRows(Builder $applications, ?int $pageSize = null): Collection|LengthAwarePaginator
    {
        $query = (clone $applications)
            ->with(['certificates:id,research_application_id,status,released_at,claimed_at'])
            ->latest('submitted_at')
            ->latest('id');
        $columns = [
            'id',
            'application_code',
            'research_title',
            'institution',
            'application_status',
            'review_type',
            'submitted_at',
        ];

        if ($pageSize !== null) {
            return $query
                ->paginate($pageSize, $columns, 'applications_page')
                ->withQueryString()
                ->through(fn (ResearchApplication $application): array => $this->applicationRow($application));
        }

        return $query->get($columns)->map(
            fn (ResearchApplication $application): array => $this->applicationRow($application),
        );
    }

    /** @return array{application: ResearchApplication, certificate_status: string} */
    private function applicationRow(ResearchApplication $application): array
    {
        $certificates = $application->certificates;
        $certificateStatus = $certificates->isEmpty()
            ? 'Not issued'
            : ($certificates->every(fn (Certificate $certificate) => $certificate->status === CertificateStatus::Claimed)
                ? 'Claimed'
                : ($certificates->every(fn (Certificate $certificate) => in_array($certificate->status, [CertificateStatus::Released, CertificateStatus::Claimed], true))
                    ? 'Issued / unclaimed'
                    : ($certificates->contains(fn (Certificate $certificate) => $certificate->status === CertificateStatus::GenerationFailed)
                        ? 'Generation failed'
                        : 'Pending release')));

        return [
            'application' => $application,
            'certificate_status' => $certificateStatus,
        ];
    }

    /** @param array<string, mixed> $filters */
    private function applicantCertification(array $filters): Collection
    {
        return $this->identityVisibility
            ->visibleApplications($this->applicationQuery($filters))
            ->with([
                'applicant:id,name,institutional_identifier,institution',
                'certificates:id,research_application_id,recipient_name,certificate_number,status,current_certificate_version_id,released_at,claimed_at',
                'certificates.currentVersion:id,certificate_id,status,stored_file_path,original_file_name',
            ])
            ->orderBy('application_code')
            ->get(['id', 'applicant_user_id', 'application_code', 'institution'])
            ->flatMap(function (ResearchApplication $application): Collection {
                return $application->certificates
                    ->sortBy([['recipient_name', 'asc'], ['id', 'asc']])
                    ->map(function (Certificate $certificate) use ($application): array {
                        $releasedAt = $certificate->released_at;

                        return [
                            'applicant' => $application->applicant,
                            'application' => $application,
                            'certificate' => $certificate,
                            'released_at' => $releasedAt,
                            'ageing_days' => $releasedAt
                                ? intdiv(max(0, $releasedAt->diffInSeconds(now(), false)), 86400)
                                : null,
                        ];
                    });
            })
            ->values();
    }

    /** @param array<string, mixed> $filters */
    private function instituteSummary(Builder $applications, array $filters): Collection
    {
        $records = (clone $applications)->get([
            'id',
            'applicant_user_id',
            'institution',
            'application_status',
        ]);
        $applicationIds = $records->pluck('id');
        $certificates = Certificate::query()
            ->whereIn('research_application_id', $applicationIds)
            ->get(['research_application_id', 'status', 'claimed_at'])
            ->groupBy('research_application_id');
        $activeApplicants = User::query()
            ->where('role', UserRole::Applicant->value)
            ->where('account_status', AccountStatus::Active->value)
            ->when(filled($filters['institute'] ?? null), fn (Builder $users) => $users
                ->where('institution', $filters['institute']))
            ->get(['id', 'institution'])
            ->groupBy(fn (User $user): string => trim((string) $user->institution));

        $applicationGroups = $records
            ->groupBy(fn (ResearchApplication $application): string => trim((string) $application->institution) ?: 'Not specified');

        return $applicationGroups->keys()
            ->merge($activeApplicants->keys())
            ->filter()
            ->unique()
            ->map(function (string $institute) use ($applicationGroups, $certificates, $activeApplicants): array {
                $instituteApplications = $applicationGroups->get($institute, collect());
                $submittedApplicantIds = $instituteApplications->pluck('applicant_user_id')->filter()->unique();
                $applicantAccounts = $activeApplicants->get($institute, collect());
                $claimedApplicantIds = $instituteApplications
                    ->filter(fn (ResearchApplication $application): bool => $certificates
                        ->get($application->id, collect())
                        ->contains(fn (Certificate $certificate): bool => $certificate->status === CertificateStatus::Claimed))
                    ->pluck('applicant_user_id')
                    ->filter()
                    ->unique();
                $unclaimedApplicantIds = $instituteApplications
                    ->filter(fn (ResearchApplication $application): bool => $certificates
                        ->get($application->id, collect())
                        ->contains(fn (Certificate $certificate): bool => $certificate->status === CertificateStatus::Released
                            && $certificate->claimed_at === null))
                    ->pluck('applicant_user_id')
                    ->filter()
                    ->unique();

                return [
                    'institute' => $institute,
                    'unique_applicants' => $submittedApplicantIds->count(),
                    'submitted' => $instituteApplications->count(),
                    'not_submitted' => $applicantAccounts->pluck('id')->diff($submittedApplicantIds)->count(),
                    'failed' => $instituteApplications->where('application_status', ApplicationStatus::Failed)->count(),
                    'claimed' => $claimedApplicantIds->count(),
                    'unclaimed' => $unclaimedApplicantIds->count(),
                ];
            })
            ->sortBy('institute')
            ->values();
    }

    /** @param array<string, mixed> $filters */
    private function adviserReviewerSummary(array $filters): Collection
    {
        return User::query()
            ->where('role', UserRole::Adviser->value)
            ->where('account_status', AccountStatus::Active->value)
            ->when(filled($filters['institute'] ?? null), fn (Builder $users) => $users
                ->where('institution', $filters['institute']))
            ->get(['id', 'institution', 'reviewer_enabled'])
            ->groupBy(fn (User $user): string => trim((string) $user->institution) ?: 'Not specified')
            ->map(fn (Collection $accounts, string $institute): array => [
                'institute' => $institute,
                'advisers' => $accounts->count(),
                'reviewers' => $accounts->where('reviewer_enabled', true)->count(),
            ])
            ->sortBy('institute')
            ->values();
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
        $now = now();
        $dueSoon = $now->copy()->addDays(self::DUE_SOON_DAYS);
        $currentTermId = AcademicTerm::query()->current()->value('id');
        $workloadFilters = $filters;
        $workloadFilters['academic_term_id'] = $currentTermId;
        $assignments = $currentTermId
            ? $this->relatedApplications(
                ReviewerAssignment::query()->whereNull('superseded_at'),
                $workloadFilters,
            )
                ->with('researchApplication:id,review_type,institution')
                ->get([
                    'id',
                    'research_application_id',
                    'reviewer_user_id',
                    'assignment_status',
                    'review_deadline_at',
                ])
                ->groupBy('reviewer_user_id')
            : collect();

        return User::query()->reviewerEnabled()->where('account_status', AccountStatus::Active->value)
            ->when(filled($filters['institute'] ?? null), fn (Builder $users) => $users
                ->where('institution', $filters['institute']))
            ->get(['id', 'name', 'institution'])
            ->map(function (User $reviewer) use ($assignments, $activeStatuses, $now, $dueSoon): array {
                $records = $assignments->get($reviewer->id, collect());
                $active = $records->filter(fn (ReviewerAssignment $assignment) => in_array($assignment->assignment_status->value, $activeStatuses, true));
                $completed = $records->where('assignment_status', ReviewerAssignmentStatus::DecisionSubmitted);

                return [
                    'reviewer' => $reviewer,
                    'institute' => $reviewer->institution ?: 'Not specified',
                    'expedited' => $records->filter(fn (ReviewerAssignment $assignment) => $assignment->researchApplication?->review_type === ReviewType::Expedited->value)->count(),
                    'full_board' => $records->filter(fn (ReviewerAssignment $assignment) => $assignment->researchApplication?->review_type === ReviewType::FullBoard->value)->count(),
                    'total' => $records->count(),
                    'active' => $active->count(),
                    'capacity' => self::REVIEWER_CAPACITY,
                    'remaining' => max(0, self::REVIEWER_CAPACITY - $records->count()),
                    'completed' => $completed->count(),
                    'pending' => $records->count() - $completed->count(),
                    'overdue' => $active->filter(fn (ReviewerAssignment $assignment) => $assignment->review_deadline_at?->lt($now))->count(),
                    'due_soon' => $active->filter(fn (ReviewerAssignment $assignment) => $assignment->review_deadline_at?->between($now, $dueSoon))->count(),
                ];
            })->sortByDesc('active')->values();
    }

    /** @param array<string, mixed> $filters */
    private function adviserWorkload(array $filters): Collection
    {
        $applications = $this->applyApplicationFilters(
            ResearchApplication::query()->whereNotNull('submitted_at'),
            $filters,
        )
            ->get(['id', 'adviser_user_id', 'applicant_user_id', 'application_status'])
            ->groupBy('adviser_user_id');
        $endorsedApplications = $this->relatedApplications(
            Endorsement::query()->where('endorsement_status', EndorsementStatus::Endorsed->value),
            $filters,
        )
            ->with('researchApplication:id,applicant_user_id')
            ->get(['id', 'adviser_user_id', 'research_application_id'])
            ->groupBy('adviser_user_id');

        return User::query()->where('role', UserRole::Adviser->value)->where('account_status', AccountStatus::Active->value)
            ->when(filled($filters['institute'] ?? null), fn (Builder $users) => $users
                ->where('institution', $filters['institute']))
            ->get(['id', 'name', 'institution', 'expected_endorsement_count'])
            ->map(function (User $adviser) use ($applications, $endorsedApplications): array {
                $records = $applications->get($adviser->id, collect());
                $endorsedApplicantIds = $endorsedApplications->get($adviser->id, collect())
                    ->pluck('researchApplication.applicant_user_id')
                    ->filter()
                    ->unique();
                $receivedApplicantIds = $records->pluck('applicant_user_id')->filter()->unique();
                $awaitingApplicantIds = $records
                    ->where('application_status', ApplicationStatus::SubmittedToAdviser)
                    ->pluck('applicant_user_id')
                    ->filter()
                    ->unique()
                    ->diff($endorsedApplicantIds);
                $expected = max(0, (int) $adviser->expected_endorsement_count);
                $endorsed = $endorsedApplicantIds->count();
                $awaiting = $awaitingApplicantIds->count();

                return [
                    'adviser' => $adviser,
                    'institute' => $adviser->institution ?: 'Not specified',
                    'expected' => $expected,
                    'received' => $receivedApplicantIds->count(),
                    'endorsed' => $endorsed,
                    'awaiting' => $awaiting,
                    'not_received' => max(0, $expected - $endorsed - $awaiting),
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
        return $this->identityVisibility->visibleApplications($applications);
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
