<?php

namespace App\Services\Applications;

use App\Enums\ApplicationStage;
use App\Enums\ApplicationStatus;
use App\Enums\ReceiptCheckStatus;
use App\Enums\ReviewerAssignmentStatus;
use App\Enums\ReviewerConflictStatus;
use App\Enums\ReviewType;
use App\Enums\ScreeningCompletenessStatus;
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
                'completeness_status' => ScreeningCompletenessStatus::from($data['completeness_status']),
                'receipt_check_status' => ReceiptCheckStatus::from($data['receipt_check_status']),
                'required_documents_verified' => true,
                'receipt_status_recorded' => true,
                'basic_eligibility_confirmed' => true,
                'screening_notes' => filled($data['screening_notes'] ?? null)
                    ? trim((string) $data['screening_notes'])
                    : null,
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

    /**
     * Correct one persisted screening while preserving compatible work and removing only unstarted assignments.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateScreening(
        User $actor,
        ResearchApplication $application,
        array $data,
    ): ApplicationScreening {
        return DB::transaction(function () use ($actor, $application, $data): ApplicationScreening {
            // Lock the application, screening, and initial assignments in a stable order before reconciling state.
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
                ->where('research_application_id', $locked->id)
                ->where('review_type', 'initial_review')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $previousReviewType = $screening->review_type;
            $reviewType = ReviewType::from($data['review_type']);
            $previouslyReady = $this->persistedScreeningIsReady($screening);
            $administrativelyReady = $this->screeningIsReady($data);

            // A still-valid decision must continue to use the current persisted requirement state.
            if ($administrativelyReady) {
                try {
                    $this->requirements->assertReady($locked);
                } catch (ValidationException $exception) {
                    throw $exception->errorBag('resScreening');
                }
            }

            $assignmentCountIsCompatible = $reviewType === ReviewType::Exempted
                ? $assignments->isEmpty()
                : $assignments->count() === $reviewType->reviewerCount()
                    || ($assignments->isEmpty() && in_array($locked->application_status, [
                        ApplicationStatus::UnderResScreening,
                        ApplicationStatus::AwaitingReviewerAssignment,
                    ], true));
            $assignmentsAreCompatible = $previouslyReady
                && $administrativelyReady
                && $reviewType === $previousReviewType
                && $assignmentCountIsCompatible;
            $removedReviewerIds = collect();

            if ($assignments->isNotEmpty() && ! $assignmentsAreCompatible) {
                // Started or submitted review work is immutable from this correction surface.
                if ($assignments->contains(fn (ReviewerAssignment $assignment): bool => $assignment->assignment_status !== ReviewerAssignmentStatus::Pending
                    || $assignment->submitted_at !== null)) {
                    throw ValidationException::withMessages([
                        'review_type' => 'This change cannot be saved because one or more assigned reviewers already started review work.',
                    ])->errorBag('resScreening');
                }

                $removedReviewerIds = $assignments->pluck('reviewer_user_id')->unique()->values();
                ReviewerAssignment::query()->whereKey($assignments->pluck('id'))->delete();
                $assignments = collect();
            }

            $updatedAt = now();
            $screening->update([
                'screened_by_user_id' => $actor->id,
                'completeness_status' => ScreeningCompletenessStatus::from($data['completeness_status']),
                'receipt_check_status' => ReceiptCheckStatus::from($data['receipt_check_status']),
                'required_documents_verified' => (bool) $data['required_documents_verified'],
                'receipt_status_recorded' => (bool) $data['receipt_status_recorded'],
                'basic_eligibility_confirmed' => (bool) $data['basic_eligibility_confirmed'],
                'screening_notes' => filled($data['screening_notes'] ?? null)
                    ? trim((string) $data['screening_notes'])
                    : null,
                'review_type' => $reviewType,
                'classification_reason' => trim((string) $data['classification_reason']),
                'classified_at' => $updatedAt,
            ]);

            // A metadata-only correction must never rewind an already active initial-review projection.
            if ($assignmentsAreCompatible) {
                $nextStatus = $locked->application_status;
                $nextStage = $locked->current_stage;
            } else {
                [$nextStatus, $nextStage] = $this->correctedProjection(
                    $administrativelyReady,
                    $reviewType,
                    $assignments->count(),
                );
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
                'removed_reviewer_count' => $removedReviewerIds->count(),
                'result' => $nextStatus->value,
            ]);

            // Removed pending reviewers receive a neutral queue update without application identity or internal reasons.
            User::query()->whereKey($removedReviewerIds)->get()->each(function (User $reviewer): void {
                $reviewer->notify(new DashboardUpdateNotification([
                    'title' => 'Ethics review assignment updated',
                    'message' => 'A previously assigned application is no longer in your active review queue.',
                    'icon' => 'clipboard',
                    'tone' => 'blue',
                    'route' => 'reviewer.assignments.index',
                ]));
            });
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
    ): Collection {
        return DB::transaction(function () use ($actor, $application, $reviewerIds): Collection {
            // Lock the application first so all assignment attempts acquire shared resources consistently.
            $locked = ResearchApplication::query()
                ->whereKey($application->id)
                ->lockForUpdate()
                ->firstOrFail();

            Gate::forUser($actor)->authorize('assignReviewers', $locked);
            $reviewType = ReviewType::tryFrom((string) $locked->review_type);

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

            if ($locked->reviewerAssignments()->where('review_type', 'initial_review')->exists()) {
                throw ValidationException::withMessages([
                    'reviewer_ids' => 'Reviewers have already been assigned to this application.',
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

            foreach ($reviewers as $reviewer) {
                $this->reviewerEligibility->assertEligible($reviewer, $locked, $reviewType);
            }

            $assignedAt = now();
            $reviewDeadline = $this->deadlines
                ->configuration('reviewer-submission', UserRole::Reviewer)
                ?->due_at;
            $assignments = $reviewers->map(function (User $reviewer) use ($locked, $assignedAt, $reviewDeadline): ReviewerAssignment {
                return $locked->reviewerAssignments()->create([
                    // Initial versus revision cycle remains separate from expedited/full-board classification.
                    'reviewer_user_id' => $reviewer->id,
                    'review_type' => 'initial_review',
                    'assignment_status' => ReviewerAssignmentStatus::Pending->value,
                    'conflict_status' => ReviewerConflictStatus::Pending->value,
                    'assigned_at' => $assignedAt,
                    'review_deadline_at' => $reviewDeadline,
                ]);
            });

            $nextStatus = $reviewType === ReviewType::Expedited
                ? ApplicationStatus::UnderExpeditedReview
                : ApplicationStatus::UnderFullBoardReview;

            $locked->update([
                'application_status' => $nextStatus->value,
                'current_stage' => ApplicationStage::EthicsReview->value,
                'status_updated_at' => $assignedAt,
            ]);

            // Audit only the classification and assignment total, never reviewer comments or document contents.
            $this->auditLog->record($actor, 'application.reviewers_assigned', $locked, [
                'review_type' => $reviewType->value,
                'reviewer_count' => $assignments->count(),
                'result' => $nextStatus->value,
            ]);

            foreach ($assignments as $assignment) {
                $assignment->reviewer->notify(new DashboardUpdateNotification([
                    'title' => 'Ethics review assignment available',
                    'message' => 'A research ethics application is ready for your review.',
                    'icon' => 'file-search',
                    'tone' => 'blue',
                    'route' => 'reviewer.assignments.show',
                    'route_parameters' => ['reviewerAssignment' => $assignment->id],
                ]));
            }

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
     * Treat any revoked administrative confirmation as a return to active RES screening.
     *
     * @param  array<string, mixed>  $data
     */
    private function screeningIsReady(array $data): bool
    {
        return $data['completeness_status'] === ScreeningCompletenessStatus::Complete->value
            && $data['receipt_check_status'] === ReceiptCheckStatus::Accepted->value
            && (bool) $data['required_documents_verified']
            && (bool) $data['receipt_status_recorded']
            && (bool) $data['basic_eligibility_confirmed'];
    }

    /**
     * Resolve whether the current saved screening decision is administratively complete.
     */
    private function persistedScreeningIsReady(ApplicationScreening $screening): bool
    {
        return $screening->completeness_status === ScreeningCompletenessStatus::Complete
            && $screening->receipt_check_status === ReceiptCheckStatus::Accepted
            && $screening->required_documents_verified
            && $screening->receipt_status_recorded
            && $screening->basic_eligibility_confirmed;
    }

    /**
     * Resolve the application projection after a correction and any pending-assignment cleanup.
     *
     * @return array{ApplicationStatus, ApplicationStage}
     */
    private function correctedProjection(
        bool $administrativelyReady,
        ReviewType $reviewType,
        int $assignmentCount,
    ): array {
        if (! $administrativelyReady) {
            return [ApplicationStatus::UnderResScreening, ApplicationStage::ResScreening];
        }

        if ($reviewType === ReviewType::Exempted) {
            return [ApplicationStatus::Exempted, ApplicationStage::DecisionRelease];
        }

        if ($assignmentCount === $reviewType->reviewerCount()) {
            return [
                $reviewType === ReviewType::Expedited
                    ? ApplicationStatus::UnderExpeditedReview
                    : ApplicationStatus::UnderFullBoardReview,
                ApplicationStage::EthicsReview,
            ];
        }

        return [ApplicationStatus::AwaitingReviewerAssignment, ApplicationStage::ResScreening];
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
