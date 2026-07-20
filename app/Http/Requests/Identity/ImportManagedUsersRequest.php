<?php

namespace App\Http\Requests\Identity;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class ImportManagedUsersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('import', User::class) ?? false;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'accounts_file' => [
                'required',
                'file',
                'max:2048',
                'extensions:csv',
                'mimetypes:text/plain,text/csv,application/csv,application/vnd.ms-excel',
            ],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'accounts_file.extensions' => 'Upload a CSV file using the provided template.',
            'accounts_file.mimetypes' => 'The uploaded file content must be a valid CSV document.',
            'accounts_file.max' => 'The CSV file must not exceed 2 MB.',
        ];
    }
}
