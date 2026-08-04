<?php

namespace App\Http\Requests\Reviewer;

use App\Enums\ReviewerConflictStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DeclareReviewerConflictRequest extends FormRequest
{
    protected $errorBag = 'reviewerConflict';

    public function authorize(): bool
    {
        $assignment = $this->route('reviewerAssignment');

        return $assignment && $this->user()?->can('declareConflict', $assignment);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'conflict_status' => [
                'required',
                Rule::in([
                    ReviewerConflictStatus::Cleared->value,
                    ReviewerConflictStatus::Declared->value,
                ]),
            ],
        ];
    }
}
