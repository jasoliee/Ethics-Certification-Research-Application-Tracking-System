<?php

namespace App\Services\Applications;

use App\Enums\ReviewFormArtifactStatus;
use App\Enums\ReviewFormStatus;
use App\Models\ReviewComment;
use App\Models\ReviewerAssignment;
use App\Models\ReviewFormArtifact;
use App\Models\ReviewFormSubmission;
use App\Models\ReviewSubmission;
use App\Models\ReviewSubmissionVersion;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ReviewSubmissionVersionService
{
    public const SNAPSHOT_SCHEMA_VERSION = 1;

    /**
     * @param  Collection<int, ReviewFormSubmission>  $forms
     * @param  Collection<int, ReviewComment>  $comments
     * @param  Collection<int, ReviewFormArtifact>  $artifacts
     */
    public function create(
        User $actor,
        ReviewerAssignment $assignment,
        ReviewSubmission $submission,
        Collection $forms,
        Collection $comments,
        Collection $artifacts,
        mixed $submittedAt,
        ?string $submissionToken = null,
    ): ReviewSubmissionVersion {
        $token = $submissionToken ?: (string) Str::uuid();
        $requestHash = $this->workingRequestHash(
            $assignment,
            $submission->decision?->value,
            $submission->decision_comment,
        );
        $existing = $submission->versions()
            ->where('submission_token', $token)
            ->lockForUpdate()
            ->first();

        if ($existing) {
            if (! hash_equals($existing->request_sha256, $requestHash)) {
                throw ValidationException::withMessages([
                    'submission_token' => 'This submission token was already used for different review content. Refresh and try again.',
                ])->errorBag('reviewDecision');
            }

            return $existing;
        }

        $snapshot = $this->snapshot($assignment, $submission, $forms, $comments, $artifacts);
        $encoded = $this->canonicalJson($snapshot);
        $nextVersion = ((int) $submission->versions()->lockForUpdate()->max('version_number')) + 1;
        $version = $submission->versions()->create([
            'reviewer_assignment_id' => $assignment->id,
            'version_number' => $nextVersion,
            'submission_token' => $token,
            'decision' => $submission->decision->value,
            'decision_comment' => $submission->decision_comment,
            'snapshot_schema_version' => self::SNAPSHOT_SCHEMA_VERSION,
            'payload_snapshot' => $snapshot,
            'payload_sha256' => hash('sha256', $encoded),
            'request_sha256' => $requestHash,
            'submitted_by_user_id' => $actor->id,
            'submitted_at' => $submittedAt,
        ]);

        if ($artifacts->isNotEmpty()) {
            ReviewFormArtifact::query()
                ->whereKey($artifacts->pluck('id'))
                ->update(['review_submission_version_id' => $version->id]);
        }
        $submission->update([
            'current_version_id' => $version->id,
            'draft_decision' => $submission->decision?->value,
            'draft_decision_comment' => $submission->decision_comment,
            'has_unsubmitted_changes' => false,
        ]);

        return $version->refresh();
    }

    /**
     * Lazily normalizes fixtures and pre-migration submissions so every release
     * still points at immutable evidence.
     */
    public function ensureCurrent(ReviewSubmission $submission): ReviewSubmissionVersion
    {
        $locked = ReviewSubmission::query()->whereKey($submission->id)->lockForUpdate()->firstOrFail();
        if ($locked->current_version_id) {
            return $locked->currentVersion()->firstOrFail();
        }

        $latest = $locked->versions()->orderByDesc('version_number')->first();
        if ($latest) {
            $locked->update(['current_version_id' => $latest->id]);

            return $latest;
        }

        $assignment = ReviewerAssignment::query()->whereKey($locked->reviewer_assignment_id)->lockForUpdate()->firstOrFail();
        $forms = $assignment->formSubmissions()->orderBy('id')->lockForUpdate()->get();
        $comments = $assignment->comments()->withTrashed()->orderBy('id')->lockForUpdate()->get();
        $artifacts = ReviewFormArtifact::query()
            ->whereIn('review_form_submission_id', $forms->pluck('id'))
            ->where('status', ReviewFormArtifactStatus::Ready->value)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
        $actor = User::withTrashed()->findOrFail($assignment->reviewer_user_id);

        return $this->create(
            $actor,
            $assignment,
            $locked,
            $forms,
            $comments,
            $artifacts,
            $locked->submitted_at ?? now(),
        );
    }

    /**
     * Clear a false dirty flag when the current working data still exactly matches
     * the immutable submitted version. This also repairs worksheet rows downgraded
     * by a stale navigation draft request.
     */
    public function normalizeUnchangedSubmission(
        ReviewerAssignment $assignment,
        ReviewSubmission $submission,
    ): bool {
        $version = $submission->currentVersion;
        if (! $submission->has_unsubmitted_changes || ! $version) {
            return false;
        }

        $workingHash = $this->workingRequestHash(
            $assignment,
            $submission->draft_decision?->value,
            $submission->draft_decision_comment,
        );
        if (! hash_equals($version->request_sha256, $workingHash)) {
            return false;
        }

        collect((array) data_get($version->payload_snapshot, 'forms', []))
            ->each(function (array $snapshot) use ($assignment, $version): void {
                $form = $assignment->formSubmissions()
                    ->whereKey((int) data_get($snapshot, 'id'))
                    ->lockForUpdate()
                    ->first();
                if (! $form) {
                    return;
                }

                $form->update([
                    'catalog_version' => data_get($snapshot, 'catalog_version'),
                    'catalog_snapshot' => data_get($snapshot, 'catalog'),
                    'finalized_payload_snapshot' => data_get($snapshot, 'payload'),
                    'finalized_context_snapshot' => data_get($snapshot, 'context'),
                    'status' => ReviewFormStatus::Final->value,
                    'review_date' => $version->submitted_at?->toDateString(),
                    'completed_at' => $version->submitted_at,
                    'finalized_at' => $version->submitted_at,
                ]);
            });

        $submission->update(['has_unsubmitted_changes' => false]);

        return true;
    }

    public function workingRequestHash(
        ReviewerAssignment $assignment,
        ?string $decision,
        ?string $decisionComment,
    ): string {
        $forms = $assignment->formSubmissions()
            ->orderBy('form_type')
            ->get()
            ->map(fn (ReviewFormSubmission $form): array => [
                'form_type' => $form->form_type->value,
                'responses' => $form->responses,
                'consent_required' => $form->consent_required,
                'consent_not_required_explanation' => $form->consent_not_required_explanation,
                'recommendation' => $form->recommendation?->value,
                'recommendation_comments' => $form->recommendation_comments,
            ])->all();
        $comments = $assignment->comments()
            ->withTrashed()
            ->orderBy('id')
            ->get()
            ->map(fn (ReviewComment $comment): array => [
                'id' => $comment->id,
                'document_id' => $comment->application_document_id,
                'scope' => $comment->scope->value,
                'category' => $comment->category->value,
                'page_number' => $comment->page_number,
                'body' => $comment->body,
                'status' => $comment->status,
                'deleted_at' => $comment->deleted_at?->toIso8601String(),
            ])->all();

        return hash('sha256', $this->canonicalJson([
            'assignment_id' => $assignment->id,
            'decision' => $decision,
            'decision_comment' => filled($decisionComment) ? trim((string) $decisionComment) : null,
            'forms' => $forms,
            'comments' => $comments,
        ]));
    }

    /** @return array<string, mixed> */
    private function snapshot(
        ReviewerAssignment $assignment,
        ReviewSubmission $submission,
        Collection $forms,
        Collection $comments,
        Collection $artifacts,
    ): array {
        return [
            'schema_version' => self::SNAPSHOT_SCHEMA_VERSION,
            'assignment_id' => $assignment->id,
            'review_cycle' => (int) $assignment->review_cycle,
            'review_type' => $assignment->review_type,
            'decision' => $submission->decision?->value,
            'decision_comment' => $submission->decision_comment,
            'forms' => $forms->sortBy('id')->map(fn (ReviewFormSubmission $form): array => [
                'id' => $form->id,
                'form_type' => $form->form_type->value,
                'catalog_version' => $form->catalog_version,
                'catalog' => $form->catalog_snapshot,
                'payload' => $form->finalized_payload_snapshot,
                'context' => $form->finalized_context_snapshot,
            ])->values()->all(),
            'comments' => $comments->sortBy('id')->map(fn (ReviewComment $comment): array => [
                'id' => $comment->id,
                'application_document_id' => $comment->application_document_id,
                'scope' => $comment->scope->value,
                'category' => $comment->category->value,
                'page_number' => $comment->page_number,
                'status' => $comment->status,
                'body' => $comment->body,
                'created_at' => $comment->created_at?->toIso8601String(),
                'updated_at' => $comment->updated_at?->toIso8601String(),
                'deleted_at' => $comment->deleted_at?->toIso8601String(),
            ])->values()->all(),
            'artifacts' => $artifacts->sortBy('id')->map(fn (ReviewFormArtifact $artifact): array => [
                'id' => $artifact->id,
                'review_form_submission_id' => $artifact->review_form_submission_id,
                'artifact_version' => $artifact->artifact_version,
                'business_version' => $artifact->business_version,
                'sha256' => $artifact->sha256,
                'template_code' => $artifact->template_code,
                'template_version' => $artifact->template_version,
                'background_id' => $artifact->certificate_background_id,
                'background_sha256' => $artifact->background_sha256,
            ])->values()->all(),
        ];
    }

    /** @param array<string, mixed> $payload */
    private function canonicalJson(array $payload): string
    {
        $sort = function (mixed $value) use (&$sort): mixed {
            if (! is_array($value)) {
                return $value;
            }

            if (! array_is_list($value)) {
                ksort($value);
            }

            return array_map($sort, $value);
        };

        return json_encode($sort($payload), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
