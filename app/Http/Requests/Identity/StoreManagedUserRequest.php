<?php

namespace App\Http\Requests\Identity;

use App\Enums\ApplicantType;
use App\Enums\ProfileOptionField;
use App\Enums\UserRole;
use App\Models\User;
use App\Services\Identity\AccountCreationAuthorizationService;
use App\Services\Identity\ProfileOptionCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreManagedUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $actor = $this->user();
        $targetRole = UserRole::tryFrom((string) $this->input('role'));

        return $actor !== null
            && $targetRole !== null
            && $actor->can('create', User::class)
            && app(AccountCreationAuthorizationService::class)->canCreate($actor, $targetRole);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $isApplicant = $this->input('role') === UserRole::Applicant->value;
        $isStudent = $isApplicant && $this->input('applicant_type') === ApplicantType::Student->value;
        $options = app(ProfileOptionCatalog::class);

        return [
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'suffix' => ['nullable', 'string', 'max:30'],
            'email' => ['required', 'email:rfc', 'max:255', Rule::unique('users', 'email')],
            'institutional_identifier' => ['required', 'string', 'max:50', 'regex:/^[A-Z0-9][A-Z0-9._-]*$/i', Rule::unique('users', 'institutional_identifier')],
            'phone_number' => ['required', 'digits:11'],
            'institution' => ['nullable', 'string', 'max:150', Rule::in($options->values(ProfileOptionField::Institution))],
            'department' => ['nullable', 'string', 'max:150', Rule::in($options->values(ProfileOptionField::Department))],
            'program' => ['nullable', 'string', 'max:150', Rule::in($options->values(ProfileOptionField::Program))],
            'year_level' => [Rule::requiredIf($isStudent), 'nullable', 'string', 'max:150', Rule::in($options->values(ProfileOptionField::YearLevel))],
            'position_title' => ['nullable', 'string', 'max:150'],
            'reviewer_classifications' => ['nullable', 'array', 'min:1', 'max:2'],
            'reviewer_classifications.*' => ['string', 'distinct', Rule::in(['Expedited', 'Full Board'])],
            'reviewer_capacity' => ['nullable', 'integer', 'between:1,30'],
            'role' => ['required', Rule::enum(UserRole::class)],
            'applicant_type' => [Rule::requiredIf($isApplicant), 'nullable', Rule::enum(ApplicantType::class)],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        $options = app(ProfileOptionCatalog::class);

        return [
            'email.email' => 'Email must be a valid address such as name@example.com.',
            'institutional_identifier.regex' => 'Use only letters, numbers, periods, underscores, and hyphens for the institutional identifier.',
            'phone_number.digits' => 'Phone Number must contain exactly 11 digits.',
            'phone_number.required' => 'Phone Number is required and must contain exactly 11 digits.',
            'institution.in' => $options->validationMessage(ProfileOptionField::Institution),
            'department.in' => $options->validationMessage(ProfileOptionField::Department),
            'program.in' => $options->validationMessage(ProfileOptionField::Program),
            'year_level.in' => $options->validationMessage(ProfileOptionField::YearLevel),
            'reviewer_classifications.*.in' => 'Reviewer Classification must be Expedited or Full Board.',
        ];
    }
}
