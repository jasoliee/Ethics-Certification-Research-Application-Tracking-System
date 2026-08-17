<?php

namespace App\Http\Requests\Settings;

use App\Enums\UserRole;
use App\Models\CertificateBackground;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UploadManagedBackgroundRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === UserRole::ResLead;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'background_type' => ['required', Rule::in([
                CertificateBackground::TYPE_CERTIFICATE,
                CertificateBackground::TYPE_REVIEW_WORKSHEET,
            ])],
            'background' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png'],
        ];
    }
}
