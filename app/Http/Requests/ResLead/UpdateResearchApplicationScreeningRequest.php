<?php

namespace App\Http\Requests\ResLead;

use App\Enums\ReceiptCheckStatus;
use App\Enums\ReviewType;
use App\Enums\ScreeningCompletenessStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates an authorized correction to an existing RES screening decision.
 */
class UpdateResearchApplicationScreeningRequest extends FormRequest
{
    protected $errorBag = 'resScreening';

    public function authorize(): bool
    {
        $application = $this->route('researchApplication');

        return $application && $this->user()?->can('updateScreening', $application);
    }

    /**
     * Normalize unchecked screening confirmations so a correction can explicitly revoke a prior finding.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'required_documents_verified' => $this->boolean('required_documents_verified'),
            'receipt_status_recorded' => $this->boolean('receipt_status_recorded'),
            'basic_eligibility_confirmed' => $this->boolean('basic_eligibility_confirmed'),
        ]);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'completeness_status' => ['required', Rule::enum(ScreeningCompletenessStatus::class)],
            'receipt_check_status' => ['required', Rule::enum(ReceiptCheckStatus::class)],
            'required_documents_verified' => ['required', 'boolean'],
            'receipt_status_recorded' => ['required', 'boolean'],
            'basic_eligibility_confirmed' => ['required', 'boolean'],
            'screening_notes' => ['nullable', 'string', 'max:2000'],
            'review_type' => ['required', Rule::enum(ReviewType::class)],
            'classification_reason' => ['required', 'string', 'min:15', 'max:2000'],
        ];
    }
}
