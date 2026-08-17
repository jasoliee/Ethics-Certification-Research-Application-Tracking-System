<?php

namespace App\Http\Requests\Applications;

use App\Models\ResearchApplication;
use App\Rules\SafeApplicationDocumentUpload;
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
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'document' => self::documentRules(),
        ];
    }

    /**
     * Share the same per-file contract with the multi-requirement upload endpoint.
     *
     * @return array<int, mixed>
     */
    public static function documentRules(): array
    {
        return [
            'required',
            'file',
            'max:'.ApplicationDocumentService::MAX_FILE_KILOBYTES,
            'extensions:pdf,jpg,jpeg,png,gif,webp',
            'mimetypes:application/pdf,image/jpeg,image/png,image/gif,image/webp',
            new SafeApplicationDocumentUpload,
        ];
    }
}
