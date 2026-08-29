<?php

namespace App\Http\Requests\Reviewer;

use App\Enums\ReviewDecision;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveReviewerFormRequest extends FormRequest
{
    protected $errorBag = 'reviewerForm';

    public function authorize(): bool
    {
        $assignment = $this->route('reviewerAssignment');

        return $assignment && $this->user()?->can('work', $assignment);
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('consent_required')) {
            $this->merge(['consent_required' => $this->boolean('consent_required')]);
        }
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'intent' => ['required', Rule::in(['draft', 'submit'])],
            'responses' => ['nullable', 'array', 'max:20'],
            'responses.*' => ['array:answer,comment'],
            'responses.*.answer' => ['nullable', Rule::in(['yes', 'no', 'unable_to_assess'])],
            'responses.*.comment' => ['nullable', 'string', 'min:5', 'max:1000'],
            'consent_required' => ['nullable', 'boolean'],
            'consent_not_required_explanation' => ['nullable', 'string', 'min:5', 'max:2000'],
            'recommendation' => ['nullable', Rule::enum(ReviewDecision::class)],
            'recommendation_comments' => ['nullable', 'string', 'min:5', 'max:2000'],
        ];
    }
}
