<?php

namespace App\Services\Applications;

use App\Enums\AccountStatus;
use App\Enums\ApplicationRevisionStatus;
use App\Enums\ApplicationStage;
use App\Enums\ApplicationStatus;
use App\Enums\ReviewCommentCategory;
use App\Enums\ReviewDecision;
use App\Enums\ReviewerAssignmentStatus;
use App\Enums\ReviewSubmissionStatus;
use App\Enums\UserRole;
use App\Models\ApplicationDecisionRelease;
use App\Models\ApplicationRevision;
use App\Models\ResearchApplication;
use App\Models\ReviewComment;
use App\Models\ReviewerAssignment;
use App\Models\ReviewSubmission;
use App\Models\User;
use App\Notifications\DashboardUpdateNotification;
use App\Services\AuditLogService;
use App\Services\Settings\DeadlineProcessAvailability;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ApplicationRevisionWorkflowService
{
    public const MAX_REVISION_CYCLES = 2;

    public function __construct(
        private readonly DeadlineProcessAvailability $deadlines,
        private readonly AuditLogService $auditLog,
    ) {}

    public function releaseDecision(
        User $actor,
        ResearchApplication $application,
        ReviewSubmission $sourceSubmission,
    ): ApplicationDecisionRelease {
        $release = DB::transaction(function () use ($actor, $application, $sourceSubmission): ApplicationDecisionRelease {
            $locked = ResearchApplication::query()->whereKey($application->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('releaseDecision', $locked);

            $reviewCycle = max(0, ((int) $locked->current_revision_cycle) - 1);
            $existing = ApplicationDecisionRelease::query()
                ->where('research_application_id', $locked->id)
                ->where('review_cycle', $reviewCycle)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                if ($existing->source_review_submission_id === $sourceSubmission->id
                    || ($existing->source_review_submission_id === null && $existing->decision === $sourceSubmission->decision)) {
                    return $existing;
                }

                throw ValidationException::withMessages([
                    'review_submission_id' => 'A different Reviewer decision has already been released for this review cycle.',
                ])->errorBag('decisionRelease');
            }

            if ($locked->application_status !== ApplicationStatus::ReviewSubmittedPendingRelease) {
                throw ValidationException::withMessages([
                    'review_submission_id' => 'This application is not awaiting an authorized result release.',
                ])->errorBag('decisionRelease');
            }

            $sourceReviewType = $reviewCycle === 0 ? 'initial_review' : 'revision_review';
            $assignments = ReviewerAssignment::query()
                ->current()
                ->where('research_application_id', $locked->id)
                ->where('review_cycle', $reviewCycle)
                ->where('review_type', $sourceReviewType)
                ->with('reviewSubmission')
                ->lockForUpdate()
                ->get();

            if ($assignments->isEmpty() || $assignments->contains(
                fn (ReviewerAssignment $assignment): bool => $assignment->assignment_status !== ReviewerAssignmentStatus::DecisionSubmitted
                    || $assignment->reviewSubmission?->status !== ReviewSubmissionStatus::Submitted,
            )) {
                throw ValidationException::withMessages([
                    'review_submission_id' => 'Every required Reviewer must submit this review cycle before RES can release a result.',
                ])->errorBag('decisionRelease');
            }

            $source = ReviewSubmission::query()
                ->whereKey($sourceSubmission->id)
                ->whereIn('reviewer_assignment_id', $assignments->pluck('id'))
                ->where('status', ReviewSubmissionStatus::Submitted->value)
                ->lockForUpdate()
                ->first();
            if (! $source?->decision) {
                throw ValidationException::withMessages([
                    'review_submission_id' => 'Select one submitted Reviewer decision from this application review cycle.',
                ])->errorBag('decisionRelease');
            }

            $decision = $source->decision;
            $comments = ReviewComment::query()
                ->where('reviewer_assignment_id', $source->reviewer_assignment_id)
                ->with('document:id,research_application_id,document_requirement_id,document_version')
                ->lockForUpdate()
                ->get();

            $selectedRequiredRevisionComments = $comments
                ->filter(fn (ReviewComment $comment): bool => $comment->category === ReviewCommentCategory::RequiredRevision);
            $requiredDocuments = $selectedRequiredRevisionComments
                ->filter(fn (ReviewComment $comment): bool => $comment->document !== null)
                ->map(fn (ReviewComment $comment): array => [
                    'requirement_id' => $comment->document->document_requirement_id,
                    'source_document_id' => $comment->document->id,
                ])
                ->unique('requirement_id')
                ->values();
            if (in_array($decision, [ReviewDecision::MinorRevision, ReviewDecision::MajorRevision], true)) {
                if ($reviewCycle >= self::MAX_REVISION_CYCLES) {
                    throw ValidationException::withMessages([
                        'review_submission_id' => 'The maximum of two revision cycles has been reached. A further revision cannot be opened for this application.',
                    ])->errorBag('decisionRelease');
                }
            }

            $releasedAt = now();
            $release = ApplicationDecisionRelease::create([
                'research_application_id' => $locked->id,
                'review_cycle' => $reviewCycle,
                'source_review_type' => $sourceReviewType,
                'source_review_submission_id' => $source->id,
                'decision' => $decision->value,
                'released_by_user_id' => $actor->id,
                'released_at' => $releasedAt,
            ]);

            if ($comments->isNotEmpty()) {
                ReviewComment::query()->whereIn('id', $comments->pluck('id'))->update([
                    'application_decision_release_id' => $release->id,
                    'released_at' => $releasedAt,
                    'released_by_user_id' => $actor->id,
                ]);
            }

            if ($reviewCycle > 0) {
                ApplicationRevision::query()
                    ->where('research_application_id', $locked->id)
                    ->where('revision_number', $reviewCycle)
                    ->lockForUpdate()
                    ->update([
                        'status' => ApplicationRevisionStatus::Completed->value,
                        'completed_at' => $releasedAt,
                    ]);
            }

            if (in_array($decision, [ReviewDecision::MinorRevision, ReviewDecision::MajorRevision], true)) {
                $revisionStatus = $this->deadlines->status(
                    'revision-period',
                    UserRole::Applicant,
                    'Applicant revision submission',
                );
                $dueAt = $revisionStatus['deadline']?->due_at;

                if (! $revisionStatus['configured'] || ! $dueAt || $dueAt->isPast()) {
                    throw ValidationException::withMessages([
                        'review_submission_id' => 'Configure a current Applicant revision deadline before releasing a revision decision.',
                    ])->errorBag('decisionRelease');
                }

                $revisionNumber = $reviewCycle + 1;
                $revision = ApplicationRevision::create([
                    'research_application_id' => $locked->id,
                    'application_decision_release_id' => $release->id,
                    'revision_number' => $revisionNumber,
                    'status' => ApplicationRevisionStatus::PendingUploads->value,
                    'due_at' => $dueAt,
                ]);

                foreach ($requiredDocuments as $requiredDocument) {
                    $revision->requirements()->create([
                        'document_requirement_id' => $requiredDocument['requirement_id'],
                        'source_application_document_id' => $requiredDocument['source_document_id'],
                        'is_required' => true,
                    ]);
                }

                $locked->update([
                    'application_status' => ApplicationStatus::RevisionWindowOpen->value,
                    'current_stage' => ApplicationStage::Revision->value,
                    'current_revision_cycle' => $revisionNumber + 1,
                    'status_updated_at' => $releasedAt,
                ]);
            } else {
                $locked->update([
                    'application_status' => $decision === ReviewDecision::Approved
                        ? ApplicationStatus::ResultReleasedAccepted->value
                        : ApplicationStatus::ResultReleasedDisapproved->value,
                    'current_stage' => $decision === ReviewDecision::Approved
                        ? ApplicationStage::DecisionRelease->value
                        : ApplicationStage::Completed->value,
                    'status_updated_at' => $releasedAt,
                ]);
            }

            $this->auditLog->record($actor, 'application.review_decision_released', $locked, [
                'decision_release_id' => $release->id,
                'review_cycle' => $reviewCycle,
                'decision' => $decision->value,
                'source_review_submission_id' => $source->id,
                'source_reviewer_assignment_id' => $source->reviewer_assignment_id,
                'released_comment_count' => $comments->count(),
                'required_document_count' => $requiredDocuments->count(),
                'result' => $locked->application_status->value,
            ]);

            return $release;
        }, 3);

        if ($release->wasRecentlyCreated) {
            $application->applicant?->notify(new DashboardUpdateNotification([
                'title' => 'Ethics review decision released',
                'message' => 'An authorized decision and its released comments are now available for your application.',
                'icon' => 'clipboard',
                'tone' => 'blue',
                'route' => 'applicant.revision-certificates.index',
                'route_parameters' => ['application' => $application->id],
            ]));
        }

        return $release;
    }

    public function submitRevision(
        User $actor,
        ResearchApplication $application,
        ApplicationRevision $revision,
    ): ApplicationRevision {
        $reviewerIds = [];

        $submitted = DB::transaction(function () use (
            $actor,
            $application,
            $revision,
            &$reviewerIds,
        ): ApplicationRevision {
            $lockedApplication = ResearchApplication::query()
                ->whereKey($application->id)
                ->lockForUpdate()
                ->firstOrFail();
            $lockedRevision = ApplicationRevision::query()
                ->whereKey($revision->id)
                ->where('research_application_id', $lockedApplication->id)
                ->lockForUpdate()
                ->firstOrFail();
            Gate::forUser($actor)->authorize('submitRevision', $lockedApplication);

            if ($lockedRevision->status === ApplicationRevisionStatus::UnderReview) {
                return $lockedRevision;
            }

            if ($lockedRevision->status !== ApplicationRevisionStatus::PendingUploads
                || $lockedApplication->application_status !== ApplicationStatus::RevisionWindowOpen) {
                throw ValidationException::withMessages([
                    'revision' => 'This revision is not accepting a submission.',
                ])->errorBag('revisionSubmission');
            }

            $this->deadlines->assertOpen(
                'revision-period',
                UserRole::Applicant,
                'Applicant revision submission',
            );
            if ($lockedRevision->due_at->isPast()) {
                throw ValidationException::withMessages([
                    'revision' => 'The application-specific revision deadline has passed.',
                ])->errorBag('revisionSubmission');
            }

            $requirements = $lockedRevision->requirements()
                ->with('replacementDocument')
                ->lockForUpdate()
                ->get();
            $missing = $requirements->filter(function ($requirement) use ($lockedApplication, $lockedRevision): bool {
                $document = $requirement->replacementDocument;

                return $requirement->is_required && (
                    ! $document
                    || $document->research_application_id !== $lockedApplication->id
                    || ! $document->is_current
                    || (int) $document->document_version !== ((int) $lockedRevision->revision_number) + 1
                );
            });

            if ($requirements->isEmpty() || $missing->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'revision' => 'Replace every document marked as required before submitting the revision.',
                ])->errorBag('revisionSubmission');
            }

            $reviewWindow = $this->deadlines->status(
                'reviewing-revision-period',
                UserRole::Reviewer,
                'Reviewing of revision',
            );
            $reviewDeadline = $reviewWindow['deadline']?->due_at;

            if (! $reviewWindow['configured'] || ! $reviewDeadline || $reviewDeadline->isPast()) {
                throw ValidationException::withMessages([
                    'revision' => 'The RES Lead must configure a current reviewing-revision deadline before this revision can be routed.',
                ])->errorBag('revisionSubmission');
            }

            $priorCycle = ((int) $lockedRevision->revision_number) - 1;
            $priorAssignments = ReviewerAssignment::query()
                ->current()
                ->where('research_application_id', $lockedApplication->id)
                ->where('review_cycle', $priorCycle)
                ->where('assignment_status', ReviewerAssignmentStatus::DecisionSubmitted->value)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($priorAssignments->isEmpty()) {
                throw ValidationException::withMessages([
                    'revision' => 'The prior authorized Reviewer set could not be resolved for re-review.',
                ])->errorBag('revisionSubmission');
            }

            $existingAssignments = ReviewerAssignment::query()
                ->current()
                ->where('research_application_id', $lockedApplication->id)
                ->where('review_cycle', $lockedRevision->revision_number)
                ->lockForUpdate()
                ->get();

            if ($existingAssignments->isEmpty()) {
                $assignedAt = now();
                foreach ($priorAssignments as $priorAssignment) {
                    $sequence = ((int) ReviewerAssignment::query()
                        ->where('research_application_id', $lockedApplication->id)
                        ->where('reviewer_user_id', $priorAssignment->reviewer_user_id)
                        ->where('review_type', 'revision_review')
                        ->max('assignment_sequence')) + 1;

                    ReviewerAssignment::create([
                        'research_application_id' => $lockedApplication->id,
                        'reviewer_user_id' => $priorAssignment->reviewer_user_id,
                        'review_type' => 'revision_review',
                        'review_cycle' => $lockedRevision->revision_number,
                        'assignment_status' => ReviewerAssignmentStatus::RevisionReview->value,
                        'assignment_sequence' => $sequence,
                        'replaces_assignment_id' => $priorAssignment->id,
                        'assigned_at' => $assignedAt,
                        'review_deadline_at' => $reviewDeadline,
                    ]);
                }
            }

            $reviewerIds = $priorAssignments->pluck('reviewer_user_id')->all();
            $submittedAt = now();
            $lockedRevision->update([
                'status' => ApplicationRevisionStatus::UnderReview->value,
                'submitted_by_user_id' => $actor->id,
                'submitted_at' => $submittedAt,
            ]);
            $lockedApplication->update([
                'application_status' => ApplicationStatus::UnderReReview->value,
                'current_stage' => ApplicationStage::EthicsReview->value,
                'status_updated_at' => $submittedAt,
            ]);

            $this->auditLog->record($actor, 'application.revision_submitted', $lockedApplication, [
                'application_revision_id' => $lockedRevision->id,
                'revision_number' => $lockedRevision->revision_number,
                'replacement_count' => $requirements->count(),
                'reviewer_count' => count($reviewerIds),
                'result' => ApplicationStatus::UnderReReview->value,
            ]);

            return $lockedRevision->refresh();
        }, 3);

        User::query()
            ->whereIn('id', $reviewerIds)
            ->where('role', UserRole::Reviewer->value)
            ->where('account_status', AccountStatus::Active->value)
            ->each(function (User $reviewer): void {
                $reviewer->notify(new DashboardUpdateNotification([
                    'title' => 'Revision ready for re-review',
                    'message' => 'A revised application assigned to you is ready for review.',
                    'icon' => 'refresh',
                    'tone' => 'blue',
                    'route' => 'reviewer.reviews.index',
                    'route_parameters' => ['tab' => 'revision'],
                ]));
            });

        return $submitted;
    }
}
