<?php

namespace App\Http\Requests\Identity;

use App\Enums\ApplicantType;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreManagedUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', User::class) ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $isApplicant = $this->input('role') === UserRole::Applicant->value;

        return [
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'suffix' => ['nullable', 'string', 'max:30'],
            'email' => ['required', 'email:rfc', 'max:255', Rule::unique('users', 'email')],
            'institutional_identifier' => ['required', 'string', 'max:50', 'regex:/^[A-Z0-9][A-Z0-9._-]*$/i', Rule::unique('users', 'institutional_identifier')],
            'phone_number' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+().\s-]+$/'],
            'institution' => ['nullable', 'string', 'max:150'],
            'department' => ['nullable', 'string', 'max:150'],
            'position_title' => ['nullable', 'string', 'max:150'],
            'role' => ['required', Rule::enum(UserRole::class)],
            'applicant_type' => [Rule::requiredIf($isApplicant), 'nullable', Rule::enum(ApplicantType::class)],
            'password' => ['required', 'string', 'min:8', 'max:64', 'confirmed'],
            'password_confirmation' => ['required', 'string', 'max:64'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'password.required' => 'Enter an initial password.',
            'password.min' => 'Password must be at least 8 characters.',
            'password.confirmed' => 'Password confirmation does not match.',
            'institutional_identifier.regex' => 'Use only letters, numbers, periods, underscores, and hyphens for the institutional identifier.',
        ];
    }
}
