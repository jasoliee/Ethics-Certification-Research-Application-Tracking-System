<?php

namespace App\Http\Requests\Applicant;

use App\Models\ResearchApplication;
use Illuminate\Foundation\Http\FormRequest;

class StoreApplicantSurveyRequest extends FormRequest
{
    protected $errorBag = 'certificateSurvey';

    public function authorize(): bool
    {
        $application = $this->route('researchApplication');

        return $application instanceof ResearchApplication
            && ($this->user()?->can('submitSurvey', $application) ?? false);
    }

    public function rules(): array
    {
        return [
            'ratings' => ['required', 'array'],
            'ratings.overall_process' => ['required', 'integer', 'between:1,5'],
            'ratings.communication' => ['required', 'integer', 'between:1,5'],
            'ratings.comments_helpfulness' => ['required', 'integer', 'between:1,5'],
            'ratings.timeliness' => ['required', 'integer', 'between:1,5'],
            'positive_feedback' => ['required', 'string', 'min:5', 'max:500'],
            'improvement_feedback' => ['required', 'string', 'min:5', 'max:500'],
            'additional_comments' => ['nullable', 'string', 'max:500'],
        ];
    }
}
