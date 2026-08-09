<?php

namespace App\Http\Controllers\Dashboard;

use App\Enums\ReviewDecision;
use App\Enums\ReviewFormStatus;
use App\Enums\ReviewFormType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reviewer\SaveReviewerDecisionRequest;
use App\Http\Requests\Reviewer\SaveReviewerFormRequest;
use App\Http\Requests\Reviewer\StoreReviewCommentRequest;
use App\Models\ReviewComment;
use App\Models\ReviewerAssignment;
use App\Services\Applications\ReviewerWorkflowService;
use App\Support\ReviewFormCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ReviewerWorkflowController extends Controller
{
    public function saveForm(
        SaveReviewerFormRequest $request,
        ReviewerAssignment $reviewerAssignment,
        ReviewFormType $reviewFormType,
        ReviewerWorkflowService $workflow,
    ): RedirectResponse|JsonResponse {
        $final = $request->validated('intent') === 'final';
        $form = $workflow->saveForm(
            $request->user(),
            $reviewerAssignment,
            $reviewFormType,
            $request->validated(),
            $final,
        );

        if ($request->expectsJson()) {
            $form->loadMissing('artifact');
            $totalItems = count(ReviewFormCatalog::questions($reviewFormType));
            $answeredItems = $form->status === ReviewFormStatus::Final
                ? $totalItems
                : collect($form->responses ?? [])->filter(
                    fn (array $response): bool => filled($response['answer'] ?? null),
                )->count();

            return response()->json(['data' => [
                'id' => $form->id,
                'form_type' => $form->form_type->value,
                'status' => $form->status->value,
                'finalized_at' => $form->finalized_at?->toIso8601String(),
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

        return back()->with('status', $final
            ? $reviewFormType->label().' finalized.'
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
            return response()->json(null, 204);
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
            'html' => view('dashboard.assignments.partials.comment-item', [
                'comment' => $comment,
                'assignment' => $assignment,
                'canWrite' => true,
            ])->render(),
            'count' => $assignment->comments()->count(),
        ];
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
        );

        if ($request->expectsJson()) {
            return response()->json(['data' => [
                'id' => $review->id,
                'status' => $review->status->value,
                'decision' => $review->decision?->value,
                'submitted_at' => $review->submitted_at?->toIso8601String(),
            ]]);
        }

        return $submit
            ? redirect()->route('reviewer.assignments.show', $reviewerAssignment)
                ->with('status', 'Review submitted successfully and is pending RES release.')
            : back()->with('status', 'Review decision draft saved.');
    }
}
