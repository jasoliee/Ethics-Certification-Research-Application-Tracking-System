<?php

namespace App\Http\Requests\Identity;

use App\Enums\ApplicantType;
use App\Enums\ProfileOptionField;
use App\Enums\UserRole;
use App\Models\User;
use App\Services\Identity\ProfileOptionCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateManagedUserRequest extends FormRequest
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
        $options = app(ProfileOptionCatalog::class);

        return [
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'suffix' => ['nullable', 'string', 'max:30'],
            'email' => ['required', 'email:rfc', 'max:255', Rule::unique('users', 'email')->ignore($subject->id)],
            'institutional_identifier' => ['required', 'string', 'max:50', 'regex:/^[A-Z0-9][A-Z0-9._-]*$/i', Rule::unique('users', 'institutional_identifier')->ignore($subject->id)],
            'phone_number' => ['required', 'digits:11'],
            'institution' => ['nullable', 'string', 'max:150', Rule::in($options->values(ProfileOptionField::Institute, $subject->institution))],
            'program' => ['nullable', 'string', 'max:150', Rule::in($options->values(ProfileOptionField::Program, $subject->program))],
            'year_level' => [
                Rule::requiredIf($subject->role === UserRole::Applicant && $subject->applicant_type === ApplicantType::Student),
                'nullable',
                'string',
                'max:150',
                Rule::in($options->values(ProfileOptionField::YearLevel, $subject->year_level)),
            ],
            'position_title' => ['nullable', 'string', 'max:150'],
            'reviewer_enabled' => [Rule::prohibitedIf($subject->role !== UserRole::Adviser), 'nullable', 'boolean'],
            'reviewer_capacity' => [
                'nullable',
                'integer',
            ],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        $subject = $this->route('managedUser');
        $options = app(ProfileOptionCatalog::class);

        return [
            'email.email' => 'Email must be a valid address such as name@example.com.',
            'institutional_identifier.regex' => 'Use only letters, numbers, periods, underscores, and hyphens for the institutional identifier.',
            'phone_number.digits' => 'Phone Number must contain exactly 11 digits.',
            'phone_number.required' => 'Phone Number is required and must contain exactly 11 digits.',
            'institution.in' => $options->validationMessage(ProfileOptionField::Institute),
            'program.in' => $options->validationMessage(ProfileOptionField::Program),
            'year_level.in' => $options->validationMessage(ProfileOptionField::YearLevel),
        ];
    }
}
