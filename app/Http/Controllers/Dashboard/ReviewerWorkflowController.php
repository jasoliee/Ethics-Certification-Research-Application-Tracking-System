<?php

namespace App\Http\Controllers\Dashboard;

use App\Enums\ReviewDecision;
use App\Enums\ReviewerConflictStatus;
use App\Enums\ReviewFormType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reviewer\DeclareReviewerConflictRequest;
use App\Http\Requests\Reviewer\SaveReviewerDecisionRequest;
use App\Http\Requests\Reviewer\SaveReviewerFormRequest;
use App\Http\Requests\Reviewer\StoreReviewCommentRequest;
use App\Models\ReviewComment;
use App\Models\ReviewerAssignment;
use App\Services\Applications\ReviewerWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ReviewerWorkflowController extends Controller
{
    public function declareConflict(
        DeclareReviewerConflictRequest $request,
        ReviewerAssignment $reviewerAssignment,
        ReviewerWorkflowService $workflow,
    ): RedirectResponse {
        $status = ReviewerConflictStatus::from($request->validated('conflict_status'));
        $workflow->declareConflict($request->user(), $reviewerAssignment, $status);

        return back()->with('status', $status === ReviewerConflictStatus::Cleared
            ? 'Conflict declaration recorded. The blind review workspace is now available.'
            : 'Conflict declaration recorded. RES will handle this assignment.');
    }

    public function saveForm(
        SaveReviewerFormRequest $request,
        ReviewerAssignment $reviewerAssignment,
        ReviewFormType $reviewFormType,
        ReviewerWorkflowService $workflow,
    ): RedirectResponse {
        $final = $request->validated('intent') === 'final';
        $workflow->saveForm(
            $request->user(),
            $reviewerAssignment,
            $reviewFormType,
            $request->validated(),
            $final,
        );

        return back()->with('status', $final
            ? $reviewFormType->label().' finalized.'
            : $reviewFormType->label().' draft saved.');
    }

    public function addComment(
        StoreReviewCommentRequest $request,
        ReviewerAssignment $reviewerAssignment,
        ReviewerWorkflowService $workflow,
    ): RedirectResponse {
        $workflow->addComment($request->user(), $reviewerAssignment, $request->validated());

        return back()->with('status', 'Review comment added.');
    }

    public function removeComment(
        Request $request,
        ReviewerAssignment $reviewerAssignment,
        ReviewComment $reviewComment,
        ReviewerWorkflowService $workflow,
    ): RedirectResponse {
        $workflow->removeComment($request->user(), $reviewerAssignment, $reviewComment);

        return back()->with('status', 'Review comment removed.');
    }

    public function saveDecision(
        SaveReviewerDecisionRequest $request,
        ReviewerAssignment $reviewerAssignment,
        ReviewerWorkflowService $workflow,
    ): RedirectResponse {
        $submit = $request->validated('intent') === 'submit';
        $decision = filled($request->validated('decision'))
            ? ReviewDecision::from($request->validated('decision'))
            : null;
        $workflow->saveDecision(
            $request->user(),
            $reviewerAssignment,
            $decision,
            $request->validated('decision_comment'),
            $submit,
        );

        return $submit
            ? redirect()->route('reviewer.assignments.show', $reviewerAssignment)
                ->with('status', 'Review submitted successfully and is pending RES release.')
            : back()->with('status', 'Review decision draft saved.');
    }
}
