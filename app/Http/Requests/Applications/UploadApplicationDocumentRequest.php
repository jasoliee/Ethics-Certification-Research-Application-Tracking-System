<?php

namespace App\Http\Requests\Applications;

use App\Models\ResearchApplication;
use App\Services\Applications\ApplicationDocumentService;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Authorizes and validates one private applicant requirement upload.
 */
class UploadApplicationDocumentRequest extends FormRequest
{
    /**
     * Confirm the applicant may still modify the route-bound application.
     */
    public function authorize(): bool
    {
        $application = $this->route('researchApplication');

        return $application instanceof ResearchApplication
            && ($this->user()?->can('upload', $application) ?? false);
    }

    /**
     * Reject oversized, executable, and unsupported file types before storage.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'document' => [
                'required',
                'file',
                'max:'.ApplicationDocumentService::MAX_FILE_KILOBYTES,
                'mimes:pdf,doc,docx,jpg,jpeg,png',
            ],
        ];
    }
}
