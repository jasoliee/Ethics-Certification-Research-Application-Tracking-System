<?php

namespace App\Http\Requests\Reviewer;

use App\Enums\ReviewCommentCategory;
use App\Enums\ReviewCommentScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreReviewCommentRequest extends FormRequest
{
    protected $errorBag = 'reviewComment';

    public function authorize(): bool
    {
        $assignment = $this->route('reviewerAssignment');

        return $assignment && $this->user()?->can('work', $assignment);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'scope' => ['required', Rule::enum(ReviewCommentScope::class)],
            'category' => ['required', Rule::enum(ReviewCommentCategory::class)],
            'application_document_id' => [
                Rule::requiredIf(fn (): bool => in_array($this->input('scope'), ['document', 'page'], true)),
                'nullable',
                'integer',
                'exists:application_documents,id',
            ],
            'page_number' => [
                Rule::requiredIf(fn (): bool => $this->input('scope') === ReviewCommentScope::Page->value),
                'nullable',
                'integer',
                'min:1',
                'max:10000',
            ],
            'body' => ['required', 'string', 'min:3', 'max:2000'],
        ];
    }

    /**
     * Required Revision comments must identify the source file the Applicant must replace.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->input('category') !== ReviewCommentCategory::RequiredRevision->value
                || in_array($this->input('scope'), [
                    ReviewCommentScope::Document->value,
                    ReviewCommentScope::Page->value,
                ], true)) {
                return;
            }

            $validator->errors()->add(
                'scope',
                'A Required Revision comment must apply to a specific document.',
            );
        });
    }
}
