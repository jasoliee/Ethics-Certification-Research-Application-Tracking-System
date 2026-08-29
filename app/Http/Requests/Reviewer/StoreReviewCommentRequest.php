<?php

namespace App\Http\Requests\Reviewer;

use App\Enums\ReviewCommentCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'category' => ['required', Rule::enum(ReviewCommentCategory::class)],
            'application_document_id' => [
                'nullable',
                'integer',
                'exists:application_documents,id',
            ],
            'body' => ['required', 'string', 'min:5', 'max:2000'],
        ];
    }
}
