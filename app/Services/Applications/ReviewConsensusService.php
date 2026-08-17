<?php

namespace App\Services\Applications;

use App\Enums\AccountStatus;
use App\Enums\ApplicationStage;
use App\Enums\ApplicationStatus;
use App\Enums\ReviewConsensusStatus;
use App\Enums\ReviewSubmissionStatus;
use App\Enums\ReviewType;
use App\Enums\ReviewerAssignmentStatus;
use App\Enums\UserRole;
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
        }

        $ready = $requiredCount > 0
            && $assignments->count() === $requiredCount
            && $assignments->pluck('reviewer_user_id')->unique()->count() === $requiredCount
            && $assignments->every(fn (ReviewerAssignment $assignment): bool =>
                $assignment->assignment_status === ReviewerAssignmentStatus::DecisionSubmitted
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
        $evaluatedAt = now();

        $application->update([
            'review_consensus_status' => $status->value,
            'review_consensus_cycle' => $cycle,
            'review_consensus_decision' => $status === ReviewConsensusStatus::Consensus ? $decisions->first() : null,
            'review_consensus_signature' => $signature,
            'review_consensus_evaluated_at' => $evaluatedAt,
            'review_conflicted_at' => $status === ReviewConsensusStatus::Conflicted
                ? ($application->review_conflicted_at ?? $evaluatedAt)
                : null,
            ...($ready ? [
                'application_status' => ApplicationStatus::ReviewSubmittedPendingRelease->value,
                'current_stage' => ApplicationStage::DecisionRelease->value,
                'status_updated_at' => $evaluatedAt,
            ] : []),
        ]);

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

    /**
     * Revalidate the exact current cycle and return the immutable source version.
     * The caller must already hold the application lock.
     */
    public function assertReleaseableLocked(
        ResearchApplication $application,
        ?ReviewSubmission $requestedSource = null,
    ): ReviewSubmissionVersion {
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
        $source = $requestedSource
            ? $assignments->first(fn (ReviewerAssignment $assignment): bool => $assignment->reviewSubmission?->id === $requestedSource->id)?->reviewSubmission
            : $assignments->first()?->reviewSubmission;

        if (! $source?->currentVersion
            || $source->currentVersion->decision?->value !== $application->review_consensus_decision?->value) {
            throw ValidationException::withMessages([
                'review_submission_id' => 'Select a current submitted Reviewer decision from this consensus cycle.',
            ])->errorBag('decisionRelease');
        }

        return $source->currentVersion;
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
                ]));
            }, 100);
    }
}
