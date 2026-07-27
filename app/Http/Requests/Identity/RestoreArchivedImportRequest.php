<?php

namespace App\Http\Requests\Identity;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Authorizes restoration through an actor-owned private import preview.
 */
class RestoreArchivedImportRequest extends FormRequest
{
    /**
     * Restrict every individual and bulk restoration request to the RES Lead policy.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('restoreArchivedAccounts', User::class) ?? false;
    }

    /**
     * Accept only the opaque preview token and an optional individually selected user ID.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'import_token' => ['required', 'uuid'],
            'archived_user_id' => ['sometimes', 'required', 'integer', 'min:1'],
        ];
    }
}
