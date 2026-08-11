<?php

namespace App\Http\Requests\ResLead;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;

class UploadCertificateBackgroundRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === UserRole::ResLead;
    }

    public function rules(): array
    {
        return [
            'background' => [
                'required',
                'file',
                'max:10240',
                'mimes:pdf,jpg,jpeg,png',
            ],
        ];
    }
}
