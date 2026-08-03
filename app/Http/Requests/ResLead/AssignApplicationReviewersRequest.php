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

        return [
            'reviewer_ids' => ['required', 'array', 'size:'.$requiredCount],
            'reviewer_ids.*' => ['required', 'integer', 'distinct', 'exists:users,id'],
            'confirm_assignment' => ['required', 'accepted'],
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
        ];
    }
}
