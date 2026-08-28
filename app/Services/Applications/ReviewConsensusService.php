<?php

namespace App\Services\Applications;

use App\Enums\AccountStatus;
use App\Enums\ApplicationRevisionStatus;
use App\Enums\ApplicationStage;
use App\Enums\ApplicationStatus;
use App\Enums\ReviewConsensusStatus;
use App\Enums\ReviewDecision;
use App\Enums\ReviewerAssignmentStatus;
use App\Enums\ReviewSubmissionStatus;
use App\Enums\ReviewType;
use App\Enums\UserRole;
use App\Models\ApplicationRevision;
use App\Models\ResearchApplication;
use App\Models\ReviewerAssignment;
use App\Models\ReviewSubmission;
use App\Models\ReviewSubmissionVersion;
use App\Models\User;
use App\Notifications\DashboardUpdateNotification;
use App\Services\AuditLogService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReviewConsensusService
{
    public function __construct(
        private readonly ReviewSubmissionVersionService $versions,
        private readonly AuditLogService $auditLog,
    ) {}

    public function evaluate(ResearchApplication $application): ResearchApplication
    {
        return DB::transaction(function () use ($application): ResearchApplication {
            $locked = ResearchApplication::query()->whereKey($application->id)->lockForUpdate()->firstOrFail();

            return $this->evaluateLocked($locked);
        }, 3);
    }

    /**
     * The caller must lock the application before invoking this method.
     */
    public function evaluateLocked(ResearchApplication $application): ResearchApplication
    {
        $cycle = max(0, ((int) $application->current_revision_cycle) - 1);
        $reviewType = $cycle === 0 ? 'initial_review' : 'revision_review';
        $classification = ReviewType::tryFrom((string) $application->review_type);
        $assignments = ReviewerAssignment::query()
            ->current()
            ->where('research_application_id', $application->id)
            ->where('review_cycle', $cycle)
            ->where('review_type', $reviewType)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
        $assignments->load('reviewSubmission.currentVersion');
        // Historical/imported fixtures may predate stored classification. Their
        // already-created current assignment set is the only safe recoverable count.
        $requiredCount = $classification?->reviewerCount() ?? $assignments->count();

        foreach ($assignments as $assignment) {
            $submission = $assignment->reviewSubmission;
            if ($submission?->status === ReviewSubmissionStatus::Submitted && ! $submission->currentVersion) {
                $submission->setRelation('currentVersion', $this->versions->ensureCurrent($submission));
            }
            if ($submission?->status === ReviewSubmissionStatus::Submitted
                && $submission->has_unsubmitted_changes
                && $this->versions->normalizeUnchangedSubmission($assignment, $submission)) {
                $submission->refresh()->load('currentVersion');
            }
        }

        $ready = $requiredCount > 0
            && $assignments->count() === $requiredCount
            && $assignments->pluck('reviewer_user_id')->unique()->count() === $requiredCount
            && $assignments->every(fn (ReviewerAssignment $assignment): bool => $assignment->assignment_status === ReviewerAssignmentStatus::DecisionSubmitted
                && $assignment->reviewSubmission?->status === ReviewSubmissionStatus::Submitted
                && $assignment->reviewSubmission?->currentVersion?->decision !== null
                && ! $assignment->reviewSubmission?->has_unsubmitted_changes
            );
        $decisions = $ready
            ? $assignments->map(fn (ReviewerAssignment $assignment): string => $assignment->reviewSubmission->currentVersion->decision->value)->unique()->values()
            : collect();
        $status = ! $ready
            ? ReviewConsensusStatus::AwaitingSubmissions
            : ($decisions->count() === 1 ? ReviewConsensusStatus::Consensus : ReviewConsensusStatus::Conflicted);
        $signature = $ready ? $this->signature($assignments) : null;
        $previousStatus = $application->review_consensus_status;
        $previousSignature = $application->review_consensus_signature;
        $previousApplicationStatus = $application->application_status;
        $evaluatedAt = now();
        $finalRevisionFailure = $status === ReviewConsensusStatus::Consensus
            && $cycle >= ApplicationRevisionWorkflowService::MAX_REVISION_CYCLES
            && in_array($decisions->first(), [
                ReviewDecision::MinorRevision->value,
                ReviewDecision::MajorRevision->value,
            ], true);
        $terminalFailure = $previousApplicationStatus === ApplicationStatus::Failed || $finalRevisionFailure;

        $application->update([
            'review_consensus_status' => $status->value,
            'review_consensus_cycle' => $cycle,
            'review_consensus_decision' => $status === ReviewConsensusStatus::Consensus ? $decisions->first() : null,
            'review_consensus_signature' => $signature,
            'review_consensus_evaluated_at' => $evaluatedAt,
            'review_conflicted_at' => $status === ReviewConsensusStatus::Conflicted
                ? ($application->review_conflicted_at ?? $evaluatedAt)
                : null,
            ...($terminalFailure ? [
                'application_status' => ApplicationStatus::Failed->value,
                'current_stage' => ApplicationStage::Completed->value,
                'status_updated_at' => $evaluatedAt,
            ] : ($ready ? [
                'application_status' => ApplicationStatus::ReviewSubmittedPendingRelease->value,
                'current_stage' => ApplicationStage::DecisionRelease->value,
                'status_updated_at' => $evaluatedAt,
            ] : [])),
        ]);

        if ($finalRevisionFailure && $previousApplicationStatus !== ApplicationStatus::Failed) {
            ApplicationRevision::query()
                ->where('research_application_id', $application->id)
                ->where('revision_number', $cycle)
                ->where('status', ApplicationRevisionStatus::UnderReview->value)
                ->update([
                    'status' => ApplicationRevisionStatus::Completed->value,
                    'completed_at' => $evaluatedAt,
                ]);
            $this->auditLog->record(null, 'application.final_revision_failed', $application, [
                'review_cycle' => $cycle,
                'decision' => $decisions->first(),
                'consensus_signature' => $signature,
                'reviewer_assignment_ids' => $assignments->pluck('id')->all(),
                'result' => ApplicationStatus::Failed->value,
            ]);
            $this->notifyFinalRevisionFailure($application, $assignments);
        }

        if ($status === ReviewConsensusStatus::Conflicted
            && ($previousStatus !== ReviewConsensusStatus::Conflicted || $previousSignature !== $signature)) {
            $this->notifyConflict($application);
            $this->auditLog->record(null, 'application.review_consensus_conflicted', $application, [
                'review_cycle' => $cycle,
                'submission_count' => $assignments->count(),
                'consensus_signature' => $signature,
                'result' => $status->value,
            ]);
        } elseif ($previousStatus === ReviewConsensusStatus::Conflicted && $status === ReviewConsensusStatus::Consensus) {
            $this->auditLog->record(null, 'application.review_consensus_resolved', $application, [
                'review_cycle' => $cycle,
                'consensus_signature' => $signature,
                'result' => $status->value,
            ]);
        }

        return $application->refresh();
    }

    /** Revalidate the current cycle and return its first immutable version for legacy callers. */
    public function assertReleaseableLocked(
        ResearchApplication $application,
        ?ReviewSubmission $requestedSource = null,
    ): ReviewSubmissionVersion {
        return $this->assertReleaseableVersionsLocked($application, $requestedSource)->firstOrFail();
    }

    /**
     * Revalidate the exact current cycle and return every immutable source version
     * participating in the persisted consensus signature. The caller must already
     * hold the application lock.
     *
     * @return Collection<int, ReviewSubmissionVersion>
     */
    public function assertReleaseableVersionsLocked(
        ResearchApplication $application,
        ?ReviewSubmission $requestedSource = null,
    ): Collection {
        $application = $this->evaluateLocked($application);

        if ($application->review_consensus_status === ReviewConsensusStatus::Conflicted) {
            throw ValidationException::withMessages([
                'review_submission_id' => 'Full Board Reviewer decisions are conflicted. Release is blocked until all current Reviewers submit the same decision.',
            ])->errorBag('decisionRelease');
        }
        if ($application->review_consensus_status !== ReviewConsensusStatus::Consensus) {
            throw ValidationException::withMessages([
                'review_submission_id' => 'Every required current Reviewer must submit matching, up-to-date work before RES can release a decision.',
            ])->errorBag('decisionRelease');
        }

        $cycle = (int) $application->review_consensus_cycle;
        $reviewType = $cycle === 0 ? 'initial_review' : 'revision_review';
        $assignments = ReviewerAssignment::query()
            ->current()
            ->where('research_application_id', $application->id)
            ->where('review_cycle', $cycle)
            ->where('review_type', $reviewType)
            ->with('reviewSubmission.currentVersion')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
        if ($requestedSource && ! $assignments->contains(
            fn (ReviewerAssignment $assignment): bool => $assignment->reviewSubmission?->id === $requestedSource->id,
        )) {
            throw ValidationException::withMessages([
                'review_submission_id' => 'Select a current submitted Reviewer decision from this consensus cycle.',
            ])->errorBag('decisionRelease');
        }

        $versions = $assignments
            ->map(fn (ReviewerAssignment $assignment): ?ReviewSubmissionVersion => $assignment->reviewSubmission?->currentVersion)
            ->filter();
        if ($versions->count() !== $assignments->count()
            || $versions->contains(
                fn (ReviewSubmissionVersion $version): bool => $version->decision?->value !== $application->review_consensus_decision?->value,
            )) {
            throw ValidationException::withMessages([
                'review_submission_id' => 'The current Reviewer evidence no longer matches the validated application consensus.',
            ])->errorBag('decisionRelease');
        }

        return $versions->values();
    }

    /** @param Collection<int, ReviewerAssignment> $assignments */
    private function signature(Collection $assignments): string
    {
        return hash('sha256', $assignments
            ->sortBy('id')
            ->map(fn (ReviewerAssignment $assignment): string => implode(':', [
                $assignment->id,
                $assignment->reviewSubmission->currentVersion->id,
                $assignment->reviewSubmission->currentVersion->decision->value,
            ]))
            ->implode('|'));
    }

    private function notifyConflict(ResearchApplication $application): void
    {
        User::query()
            ->where('role', UserRole::ResLead->value)
            ->where('account_status', AccountStatus::Active->value)
            ->select('id')
            ->eachById(function (User $resLead) use ($application): void {
                $resLead->notify(new DashboardUpdateNotification([
                    'title' => 'Conflicted Full Board decisions require attention',
                    'message' => 'Current Reviewer submissions disagree. Decision release is blocked until consensus is restored.',
                    'icon' => 'alert-triangle',
                    'tone' => 'red',
                    'route' => 'res.certificates.index',
                    'route_parameters' => ['application' => $application->id],
                    'academic_term_id' => $application->academic_term_id,
                ]));
            }, 100);
    }

    /** @param Collection<int, ReviewerAssignment> $assignments */
    private function notifyFinalRevisionFailure(
        ResearchApplication $application,
        Collection $assignments,
    ): void {
        $title = 'Application failed after the final revision review';
        $message = 'The third revised submission still received a Minor or Major Revision decision. The application is now closed as Failed and cannot enter another revision cycle.';
        $notifiedUserIds = [];
        $notify = function (?User $user, string $route, array $parameters) use (
            $application,
            $title,
            $message,
            &$notifiedUserIds,
        ): void {
            if (! $user
                || $user->account_status !== AccountStatus::Active
                || in_array($user->id, $notifiedUserIds, true)) {
                return;
            }

            $user->notify(new DashboardUpdateNotification([
                'title' => $title,
                'message' => $message,
                'icon' => 'alert-triangle',
                'tone' => 'red',
                'route' => $route,
                'route_parameters' => $parameters,
                'academic_term_id' => $application->academic_term_id,
            ]));
            $notifiedUserIds[] = $user->id;
        };

        $application->loadMissing(['applicant:id,account_status', 'adviser:id,account_status']);
        $notify($application->applicant, 'applicant.revision-certificates.index', [
            'application' => $application->id,
        ]);
        $notify($application->adviser, 'adviser.applications.show', [
            'researchApplication' => $application->id,
        ]);

        $assignments->loadMissing('reviewer:id,account_status');
        foreach ($assignments as $assignment) {
            $notify($assignment->reviewer, 'reviewer.assignments.show', [
                'reviewerAssignment' => $assignment->id,
            ]);
        }

        User::query()
            ->where('role', UserRole::ResLead->value)
            ->where('account_status', AccountStatus::Active->value)
            ->select(['id', 'account_status'])
            ->eachById(fn (User $resLead) => $notify($resLead, 'res.certificates.index', [
                'q' => $application->application_code,
            ]), 100);
    }
}
