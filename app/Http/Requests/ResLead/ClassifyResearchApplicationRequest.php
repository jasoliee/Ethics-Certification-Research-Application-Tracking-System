<?php

namespace App\Http\Requests\ResLead;

use App\Enums\ReviewType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates one explicit RES administrative screening and classification decision.
 */
class ClassifyResearchApplicationRequest extends FormRequest
{
    protected $errorBag = 'resScreening';

    public function authorize(): bool
    {
        $application = $this->route('researchApplication');

        return $application && $this->user()?->can('classify', $application);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'review_type' => ['required', Rule::enum(ReviewType::class)],
            'classification_reason' => ['required', 'string', 'min:5', 'max:2000'],
        ];
    }
}
