<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWorksheetSignatoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasReviewerAccess() ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'worksheet_signatory_name' => trim((string) $this->input('worksheet_signatory_name')),
        ]);
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'worksheet_signatory_name' => ['required', 'string', 'max:120'],
            'signature' => ['nullable', 'file', 'max:2048', 'mimes:png'],
        ];
    }
}
