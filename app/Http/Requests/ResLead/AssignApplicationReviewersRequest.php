<?php

namespace App\Http\Requests\ResLead;

use App\Enums\ReviewType;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the reviewer identifiers and exact count required by the saved classification.
 */
class AssignApplicationReviewersRequest extends FormRequest
{
    protected $errorBag = 'reviewerAssignment';

    public function authorize(): bool
    {
        $application = $this->route('researchApplication');

        return $application && $this->user()?->can('assignReviewers', $application);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $application = $this->route('researchApplication');
        $reviewType = ReviewType::tryFrom((string) ($application?->review_type ?? ''));
        $requiredCount = $reviewType?->reviewerCount() ?? 0;
        $reviewCycle = max(0, ((int) ($application?->current_revision_cycle ?? 1)) - 1);
        $assignmentReviewType = $reviewCycle === 0 ? 'initial_review' : 'revision_review';
        $currentReviewerIds = $application?->reviewerAssignments()
            ->current()
            ->where('review_type', $assignmentReviewType)
            ->where('review_cycle', $reviewCycle)
            ->pluck('reviewer_user_id')
            ->map(fn ($id): int => (int) $id)
            ->sort()
            ->values()
            ->all() ?? [];
        $requestedReviewerIds = collect($this->input('reviewer_ids', []))
            ->map(fn ($id): int => (int) $id)
            ->sort()
            ->values()
            ->all();
        $isReassignment = $currentReviewerIds !== [] && $currentReviewerIds !== $requestedReviewerIds;

        return [
            'reviewer_ids' => ['required', 'array', 'size:'.$requiredCount],
            'reviewer_ids.*' => ['required', 'integer', 'distinct', 'exists:users,id'],
            'confirm_assignment' => ['required', 'accepted'],
            'reassignment_reason' => [$isReassignment ? 'required' : 'nullable', 'string', 'min:10', 'max:1000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'reviewer_ids.required' => 'Select the required reviewer or reviewers.',
            'reviewer_ids.size' => 'Select exactly :size eligible reviewer(s) for this classification.',
            'reviewer_ids.*.distinct' => 'A reviewer can be selected only once.',
            'confirm_assignment.accepted' => 'Confirm the reviewer assignment before continuing.',
            'reassignment_reason.required' => 'Explain why the current reviewer set is being changed.',
        ];
    }
}
