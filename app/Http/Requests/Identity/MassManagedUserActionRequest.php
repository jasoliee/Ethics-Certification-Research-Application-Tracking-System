<?php

namespace App\Http\Requests\Identity;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MassManagedUserActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === UserRole::ResLead;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $requiresSelection = $this->input('action') !== 'resend_all_pending';

        return [
            'action' => ['required', Rule::in(['deactivate', 'archive', 'resend_setup', 'resend_all_pending'])],
            'user_ids' => [Rule::requiredIf($requiresSelection), 'array', 'max:100'],
            'user_ids.*' => ['integer', 'distinct', 'exists:users,id'],
        ];
    }
}
