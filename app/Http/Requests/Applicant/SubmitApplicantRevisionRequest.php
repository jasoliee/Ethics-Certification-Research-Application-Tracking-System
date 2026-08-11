<?php

namespace App\Http\Requests\Applicant;

use App\Models\ApplicationRevision;
use App\Models\ResearchApplication;
use Illuminate\Foundation\Http\FormRequest;

class SubmitApplicantRevisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $application = $this->route('researchApplication');
        $revision = $this->route('applicationRevision');

        return $application instanceof ResearchApplication
            && $revision instanceof ApplicationRevision
            && $revision->research_application_id === $application->id
            && ($this->user()?->can('submitRevision', $application) ?? false);
    }

    public function rules(): array
    {
        return [];
    }
}
