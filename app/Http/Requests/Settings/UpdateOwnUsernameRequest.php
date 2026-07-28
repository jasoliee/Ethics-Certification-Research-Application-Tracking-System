<?php

namespace App\Http\Requests\Settings;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates a RES Lead's own normalized login username.
 */
class UpdateOwnUsernameRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === UserRole::ResLead;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'username' => strtolower(trim((string) $this->input('username'))),
        ]);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'username' => [
                'required',
                'string',
                'min:6',
                'max:30',
                'regex:/^[a-z0-9]+(?:\.[a-z0-9]+)*[0-9]*$/',
                Rule::unique('users', 'username')->ignore($this->user()?->id),
            ],
        ];
    }
}
