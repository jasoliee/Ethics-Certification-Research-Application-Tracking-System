<?php

namespace App\Services\Applications;

use App\Enums\AccountStatus;
use App\Enums\ApplicationStage;
use App\Enums\ApplicationStatus;
use App\Enums\ReviewCommentScope;
use App\Enums\ReviewDecision;
use App\Enums\ReviewerAssignmentStatus;
use App\Enums\ReviewFormArtifactStatus;
use App\Enums\ReviewFormStatus;
use App\Enums\ReviewFormType;
use App\Enums\ReviewSubmissionStatus;
use App\Enums\UserRole;
use App\Exceptions\OfficialReviewFormGenerationException;
use App\Models\ApplicationDocument;
use App\Models\ResearchApplication;
use App\Models\ReviewComment;
use App\Models\ReviewerAssignment;
use App\Models\ReviewFormSubmission;
use App\Models\ReviewSubmission;
use App\Models\User;
use App\Notifications\DashboardUpdateNotification;
use App\Services\AuditLogService;
use App\Services\Settings\DeadlineProcessAvailability;
use App\Support\ReviewFormCatalog;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Applies assignment-owned Reviewer writes without exposing confidential content in audit metadata.
 */
class ReviewerWorkflowService
{
    public function __construct(
        private readonly DeadlineProcessAvailability $deadlines,
        private readonly AuditLogService $auditLog,
        private readonly OfficialReviewFormArtifactService $officialForms,
    ) {}

    /** @param array<string, mixed> $payload */
    public function saveForm(
        User $actor,
        ReviewerAssignment $assignment,
        ReviewFormType $type,
        array $payload,
        bool $complete,
    ): ReviewFormSubmission {
        return DB::transaction(function () use ($actor, $assignment, $type, $payload, $complete): ReviewFormSubmission {
            $locked = $this->lockedAssignment($assignment);
            $this->authorizeWritable($actor, $locked);
            $this->assertReviewWindowOpen($locked);
            $normalized = $this->normalizeFormPayload($type, $payload);
            $existing = $locked->formSubmissions()
                ->where('form_type', $type->value)
                ->lockForUpdate()
                ->first();

            if ($existing?->status === ReviewFormStatus::Final) {
                throw ValidationException::withMessages([
                    'form' => 'This worksheet is locked with the submitted overall review.',
                ])->errorBag('reviewerForm');
            }

            if ($complete) {
                $this->validateFinalForm($type, $normalized);
            }

            $completedAt = $complete ? now() : null;
            $form = $locked->formSubmissions()->updateOrCreate(
                ['form_type' => $type->value],
                [
                    ...$normalized,
                    'catalog_version' => null,
                    'catalog_snapshot' => null,
                    'finalized_payload_snapshot' => null,
                    'finalized_context_snapshot' => null,
                    'status' => $complete ? ReviewFormStatus::Completed->value : ReviewFormStatus::Draft->value,
                    'review_date' => null,
                    'completed_at' => $completedAt,
                    'finalized_at' => null,
                ],
            );

            $this->markInReview($locked);
            $this->auditLog->record($actor, $complete ? 'review.form_completed' : 'review.form_draft_saved', $locked->researchApplication, [
                'assignment_id' => $locked->id,
                'form_type' => $type->value,
                'form_status' => $form->status->value,
            ]);

            return $form->refresh()->load('artifact');
        }, 3);
    }

    /** @param array<string, mixed> $payload */
    public function addComment(
        User $actor,
        ReviewerAssignment $assignment,
        array $payload,
    ): ReviewComment {
        return DB::transaction(function () use ($actor, $assignment, $payload): ReviewComment {
            $locked = $this->lockedAssignment($assignment);
            $this->authorizeWritable($actor, $locked);
            $this->assertReviewWindowOpen($locked);

            $scope = filled($payload['application_document_id'] ?? null)
                ? ReviewCommentScope::Document
                : ReviewCommentScope::Overall;
            $document = $this->resolveCommentDocument($locked, $scope, $payload['application_document_id'] ?? null);
            $comment = $locked->comments()->create([
                'application_document_id' => $document?->id,
                'scope' => $scope->value,
                'category' => $payload['category'],
                'page_number' => null,
                'body' => trim($payload['body']),
            ]);

            $this->markInReview($locked);
            $this->auditLog->record($actor, 'review.comment_added', $locked->researchApplication, [
                'assignment_id' => $locked->id,
                'comment_id' => $comment->id,
                'scope' => $scope->value,
            ]);

            return $comment;
        }, 3);
    }

    public function removeComment(
        User $actor,
        ReviewerAssignment $assignment,
        ReviewComment $comment,
    ): void {
        DB::transaction(function () use ($actor, $assignment, $comment): void {
            $locked = $this->lockedAssignment($assignment);
            $this->authorizeWritable($actor, $locked);
            $this->assertReviewWindowOpen($locked);
            $lockedComment = ReviewComment::query()->whereKey($comment->id)->lockForUpdate()->firstOrFail();

            if ($lockedComment->reviewer_assignment_id !== $locked->id) {
                abort(404);
            }

            $commentId = $lockedComment->id;
            $scope = $lockedComment->scope->value;
            $lockedComment->delete();
            $this->auditLog->record($actor, 'review.comment_removed', $locked->researchApplication, [
                'assignment_id' => $locked->id,
                'comment_id' => $commentId,
                'scope' => $scope,
            ]);
        }, 3);
    }

    /** @param array<string, mixed> $payload */
    public function updateComment(
        User $actor,
        ReviewerAssignment $assignment,
        ReviewComment $comment,
        array $payload,
    ): ReviewComment {
        return DB::transaction(function () use ($actor, $assignment, $comment, $payload): ReviewComment {
            $locked = $this->lockedAssignment($assignment);
            $this->authorizeWritable($actor, $locked);
            $this->assertReviewWindowOpen($locked);
            $lockedComment = ReviewComment::query()->whereKey($comment->id)->lockForUpdate()->firstOrFail();

            if ($lockedComment->reviewer_assignment_id !== $locked->id) {
                abort(404);
            }

            $scope = filled($payload['application_document_id'] ?? null)
                ? ReviewCommentScope::Document
                : ReviewCommentScope::Overall;
            $document = $this->resolveCommentDocument($locked, $scope, $payload['application_document_id'] ?? null);
            $lockedComment->update([
                'application_document_id' => $document?->id,
                'scope' => $scope->value,
                'category' => $payload['category'],
                'page_number' => null,
                'body' => trim($payload['body']),
            ]);
            $this->auditLog->record($actor, 'review.comment_updated', $locked->researchApplication, [
                'assignment_id' => $locked->id,
                'comment_id' => $lockedComment->id,
                'scope' => $scope->value,
            ]);

            return $lockedComment->refresh();
        }, 3);
    }

    public function changeCommentStatus(
        User $actor,
        ReviewerAssignment $assignment,
        ReviewComment $comment,
        string $status,
    ): ReviewComment {
        return DB::transaction(function () use ($actor, $assignment, $comment, $status): ReviewComment {
            $locked = $this->lockedAssignment($assignment);
            $this->authorizeWritable($actor, $locked);
            $this->assertReviewWindowOpen($locked);
            $lockedComment = ReviewComment::query()->whereKey($comment->id)->lockForUpdate()->firstOrFail();

            if ($lockedComment->reviewer_assignment_id !== $locked->id) {
                abort(404);
            }

            $previous = $lockedComment->status;

            if ($previous === $status) {
                return $lockedComment;
            }

            $changedAt = now();
            $lockedComment->update([
                'status' => $status,
                'resolved_at' => $status === 'resolved' ? $changedAt : null,
                'resolved_by_user_id' => $status === 'resolved' ? $actor->id : null,
            ]);
            DB::table('review_comment_status_changes')->insert([
                'review_comment_id' => $lockedComment->id,
                'actor_user_id' => $actor->id,
                'from_status' => $previous,
                'to_status' => $status,
                'changed_at' => $changedAt,
                'created_at' => $changedAt,
                'updated_at' => $changedAt,
            ]);
            $this->auditLog->record($actor, 'review.comment_status_changed', $locked->researchApplication, [
                'assignment_id' => $locked->id,
                'comment_id' => $lockedComment->id,
                'from_status' => $previous,
                'to_status' => $status,
            ]);

            return $lockedComment->refresh();
        }, 3);
    }

    public function saveDecision(
        User $actor,
        ReviewerAssignment $assignment,
        ?ReviewDecision $decision,
        ?string $decisionComment,
        bool $submit,
    ): ReviewSubmission {
        $storedPaths = [];

        try {
            // PDF writes cannot participate in the database transaction, so do not retry
            // automatically and remove every exact path if either artifact cannot commit.
            return DB::transaction(function () use (
                $actor,
                $assignment,
                $decision,
                $decisionComment,
                $submit,
                &$storedPaths,
            ): ReviewSubmission {
                $locked = $this->lockedAssignment($assignment);
                $this->authorizeWritable($actor, $locked);
                $this->assertReviewWindowOpen($locked);
                $finalForms = collect();

                if ($submit) {
                    $finalForms = $this->finalFormsForSubmission($locked, $decision, $decisionComment);
                }

                $submittedAt = $submit ? now() : null;
                $submission = $locked->reviewSubmission()->updateOrCreate([], [
                    'status' => $submit ? ReviewSubmissionStatus::Submitted->value : ReviewSubmissionStatus::Draft->value,
                    'decision' => $decision?->value,
                    'decision_comment' => filled($decisionComment) ? trim((string) $decisionComment) : null,
                    'submitted_at' => $submittedAt,
                ]);

                if (! $submit) {
                    $this->markInReview($locked);
                    $this->auditLog->record($actor, 'review.decision_draft_saved', $locked->researchApplication, [
                        'assignment_id' => $locked->id,
                        'decision' => $decision?->value,
                    ]);

                    return $submission->refresh();
                }

                // Complete worksheets become immutable snapshots only inside the same transaction as the decision.
                $finalForms = $this->finalizeFormsForDecision($actor, $locked, $finalForms, $submittedAt);

                $comments = $locked->comments()
                    ->with('document.requirement:id,name')
                    ->orderBy('created_at')
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();
                $finalReview = $this->finalizedReviewContext($submission, $comments, $submittedAt);
                $generatedArtifacts = collect();

                foreach ($finalForms as $form) {
                    $latestVersion = $form->artifacts()
                        ->lockForUpdate()
                        ->orderByDesc('artifact_version')
                        ->value('artifact_version');
                    $artifactVersion = ((int) $latestVersion) + 1;
                    $context = [
                        ...(array) $form->finalized_context_snapshot,
                        'final_review' => $finalReview,
                    ];
                    $artifactData = $this->officialForms->renderAndStore(
                        $form->form_type,
                        (array) $form->finalized_payload_snapshot,
                        $context,
                        $artifactVersion,
                    );
                    $storedPaths[] = $artifactData['stored_file_path'];
                    $generatedArtifacts->push($form->artifacts()->create([
                        ...$artifactData,
                        'artifact_version' => $artifactVersion,
                        'status' => ReviewFormArtifactStatus::Ready->value,
                        'generated_at' => $submittedAt,
                    ]));
                }

                foreach ($finalForms as $form) {
                    $currentArtifactId = $generatedArtifacts
                        ->firstWhere('review_form_submission_id', $form->id)?->id;
                    $form->artifacts()
                        ->where('status', ReviewFormArtifactStatus::Ready->value)
                        ->where('id', '!=', $currentArtifactId)
                        ->update(['status' => ReviewFormArtifactStatus::Superseded->value]);
                }

                $locked->update([
                    'assignment_status' => ReviewerAssignmentStatus::DecisionSubmitted->value,
                    'submitted_at' => $submittedAt,
                ]);

                $application = ResearchApplication::query()
                    ->whereKey($locked->research_application_id)
                    ->lockForUpdate()
                    ->firstOrFail();
                $allSubmitted = $application->reviewerAssignments()
                    ->current()
                    ->where('review_type', $locked->review_type)
                    ->where('review_cycle', $locked->review_cycle)
                    ->lockForUpdate()
                    ->get()
                    ->every(fn (ReviewerAssignment $item): bool => $item->assignment_status === ReviewerAssignmentStatus::DecisionSubmitted);

                if ($allSubmitted) {
                    $application->update([
                        'application_status' => ApplicationStatus::ReviewSubmittedPendingRelease->value,
                        'current_stage' => ApplicationStage::DecisionRelease->value,
                        'status_updated_at' => now(),
                    ]);
                    $this->notifyResLeads(
                        'Reviewer decisions ready for release processing',
                        'All required reviewer decisions for an application have been submitted.',
                        $application,
                    );
                }

                $this->auditLog->record($actor, 'review.decision_submitted', $application, [
                    'assignment_id' => $locked->id,
                    'decision' => $decision?->value,
                    'artifact_ids' => $generatedArtifacts->pluck('id')->all(),
                    'artifact_versions' => $generatedArtifacts->pluck('artifact_version')->all(),
                    'all_reviewers_submitted' => $allSubmitted,
                    'result' => $application->application_status->value,
                ]);

                return $submission->refresh();
            }, 1);
        } catch (OfficialReviewFormGenerationException $exception) {
            foreach ($storedPaths as $storedPath) {
                Storage::disk('local')->delete($storedPath);
            }

            report($exception);

            throw ValidationException::withMessages([
                'review' => 'The official completed PDFs could not be generated securely. The review was not submitted; your completed worksheets remain editable.',
            ])->errorBag('reviewDecision');
        } catch (Throwable $exception) {
            foreach ($storedPaths as $storedPath) {
                Storage::disk('local')->delete($storedPath);
            }

            throw $exception;
        }
    }

    private function lockedAssignment(ReviewerAssignment $assignment): ReviewerAssignment
    {
        $applicationId = ReviewerAssignment::query()
            ->whereKey($assignment->id)
            ->value('research_application_id');
        $application = ResearchApplication::query()
            ->whereKey($applicationId)
            ->lockForUpdate()
            ->firstOrFail();
        $locked = ReviewerAssignment::query()
            ->whereKey($assignment->id)
            ->lockForUpdate()
            ->firstOrFail();

        return $locked->setRelation('researchApplication', $application);
    }

    private function authorizeWritable(User $actor, ReviewerAssignment $assignment): void
    {
        Gate::forUser($actor)->authorize('work', $assignment);
    }

    private function assertReviewWindowOpen(ReviewerAssignment $assignment): void
    {
        $revisionReview = $assignment->review_type === 'revision_review'
            || (int) $assignment->review_cycle > 0;

        try {
            $this->deadlines->assertOpen(
                $revisionReview ? 'reviewing-revision-period' : 'reviewer-submission',
                UserRole::Reviewer,
                $revisionReview ? 'Reviewing of revision' : 'Reviewer submission',
            );
        } catch (ValidationException $exception) {
            throw $exception->errorBag('reviewerWorkflow');
        }
    }

    private function markInReview(ReviewerAssignment $assignment): void
    {
        if ($assignment->assignment_status === ReviewerAssignmentStatus::Pending) {
            $assignment->update(['assignment_status' => ReviewerAssignmentStatus::InReview->value]);
        }
    }

    /** @return array<string, mixed> */
    private function finalizedFormContext(User $actor, ReviewerAssignment $assignment, mixed $finalizedAt): array
    {
        $application = $assignment->researchApplication;

        return [
            'application_id' => $application->id,
            'application_code' => $application->application_code,
            'research_title' => $application->research_title,
            'institution' => $application->institution,
            'review_classification' => filled($application->review_type)
                ? Str::headline((string) $application->review_type)
                : 'Not specified',
            // Applicant and Adviser identities are intentionally excluded from this blind artifact.
            'proponent_label' => 'WITHHELD - BLIND REVIEW',
            'reviewer_assignment_id' => $assignment->id,
            'assignment_sequence' => $assignment->assignment_sequence,
            'reviewer_user_id' => $actor->id,
            'reviewer_name' => $actor->name,
            'primary_reviewer_label' => 'Not designated in ECRATS',
            'received_at' => $assignment->assigned_at?->toIso8601String(),
            'received_date' => $assignment->assigned_at?->format('m/d/y') ?? 'Not recorded',
            'review_date' => $finalizedAt->format('m/d/y'),
            'finalized_at' => $finalizedAt->toIso8601String(),
            'attestation' => [
                'method' => 'authenticated_electronic_attestation',
                'version' => 1,
                'actor_user_id' => $actor->id,
                'actor_name' => $actor->name,
                'statement' => 'The authenticated reviewer finalized this response as their official review record.',
                'attested_at' => $finalizedAt->toIso8601String(),
            ],
        ];
    }

    /**
     * @param  Collection<int, ReviewComment>  $comments
     * @return array<string, mixed>
     */
    private function finalizedReviewContext(
        ReviewSubmission $submission,
        Collection $comments,
        mixed $submittedAt,
    ): array {
        return [
            'review_submission_id' => $submission->id,
            'decision' => $submission->decision?->value,
            'decision_label' => $submission->decision?->label(),
            'decision_comment' => $submission->decision_comment,
            'submitted_at' => $submittedAt->toIso8601String(),
            'submitted_at_display' => $submittedAt->format('M j, Y g:i A'),
            // Comments have no form-type discriminator, so the complete frozen assignment
            // record is applicable to both official forms and is preserved losslessly.
            'assignment_comments' => $comments->map(function (ReviewComment $comment): array {
                $documentLabel = $comment->document?->requirement?->name;
                $reference = match ($comment->scope) {
                    ReviewCommentScope::Overall => 'Overall review',
                    ReviewCommentScope::Document => $documentLabel ?: 'Submitted document #'.$comment->application_document_id,
                    ReviewCommentScope::Page => ($documentLabel ?: 'Submitted document #'.$comment->application_document_id)
                        .' - page '.($comment->page_number ?? 'not recorded'),
                };

                return [
                    'id' => $comment->id,
                    'scope' => $comment->scope->value,
                    'scope_label' => $comment->scope->label(),
                    'category' => $comment->category->value,
                    'category_label' => $comment->category->label(),
                    'application_document_id' => $comment->application_document_id,
                    'page_number' => $comment->page_number,
                    'reference' => $reference,
                    'status' => $comment->status,
                    'body' => $comment->body,
                    'created_at' => $comment->created_at?->toIso8601String(),
                    'created_at_display' => $comment->created_at?->format('M j, Y g:i A'),
                    'updated_at' => $comment->updated_at?->toIso8601String(),
                    'updated_at_display' => $comment->updated_at?->format('M j, Y g:i A'),
                ];
            })->values()->all(),
        ];
    }

    /** @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function normalizeFormPayload(ReviewFormType $type, array $payload): array
    {
        $knownQuestions = ReviewFormCatalog::questions($type);
        $allowedAnswers = array_keys(ReviewFormCatalog::answers($type));
        $responses = $payload['responses'] ?? [];
        $unknownKeys = array_diff(array_keys($responses), array_keys($knownQuestions));

        if ($unknownKeys !== []) {
            throw ValidationException::withMessages([
                'responses' => 'The reviewer form contains an unrecognized question.',
            ])->errorBag('reviewerForm');
        }

        $normalizedResponses = [];

        foreach ($responses as $key => $response) {
            $answer = $response['answer'] ?? null;

            if (filled($answer) && ! in_array($answer, $allowedAnswers, true)) {
                throw ValidationException::withMessages([
                    "responses.{$key}.answer" => 'The selected answer is not valid for this reviewer form.',
                ])->errorBag('reviewerForm');
            }

            $normalizedResponses[$key] = [
                'answer' => filled($answer) ? $answer : null,
                'comment' => $type === ReviewFormType::InformedConsent
                    ? null
                    : (filled($response['comment'] ?? null) ? trim($response['comment']) : null),
            ];
        }

        $consentRequired = $type === ReviewFormType::InformedConsent
            ? ($payload['consent_required'] ?? null)
            : null;
        if ($type === ReviewFormType::InformedConsent && $consentRequired === false) {
            // The gate and explanation are the traceable source; hidden dependent answers are never retained.
            $normalizedResponses = [];
        }

        return [
            'responses' => $normalizedResponses ?: null,
            'consent_required' => $consentRequired,
            'consent_not_required_explanation' => filled($payload['consent_not_required_explanation'] ?? null)
                ? trim($payload['consent_not_required_explanation'])
                : null,
            'recommendation' => $payload['recommendation'] ?? null,
            'recommendation_comments' => filled($payload['recommendation_comments'] ?? null)
                ? trim($payload['recommendation_comments'])
                : null,
        ];
    }

    /** @param array<string, mixed> $payload */
    private function validateFinalForm(ReviewFormType $type, array $payload): void
    {
        $errors = [];
        $requiresAnswers = true;

        if ($type === ReviewFormType::InformedConsent) {
            if ($payload['consent_required'] === null) {
                $errors['consent_required'] = 'State whether informed consent is necessary.';
            } elseif ($payload['consent_required'] === false) {
                $requiresAnswers = false;

                if (mb_strlen((string) $payload['consent_not_required_explanation']) < 10) {
                    $errors['consent_not_required_explanation'] = 'Explain why informed consent is not necessary.';
                }
            }
        }

        if ($requiresAnswers) {
            foreach (ReviewFormCatalog::questions($type) as $key => $question) {
                if (blank($payload['responses'][$key]['answer'] ?? null)) {
                    $errors["responses.{$key}.answer"] = 'Answer every applicable reviewer form item.';
                }
            }
        }

        if (blank($payload['recommendation'])) {
            $errors['recommendation'] = 'Select a recommendation.';
        }

        if ($this->nonWhitespaceLength((string) $payload['recommendation_comments']) < 15) {
            $errors['recommendation_comments'] = 'Enter recommendation comments with at least 15 non-whitespace characters.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors)->errorBag('reviewerForm');
        }
    }

    private function resolveCommentDocument(
        ReviewerAssignment $assignment,
        ReviewCommentScope $scope,
        mixed $documentId,
    ): ?ApplicationDocument {
        if ($scope === ReviewCommentScope::Overall) {
            return null;
        }

        $document = ApplicationDocument::query()
            ->whereKey((int) $documentId)
            ->where('research_application_id', $assignment->research_application_id)
            ->where('is_current', true)
            ->first();

        if (! $document) {
            throw ValidationException::withMessages([
                'application_document_id' => 'Select a document from this assigned application.',
            ])->errorBag('reviewComment');
        }

        return $document;
    }

    /** @return Collection<int, ReviewFormSubmission> */
    private function finalFormsForSubmission(
        ReviewerAssignment $assignment,
        ?ReviewDecision $decision,
        ?string $decisionComment,
    ): Collection {
        $errors = [];

        if (! $decision) {
            $errors['decision'] = 'Select a final review decision.';
        }

        if (mb_strlen(trim((string) $decisionComment)) < 10) {
            $errors['decision_comment'] = 'Provide a final decision comment of at least 10 characters.';
        }

        $finalForms = $assignment->formSubmissions()
            ->whereIn('form_type', array_map(
                fn (ReviewFormType $type): string => $type->value,
                ReviewFormType::cases(),
            ))
            ->lockForUpdate()
            ->get()
            ->keyBy(fn (ReviewFormSubmission $form): string => $form->form_type->value);

        foreach (ReviewFormType::cases() as $type) {
            $form = $finalForms->get($type->value);

            if (! $form
                || $form->status !== ReviewFormStatus::Completed) {
                $errors['forms'] = 'Complete both required reviewer worksheets before submitting the decision.';
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors)->errorBag('reviewDecision');
        }

        return $finalForms->values();
    }

    /**
     * Snapshot current completed worksheet values at the overall final-submission boundary.
     *
     * @param  Collection<int, ReviewFormSubmission>  $forms
     * @return Collection<int, ReviewFormSubmission>
     */
    private function finalizeFormsForDecision(
        User $actor,
        ReviewerAssignment $assignment,
        Collection $forms,
        mixed $finalizedAt,
    ): Collection {
        return $forms->map(function (ReviewFormSubmission $form) use ($actor, $assignment, $finalizedAt): ReviewFormSubmission {
            $payload = $this->normalizeFormPayload($form->form_type, [
                'responses' => $form->responses ?? [],
                'consent_required' => $form->consent_required,
                'consent_not_required_explanation' => $form->consent_not_required_explanation,
                'recommendation' => $form->recommendation?->value,
                'recommendation_comments' => $form->recommendation_comments,
            ]);
            $this->validateFinalForm($form->form_type, $payload);
            $catalogSnapshot = [
                'form_type' => $form->form_type->value,
                'form_code' => $form->form_type->code(),
                'form_label' => $form->form_type->label(),
                'items' => ReviewFormCatalog::items($form->form_type),
                'questions' => ReviewFormCatalog::questions($form->form_type),
                'answers' => ReviewFormCatalog::answers($form->form_type),
                'template' => ReviewFormCatalog::template($form->form_type),
            ];
            $form->update([
                'catalog_version' => ReviewFormCatalog::CATALOG_VERSION,
                'catalog_snapshot' => $catalogSnapshot,
                'finalized_payload_snapshot' => $payload,
                'finalized_context_snapshot' => $this->finalizedFormContext($actor, $assignment, $finalizedAt),
                'status' => ReviewFormStatus::Final->value,
                'review_date' => $finalizedAt->toDateString(),
                'finalized_at' => $finalizedAt,
            ]);

            return $form->refresh();
        })->values();
    }

    private function nonWhitespaceLength(string $value): int
    {
        return mb_strlen((string) preg_replace('/\s+/u', '', $value));
    }

    private function notifyResLeads(
        string $title,
        string $message,
        ResearchApplication $application,
    ): void {
        User::query()
            ->where('role', UserRole::ResLead->value)
            ->where('account_status', AccountStatus::Active->value)
            ->select('id')
            ->eachById(function (User $resLead) use ($title, $message, $application): void {
                $resLead->notify(new DashboardUpdateNotification([
                    'title' => $title,
                    'message' => $message,
                    'icon' => 'file-search',
                    'tone' => 'blue',
                    'route' => 'res.applications.show',
                    'route_parameters' => ['researchApplication' => $application->id],
                ]));
            }, 100);
    }
}
