<?php

namespace App\Http\Requests\ResLead;

use App\Enums\ReceiptCheckStatus;
use App\Enums\ReviewType;
use App\Enums\ScreeningCompletenessStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
            'completeness_status' => ['required', Rule::enum(ScreeningCompletenessStatus::class)],
            'receipt_check_status' => ['required', Rule::enum(ReceiptCheckStatus::class)],
            'required_documents_verified' => ['required', 'accepted'],
            'receipt_status_recorded' => ['required', 'accepted'],
            'basic_eligibility_confirmed' => ['required', 'accepted'],
            'screening_notes' => ['nullable', 'string', 'max:2000'],
            'review_type' => ['required', Rule::enum(ReviewType::class)],
            'classification_reason' => ['required', 'string', 'min:15', 'max:2000'],
        ];
    }

    /**
     * Classification remains unavailable until every administrative gate is affirmative.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($this->input('completeness_status') !== ScreeningCompletenessStatus::Complete->value) {
                $validator->errors()->add('completeness_status', 'The application must be complete before classification.');
            }

            if ($this->input('receipt_check_status') !== ReceiptCheckStatus::Accepted->value) {
                $validator->errors()->add('receipt_check_status', 'The receipt check must be accepted before classification.');
            }
        }];
    }
}
