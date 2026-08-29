<?php

namespace App\Http\Requests\ResLead;

use App\Enums\ReviewType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates an authorized correction to an existing REU screening decision.
 */
class UpdateResearchApplicationScreeningRequest extends FormRequest
{
    protected $errorBag = 'resScreening';

    public function authorize(): bool
    {
        $application = $this->route('researchApplication');

        return $application && $this->user()?->can('updateScreening', $application);
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
