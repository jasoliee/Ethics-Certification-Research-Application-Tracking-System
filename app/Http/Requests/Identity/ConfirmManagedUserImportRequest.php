<?php

namespace App\Http\Requests\Identity;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class ConfirmManagedUserImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('import', User::class) ?? false;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return ['import_token' => ['required', 'uuid']];
    }
}
