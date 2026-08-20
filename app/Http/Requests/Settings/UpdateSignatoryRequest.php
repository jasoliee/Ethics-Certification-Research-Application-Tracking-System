<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSignatoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user?->can('manageCertificateSignatory', $user) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'certificate_signatory_name' => trim((string) $this->input('certificate_signatory_name')),
        ]);
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'certificate_signatory_name' => ['required', 'string', 'max:120'],
            'certificate_valid_until' => ['nullable', 'date', 'after_or_equal:today'],
            'signature' => ['nullable', 'file', 'max:2048', 'mimes:png'],
            'qr_image' => ['nullable', 'file', 'max:4096', 'mimes:png'],
        ];
    }
}
