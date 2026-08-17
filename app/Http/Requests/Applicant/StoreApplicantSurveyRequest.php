<?php

namespace App\Http\Requests\Applicant;

use App\Models\ResearchApplication;
use App\Support\ApplicantSurveyCatalog;
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
        $questionKeys = ApplicantSurveyCatalog::questionKeys();
        $rules = [
            'ratings' => ['required', 'array:'.implode(',', $questionKeys), 'size:'.count($questionKeys)],
            'suggestions_comments' => ['nullable', 'string', 'max:2000'],
        ];

        foreach ($questionKeys as $questionKey) {
            $rules["ratings.{$questionKey}"] = ['required', 'integer', 'between:1,5'];
        }

        return $rules;
    }
}
