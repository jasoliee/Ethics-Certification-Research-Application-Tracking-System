<?php

namespace App\Http\Requests\Reviewer;

use App\Enums\ReviewDecision;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveReviewerDecisionRequest extends FormRequest
{
    protected $errorBag = 'reviewDecision';

    public function authorize(): bool
    {
        $assignment = $this->route('reviewerAssignment');

        return $assignment && $this->user()?->can('work', $assignment);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'intent' => ['required', Rule::in(['draft', 'submit'])],
            'decision' => [
                Rule::requiredIf(fn (): bool => $this->input('intent') === 'submit'),
                'nullable',
                Rule::enum(ReviewDecision::class),
            ],
            'decision_comment' => [
                Rule::requiredIf(fn (): bool => $this->input('intent') === 'submit'),
                'nullable',
                'string',
                'min:10',
                'max:2000',
            ],
        ];
    }
}
