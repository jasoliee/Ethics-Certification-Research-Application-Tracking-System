<?php

namespace App\Http\Requests\Applicant;

use App\Http\Requests\Applications\UploadApplicationDocumentRequest;
use App\Models\ApplicationRevision;
use App\Models\ApplicationRevisionRequirement;
use App\Models\ResearchApplication;
use Illuminate\Foundation\Http\FormRequest;

class UploadRevisionDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $application = $this->route('researchApplication');
        $revision = $this->route('applicationRevision');
        $requirement = $this->route('applicationRevisionRequirement');

        return $application instanceof ResearchApplication
            && $revision instanceof ApplicationRevision
            && $requirement instanceof ApplicationRevisionRequirement
            && $revision->research_application_id === $application->id
            && $requirement->application_revision_id === $revision->id
            && ($this->user()?->can('submitRevision', $application) ?? false);
    }

    public function rules(): array
    {
        return ['document' => UploadApplicationDocumentRequest::documentRules()];
    }
}
