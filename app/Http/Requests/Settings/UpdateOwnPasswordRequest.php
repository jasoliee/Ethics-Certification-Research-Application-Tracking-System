<?php

namespace App\Http\Requests\Settings;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Requires the RES Lead's current credential before changing their password.
 */
class UpdateOwnPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === UserRole::ResLead;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string', 'max:64', 'current_password:web'],
            'password' => ['required', 'string', 'min:8', 'max:64', 'confirmed', 'different:current_password'],
            'password_confirmation' => ['required', 'string', 'max:64'],
        ];
    }
}
