<?php

namespace App\Http\Requests\ResLead;

use App\Models\ResearchApplication;
use Illuminate\Foundation\Http\FormRequest;

class ReleaseApplicationDecisionRequest extends FormRequest
{
    protected $errorBag = 'decisionRelease';

    public function authorize(): bool
    {
        $application = $this->route('researchApplication');

        return $application instanceof ResearchApplication
            && ($this->user()?->can('releaseDecision', $application) ?? false);
    }

    public function rules(): array
    {
        return [
            'review_submission_id' => ['required', 'integer', 'exists:review_submissions,id'],
        ];
    }
}
