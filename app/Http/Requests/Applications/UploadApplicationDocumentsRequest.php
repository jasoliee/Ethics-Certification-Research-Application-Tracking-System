<?php

namespace App\Http\Requests\Applications;

use App\Models\ResearchApplication;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Authorizes a bounded set of requirement-keyed files for per-file validation.
 */
class UploadApplicationDocumentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $application = $this->route('researchApplication');

        return $application instanceof ResearchApplication
            && ($this->user()?->can('upload', $application) ?? false);
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'documents' => ['required', 'array', 'min:1', 'max:50'],
        ];
    }
}
