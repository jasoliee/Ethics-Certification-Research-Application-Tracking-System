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
                'extensions:xlsx',
                'mimetypes:application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/zip',
            ],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'accounts_file.extensions' => 'Upload a standard .xlsx workbook. CSV, legacy Excel, and macro-enabled files are not accepted.',
            'accounts_file.mimetypes' => 'The uploaded file content must be a standard macro-free XLSX workbook.',
            'accounts_file.max' => 'The Excel file must not exceed 2 MB.',
        ];
    }
}
