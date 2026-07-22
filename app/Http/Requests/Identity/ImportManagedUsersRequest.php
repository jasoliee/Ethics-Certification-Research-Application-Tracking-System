<?php

namespace App\Http\Requests\Identity;

use App\Models\User;
use App\Services\Identity\UserBulkImportService;
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
            'account_type' => ['required', 'string', 'max:30'],
            'accounts_file' => [
                'required',
                'file',
                'max:'.UserBulkImportService::MAX_FILE_KILOBYTES,
                'extensions:csv,xlsx',
                'mimetypes:text/plain,text/csv,application/csv,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/zip',
            ],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'accounts_file.extensions' => 'Upload a CSV or XLSX file using the selected role template.',
            'accounts_file.mimetypes' => 'The uploaded file content must be a valid CSV or XLSX document.',
            'accounts_file.max' => 'The account file must not exceed 2 MB.',
        ];
    }
}
