<?php

namespace App\Http\Requests\Identity;

use App\Enums\ApplicantType;
use App\Enums\ReviewerClassification;
use App\Enums\UserRole;
use App\Models\User;
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

        return [
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'suffix' => ['nullable', 'string', 'max:30'],
            'email' => ['required', 'email:rfc', 'max:255', Rule::unique('users', 'email')->ignore($subject->id)],
            'institutional_identifier' => ['required', 'string', 'max:50', 'regex:/^[A-Z0-9][A-Z0-9._-]*$/i', Rule::unique('users', 'institutional_identifier')->ignore($subject->id)],
            'phone_number' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+().\s-]+$/'],
            'institution' => ['nullable', 'string', 'max:150'],
            'department' => ['nullable', 'string', 'max:150'],
            'program' => ['nullable', 'string', 'max:150'],
            'year_level' => [
                Rule::requiredIf($subject->role === UserRole::Applicant && $subject->applicant_type === ApplicantType::Student),
                'nullable',
                'string',
                'max:30',
            ],
            'position_title' => [Rule::requiredIf($subject->role === UserRole::Adviser), 'nullable', 'string', 'max:150'],
            'reviewer_classification' => [
                Rule::requiredIf($subject->role === UserRole::Reviewer),
                'nullable',
                Rule::enum(ReviewerClassification::class),
            ],
            'reviewer_capacity' => [
                Rule::requiredIf($subject->role === UserRole::Reviewer),
                'nullable',
                'integer',
                'between:1,30',
            ],
        ];
    }
}
