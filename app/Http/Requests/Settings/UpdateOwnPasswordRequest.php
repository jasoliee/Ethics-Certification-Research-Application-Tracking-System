<?php

namespace App\Http\Requests\Settings;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Requires an authorized dashboard user's current credential before changing their password.
 */
class UpdateOwnPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role, [UserRole::ResLead, UserRole::Reviewer], true);
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

    /**
     * Attach the same mismatch state to both new-password controls.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! is_string($this->input('password'))
                || ! is_string($this->input('password_confirmation'))
                || hash_equals($this->input('password'), $this->input('password_confirmation'))) {
                return;
            }

            $message = 'The new password and confirmation do not match.';
            $validator->errors()->add('password', $message);
            $validator->errors()->add('password_confirmation', $message);
        });
    }
}
