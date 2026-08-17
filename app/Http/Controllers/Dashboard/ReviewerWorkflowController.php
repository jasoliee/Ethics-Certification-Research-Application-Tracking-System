<?php

namespace App\Http\Controllers\Dashboard;

use App\Enums\ReviewDecision;
use App\Enums\ReviewFormStatus;
use App\Enums\ReviewFormType;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reviewer\SaveReviewerDecisionRequest;
use App\Http\Requests\Reviewer\SaveReviewerFormRequest;
use App\Http\Requests\Reviewer\StoreReviewCommentRequest;
use App\Models\ReviewComment;
use App\Models\ReviewerAssignment;
use App\Services\Applications\ReviewerWorkflowService;
use App\Services\Settings\DeadlineProcessAvailability;
use App\Support\ReviewFormCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ReviewerWorkflowController extends Controller
{
    private const COMMENT_PAGE_SIZE = 20;

    public function saveForm(
        SaveReviewerFormRequest $request,
        ReviewerAssignment $reviewerAssignment,
        ReviewFormType $reviewFormType,
        ReviewerWorkflowService $workflow,
    ): RedirectResponse|JsonResponse {
        $complete = $request->validated('intent') === 'submit';
        $form = $workflow->saveForm(
            $request->user(),
            $reviewerAssignment,
            $reviewFormType,
            $request->validated(),
            $complete,
        );

        if ($request->expectsJson()) {
            $form->loadMissing('artifact');
            $totalItems = count(ReviewFormCatalog::questions($reviewFormType));
            $answeredItems = in_array($form->status, [ReviewFormStatus::Completed, ReviewFormStatus::Final], true)
                ? $totalItems
                : collect($form->responses ?? [])->filter(
                    fn (array $response): bool => filled($response['answer'] ?? null),
                )->count();

            return response()->json(['data' => [
                'id' => $form->id,
                'form_type' => $form->form_type->value,
                'status' => $form->status->value,
                'finalized_at' => $form->finalized_at?->toIso8601String(),
                'completed_at' => $form->completed_at?->toIso8601String(),
                'answered_items' => $answeredItems,
                'total_items' => $totalItems,
                'artifact' => $form->artifact ? [
                    'id' => $form->artifact->id,
                    'status' => $form->artifact->status->value,
                    'version' => $form->artifact->artifact_version,
                    'sha256' => $form->artifact->sha256,
                    'preview_url' => route('reviewer.assignments.forms.artifacts.preview', [
                        $reviewerAssignment,
                        $form,
                        $form->artifact,
                    ]),
                    'download_url' => route('reviewer.assignments.forms.artifacts.download', [
                        $reviewerAssignment,
                        $form,
                        $form->artifact,
                    ]),
                ] : null,
            ]]);
        }

        return back()->with('status', $complete
            ? $reviewFormType->label().' completed. You may edit it until submitting the overall review decision.'
            : $reviewFormType->label().' draft saved.');
    }

    public function addComment(
        StoreReviewCommentRequest $request,
        ReviewerAssignment $reviewerAssignment,
        ReviewerWorkflowService $workflow,
    ): RedirectResponse|JsonResponse {
        $comment = $workflow->addComment($request->user(), $reviewerAssignment, $request->validated());

        if ($request->expectsJson()) {
            return response()->json(['data' => $this->commentPayload($comment, $reviewerAssignment)], 201);
        }

        return back()->with('status', 'Review comment added.');
    }

    public function olderComments(
        Request $request,
        ReviewerAssignment $reviewerAssignment,
        DeadlineProcessAvailability $deadlines,
    ): JsonResponse {
        Gate::authorize('openWorkspace', $reviewerAssignment);
        $validated = $request->validate([
            'before_id' => ['required', 'integer', 'min:1'],
        ]);
        $reviewWindow = $deadlines->status(
            'reviewer-submission',
            UserRole::Reviewer,
            'Reviewer submission',
        );
        $canWrite = Gate::allows('work', $reviewerAssignment) && $reviewWindow['open'];
        $batch = $reviewerAssignment->comments()
            ->where('id', '<', $validated['before_id'])
            ->with('document:id,original_file_name')
            ->latest('id')
            ->limit(self::COMMENT_PAGE_SIZE + 1)
            ->get();
        $hasOlder = $batch->count() > self::COMMENT_PAGE_SIZE;
        $comments = $batch->take(self::COMMENT_PAGE_SIZE)->values();

        return response()->json(['data' => [
            'items' => $comments->map(fn (ReviewComment $comment): array => [
                'id' => $comment->id,
                'html' => $this->commentHtml($comment, $reviewerAssignment, $canWrite),
            ])->all(),
            'count' => $reviewerAssignment->comments()->count(),
            'has_more' => $hasOlder,
            'next_before_id' => $hasOlder ? $comments->last()?->id : null,
        ]]);
    }

    public function updateComment(
        StoreReviewCommentRequest $request,
        ReviewerAssignment $reviewerAssignment,
        ReviewComment $reviewComment,
        ReviewerWorkflowService $workflow,
    ): RedirectResponse|JsonResponse {
        $comment = $workflow->updateComment(
            $request->user(),
            $reviewerAssignment,
            $reviewComment,
            $request->validated(),
        );

        return $request->expectsJson()
            ? response()->json(['data' => $this->commentPayload($comment, $reviewerAssignment)])
            : back()->with('status', 'Review comment updated.');
    }

    public function changeCommentStatus(
        Request $request,
        ReviewerAssignment $reviewerAssignment,
        ReviewComment $reviewComment,
        ReviewerWorkflowService $workflow,
    ): RedirectResponse|JsonResponse {
        $validated = $request->validate(['status' => ['required', 'in:open,resolved']]);
        $comment = $workflow->changeCommentStatus(
            $request->user(),
            $reviewerAssignment,
            $reviewComment,
            $validated['status'],
        );

        return $request->expectsJson()
            ? response()->json(['data' => $this->commentPayload($comment, $reviewerAssignment)])
            : back()->with('status', $comment->status === 'resolved' ? 'Review comment resolved.' : 'Review comment reopened.');
    }

    public function removeComment(
        Request $request,
        ReviewerAssignment $reviewerAssignment,
        ReviewComment $reviewComment,
        ReviewerWorkflowService $workflow,
    ): RedirectResponse|JsonResponse {
        $workflow->removeComment($request->user(), $reviewerAssignment, $reviewComment);

        if ($request->expectsJson()) {
            return response()->json(['data' => [
                'deleted_id' => $reviewComment->id,
                'count' => $reviewerAssignment->comments()->count(),
            ]]);
        }

        return back()->with('status', 'Review comment removed.');
    }

    /** @return array<string, mixed> */
    private function commentPayload(ReviewComment $comment, ReviewerAssignment $assignment): array
    {
        $comment->loadMissing('document:id,original_file_name');

        return [
            'id' => $comment->id,
            'scope' => $comment->scope->value,
            'category' => $comment->category->value,
            'application_document_id' => $comment->application_document_id,
            'page_number' => $comment->page_number,
            'body' => $comment->body,
            'status' => $comment->status,
            'resolved_at' => $comment->resolved_at?->toIso8601String(),
            'updated_at' => $comment->updated_at?->toIso8601String(),
            // Return server-rendered, escaped markup so asynchronous and fallback responses stay identical.
            'html' => $this->commentHtml($comment, $assignment, true),
            'count' => $assignment->comments()->count(),
        ];
    }

    private function commentHtml(
        ReviewComment $comment,
        ReviewerAssignment $assignment,
        bool $canWrite,
    ): string {
        return view('dashboard.assignments.partials.comment-item', [
            'comment' => $comment,
            'assignment' => $assignment,
            'canWrite' => $canWrite,
        ])->render();
    }

    public function saveDecision(
        SaveReviewerDecisionRequest $request,
        ReviewerAssignment $reviewerAssignment,
        ReviewerWorkflowService $workflow,
    ): RedirectResponse|JsonResponse {
        $submit = $request->validated('intent') === 'submit';
        $decision = filled($request->validated('decision'))
            ? ReviewDecision::from($request->validated('decision'))
            : null;
        $review = $workflow->saveDecision(
            $request->user(),
            $reviewerAssignment,
            $decision,
            $request->validated('decision_comment'),
            $submit,
            $request->validated('submission_token'),
        );

        if ($request->expectsJson()) {
            return response()->json(['data' => [
                'id' => $review->id,
                'status' => $review->status->value,
                'decision' => $review->decision?->value,
                'decision_label' => $review->decision?->label(),
                'submitted_at' => $review->submitted_at?->toIso8601String(),
                'submitted_at_label' => $review->submitted_at?->format('M j, Y g:i A'),
                'message' => $submit
                    ? 'Review submitted successfully and is pending RES release.'
                    : 'Review decision draft saved.',
                'redirect_url' => route('reviewer.assignments.show', $reviewerAssignment),
            ]]);
        }

        return $submit
            ? redirect()->route('reviewer.assignments.show', $reviewerAssignment)
                ->with('status', 'Review submitted successfully and is pending RES release.')
            : back()->with('status', 'Review decision draft saved.');
    }
}
