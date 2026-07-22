<?php

namespace App\Http\Requests\Identity;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegenerateManagedUsernameRequest extends FormRequest
{
    public function authorize(): bool
    {
        $subject = $this->route('managedUser');

        return $subject instanceof User && ($this->user()?->can('update', $subject) ?? false);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        /** @var User $subject */
        $subject = $this->route('managedUser');

        return [
            'last_name' => ['required', 'string', 'max:100'],
            'institutional_identifier' => [
                'required',
                'string',
                'max:50',
                'regex:/^[A-Z0-9][A-Z0-9._-]*$/i',
                Rule::unique('users', 'institutional_identifier')->ignore($subject->id),
            ],
            'confirm_username_regeneration' => ['accepted'],
        ];
    }
}
