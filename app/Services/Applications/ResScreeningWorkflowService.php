<?php

namespace App\Services\Applications;

use App\Enums\ApplicationStage;
use App\Enums\ApplicationStatus;
use App\Enums\ReviewConsensusStatus;
use App\Enums\ReviewerAssignmentStatus;
use App\Enums\ReviewType;
use App\Enums\UserRole;
use App\Models\ApplicationScreening;
use App\Models\ResearchApplication;
use App\Models\ReviewerAssignment;
use App\Models\User;
use App\Notifications\DashboardUpdateNotification;
use App\Services\AuditLogService;
use App\Services\Settings\DeadlineProcessAvailability;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Applies the initial RES classification and reviewer-assignment transitions atomically.
 */
class ResScreeningWorkflowService
{
    public function __construct(
        private readonly ApplicationRequirementService $requirements,
        private readonly ReviewerEligibilityService $reviewerEligibility,
        private readonly DeadlineProcessAvailability $deadlines,
        private readonly AuditLogService $auditLog,
        private readonly ReviewConsensusService $consensus,
    ) {}

    /**
     * Persist one complete screening decision and project it onto the application workflow.
     *
     * @param  array<string, mixed>  $data
     */
    public function classify(
        User $actor,
        ResearchApplication $application,
        array $data,
    ): ApplicationScreening {
        return DB::transaction(function () use ($actor, $application, $data): ApplicationScreening {
            // Serialize classification attempts before rechecking authorization and requirement readiness.
            $locked = ResearchApplication::query()
                ->whereKey($application->id)
                ->lockForUpdate()
                ->firstOrFail();

            Gate::forUser($actor)->authorize('classify', $locked);

            if ($locked->screening()->exists()) {
                throw ValidationException::withMessages([
                    'review_type' => 'This application has already been classified.',
                ])->errorBag('resScreening');
            }

            // Browser confirmations are advisory; current mandatory-document state remains authoritative.
            try {
                $this->requirements->assertReady($locked);
            } catch (ValidationException $exception) {
                throw $exception->errorBag('resScreening');
            }
            $reviewType = ReviewType::from($data['review_type']);
            $classifiedAt = now();

            $screening = $locked->screening()->create([
                'screened_by_user_id' => $actor->id,
                'review_type' => $reviewType,
                'classification_reason' => trim((string) $data['classification_reason']),
                'classified_at' => $classifiedAt,
            ]);

            $nextStatus = $reviewType === ReviewType::Exempted
                ? ApplicationStatus::Exempted
                : ApplicationStatus::AwaitingReviewerAssignment;
            $nextStage = $reviewType === ReviewType::Exempted
                ? ApplicationStage::DecisionRelease
                : ApplicationStage::ResScreening;

            // The application remains the fast workflow projection used by queues and dashboards.
            $locked->update([
                'review_type' => $reviewType->value,
                'application_status' => $nextStatus->value,
                'current_stage' => $nextStage->value,
                'status_updated_at' => $classifiedAt,
            ]);

            // Keep notes and classification reasoning out of the bounded audit event.
            $this->auditLog->record($actor, 'application.res_classified', $locked, [
                'review_type' => $reviewType->value,
                'reviewer_count' => $reviewType->reviewerCount(),
                'result' => $nextStatus->value,
            ]);

            $this->notifyApplicantOfWorkflowUpdate($locked, $reviewType === ReviewType::Exempted);

            return $screening->refresh();
        }, 3);
    }

    /** Correct classification metadata while preserving every prior reviewer-work record. */
    public function updateScreening(
        User $actor,
        ResearchApplication $application,
        array $data,
    ): ApplicationScreening {
        return DB::transaction(function () use ($actor, $application, $data): ApplicationScreening {
            $locked = ResearchApplication::query()
                ->whereKey($application->id)
                ->lockForUpdate()
                ->firstOrFail();
            Gate::forUser($actor)->authorize('updateScreening', $locked);

            $screening = ApplicationScreening::query()
                ->where('research_application_id', $locked->id)
                ->lockForUpdate()
                ->firstOrFail();
            $assignments = ReviewerAssignment::query()
                ->current()
                ->where('research_application_id', $locked->id)
                ->where('review_type', 'initial_review')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $previousReviewType = $screening->review_type;
            $reviewType = ReviewType::from($data['review_type']);

            try {
                $this->requirements->assertReady($locked);
            } catch (ValidationException $exception) {
                throw $exception->errorBag('resScreening');
            }

            $updatedAt = now();
            $screening->update([
                'screened_by_user_id' => $actor->id,
                'review_type' => $reviewType,
                'classification_reason' => trim((string) $data['classification_reason']),
                'classified_at' => $updatedAt,
            ]);

            if ($reviewType === $previousReviewType && $assignments->count() === $reviewType->reviewerCount()) {
                $nextStatus = $locked->application_status;
                $nextStage = $locked->current_stage;
            } else {
                $supersededReviewerIds = $assignments->pluck('reviewer_user_id')->unique();

                foreach ($assignments as $assignment) {
                    $assignment->update([
                        'superseded_at' => $updatedAt,
                        'superseded_by_user_id' => $actor->id,
                        'supersession_reason' => 'Review classification changed by RES.',
                        'superseded_from_status' => $assignment->assignment_status->value,
                        'assignment_status' => ReviewerAssignmentStatus::Superseded->value,
                    ]);
                }

                $nextStatus = $reviewType === ReviewType::Exempted
                    ? ApplicationStatus::Exempted
                    : ApplicationStatus::AwaitingReviewerAssignment;
                $nextStage = $reviewType === ReviewType::Exempted
                    ? ApplicationStage::DecisionRelease
                    : ApplicationStage::ResScreening;

                User::query()->whereKey($supersededReviewerIds)->get()->each(function (User $reviewer): void {
                    $reviewer->notify(new DashboardUpdateNotification([
                        'title' => 'Ethics review assignment updated',
                        'message' => 'A previously assigned application is no longer in your active review queue.',
                        'icon' => 'clipboard',
                        'tone' => 'blue',
                        'route' => 'reviewer.assignments.index',
                    ]));
                });
            }
            $locked->update([
                'review_type' => $reviewType->value,
                'application_status' => $nextStatus->value,
                'current_stage' => $nextStage->value,
                'status_updated_at' => $updatedAt,
            ]);

            // Audit only workflow deltas; screening notes and reasons remain deliberately excluded.
            $this->auditLog->record($actor, 'application.res_screening_updated', $locked, [
                'previous_review_type' => $previousReviewType->value,
                'review_type' => $reviewType->value,
                'superseded_reviewer_count' => $assignments->count(),
                'result' => $nextStatus->value,
            ]);

            $this->notifyApplicantOfScreeningCorrection($locked);

            return $screening->refresh();
        }, 3);
    }

    /**
     * Assign the exact required reviewer set and advance the application to ethics review.
     *
     * @param  array<int, int|string>  $reviewerIds
     * @return Collection<int, ReviewerAssignment>
     */
    public function assignReviewers(
        User $actor,
        ResearchApplication $application,
        array $reviewerIds,
        ?string $reassignmentReason = null,
    ): Collection {
        return DB::transaction(function () use ($actor, $application, $reviewerIds, $reassignmentReason): Collection {
            // Lock the application first so all assignment attempts acquire shared resources consistently.
            $locked = ResearchApplication::query()
                ->whereKey($application->id)
                ->lockForUpdate()
                ->firstOrFail();

            Gate::forUser($actor)->authorize('assignReviewers', $locked);
            $reviewType = ReviewType::tryFrom((string) $locked->review_type);
            $reviewCycle = max(0, ((int) $locked->current_revision_cycle) - 1);
            $assignmentReviewType = $reviewCycle === 0 ? 'initial_review' : 'revision_review';

            if (! $reviewType?->requiresReviewers() || ! $locked->screening()->exists()) {
                throw ValidationException::withMessages([
                    'reviewer_ids' => 'This application is not ready for reviewer assignment.',
                ])->errorBag('reviewerAssignment');
            }

            $reviewerIds = array_values(array_unique(array_map('intval', $reviewerIds)));

            if (count($reviewerIds) !== $reviewType->reviewerCount()) {
                throw ValidationException::withMessages([
                    'reviewer_ids' => 'Select exactly '.$reviewType->reviewerCount().' eligible reviewer(s).',
                ])->errorBag('reviewerAssignment');
            }

            $currentAssignments = ReviewerAssignment::query()
                ->current()
                ->where('research_application_id', $locked->id)
                ->where('review_type', $assignmentReviewType)
                ->where('review_cycle', $reviewCycle)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            $retainedReviewerIds = $currentAssignments->pluck('reviewer_user_id')->intersect($reviewerIds);
            $supersededAssignments = $currentAssignments
                ->reject(fn (ReviewerAssignment $assignment): bool => in_array($assignment->reviewer_user_id, $reviewerIds, true))
                ->values();

            if ($supersededAssignments->isNotEmpty() && blank($reassignmentReason)) {
                throw ValidationException::withMessages([
                    'reassignment_reason' => 'Explain why the current reviewer set is being changed.',
                ])->errorBag('reviewerAssignment');
            }

            // Sorted reviewer locks make concurrent capacity checks deterministic and reduce deadlock risk.
            $reviewers = User::query()
                ->whereKey($reviewerIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($reviewers->count() !== count($reviewerIds)) {
                throw ValidationException::withMessages([
                    'reviewer_ids' => 'One or more selected reviewers are no longer available.',
                ])->errorBag('reviewerAssignment');
            }

            foreach ($reviewers->whereNotIn('id', $retainedReviewerIds) as $reviewer) {
                $this->reviewerEligibility->assertEligible($reviewer, $locked, $reviewType);
            }

            $assignedAt = now();
            $reviewDeadline = $this->deadlines
                ->configuration($reviewCycle === 0 ? 'reviewer-submission' : 'reviewing-revision-period', UserRole::Reviewer)
                ?->due_at;
            foreach ($supersededAssignments as $assignment) {
                $assignment->update([
                    'superseded_at' => $assignedAt,
                    'superseded_by_user_id' => $actor->id,
                    'supersession_reason' => trim((string) $reassignmentReason),
                    'superseded_from_status' => $assignment->assignment_status->value,
                    'assignment_status' => ReviewerAssignmentStatus::Superseded->value,
                ]);
            }

            $replacementLinks = $supersededAssignments->pluck('id')->values();
            $newAssignments = $reviewers
                ->whereNotIn('id', $retainedReviewerIds)
                ->values()
                ->map(function (User $reviewer, int $index) use ($locked, $assignedAt, $reviewDeadline, $replacementLinks, $assignmentReviewType, $reviewCycle): ReviewerAssignment {
                    $sequence = (int) ReviewerAssignment::query()
                        ->where('research_application_id', $locked->id)
                        ->where('reviewer_user_id', $reviewer->id)
                        ->where('review_type', $assignmentReviewType)
                        ->max('assignment_sequence') + 1;

                    return $locked->reviewerAssignments()->create([
                        // Initial versus revision cycle remains separate from expedited/full-board classification.
                        'reviewer_user_id' => $reviewer->id,
                        'review_type' => $assignmentReviewType,
                        'review_cycle' => $reviewCycle,
                        'assignment_status' => $reviewCycle === 0
                            ? ReviewerAssignmentStatus::Pending->value
                            : ReviewerAssignmentStatus::RevisionReview->value,
                        'assignment_sequence' => $sequence,
                        'replaces_assignment_id' => $replacementLinks->get($index),
                        'assigned_at' => $assignedAt,
                        'review_deadline_at' => $reviewDeadline,
                    ]);
                });
            $assignments = ReviewerAssignment::query()
                ->current()
                ->where('research_application_id', $locked->id)
                ->where('review_type', $assignmentReviewType)
                ->where('review_cycle', $reviewCycle)
                ->with('reviewer')
                ->orderBy('id')
                ->get();

            $nextStatus = $reviewCycle > 0
                ? ApplicationStatus::UnderReReview
                : ($reviewType === ReviewType::Expedited
                    ? ApplicationStatus::UnderExpeditedReview
                    : ApplicationStatus::UnderFullBoardReview);

            $locked->update([
                'application_status' => $nextStatus->value,
                'current_stage' => ApplicationStage::EthicsReview->value,
                'review_consensus_status' => ReviewConsensusStatus::AwaitingSubmissions->value,
                'review_consensus_cycle' => $reviewCycle,
                'review_consensus_decision' => null,
                'review_consensus_signature' => null,
                'review_consensus_evaluated_at' => $assignedAt,
                'review_conflicted_at' => null,
                'status_updated_at' => $assignedAt,
            ]);
            $locked = $this->consensus->evaluateLocked($locked);

            // Audit only the classification and assignment total, never reviewer comments or document contents.
            $this->auditLog->record($actor, $supersededAssignments->isEmpty()
                ? 'application.reviewers_assigned'
                : 'application.reviewers_reassigned', $locked, [
                    'review_type' => $reviewType->value,
                    'reviewer_count' => $assignments->count(),
                    'superseded_count' => $supersededAssignments->count(),
                    'result' => $nextStatus->value,
                ]);

            foreach ($newAssignments as $assignment) {
                $assignment->reviewer->notify(new DashboardUpdateNotification([
                    'title' => 'Ethics review assignment available',
                    'message' => 'A research ethics application is ready for your review.',
                    'icon' => 'file-search',
                    'tone' => 'blue',
                    'route' => 'reviewer.assignments.show',
                    'route_parameters' => ['reviewerAssignment' => $assignment->id],
                ]));
            }

            User::query()->whereKey($supersededAssignments->pluck('reviewer_user_id'))->get()
                ->each(function (User $reviewer): void {
                    $reviewer->notify(new DashboardUpdateNotification([
                        'title' => 'Ethics review assignment updated',
                        'message' => 'A previously assigned application is no longer in your active review queue.',
                        'icon' => 'clipboard',
                        'tone' => 'blue',
                        'route' => 'reviewer.assignments.index',
                    ]));
                });

            $this->notifyApplicantOfWorkflowUpdate($locked, false);

            return $assignments;
        }, 3);
    }

    /**
     * Send a neutral applicant update without reviewer identity or internal screening details.
     */
    private function notifyApplicantOfWorkflowUpdate(
        ResearchApplication $application,
        bool $exempted,
    ): void {
        $applicant = User::query()->whereKey($application->applicant_user_id)->first();

        if (! $applicant) {
            return;
        }

        $applicant->notify(new DashboardUpdateNotification([
            'title' => 'Application status updated',
            'message' => $exempted
                ? 'Your application completed RES screening and moved to documentation processing.'
                : 'Your application moved to the next ethics review stage.',
            'icon' => 'clipboard',
            'tone' => 'blue',
            'route' => 'applicant.applications.show',
            'route_parameters' => ['researchApplication' => $application->id],
        ]));
    }

    /**
     * Notify the Applicant neutrally because screening corrections must not expose internal decision details.
     */
    private function notifyApplicantOfScreeningCorrection(ResearchApplication $application): void
    {
        $applicant = User::query()->whereKey($application->applicant_user_id)->first();

        if (! $applicant) {
            return;
        }

        $applicant->notify(new DashboardUpdateNotification([
            'title' => 'Application screening updated',
            'message' => 'Your application screening record and workflow status were updated.',
            'icon' => 'clipboard',
            'tone' => 'blue',
            'route' => 'applicant.applications.show',
            'route_parameters' => ['researchApplication' => $application->id],
        ]));
    }
}
