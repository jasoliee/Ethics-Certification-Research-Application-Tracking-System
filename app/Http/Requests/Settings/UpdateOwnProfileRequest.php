<?php

namespace App\Http\Requests\Settings;

use App\Enums\ApplicantType;
use App\Enums\ProfileOptionField;
use App\Enums\UserRole;
use App\Services\Identity\ProfileOptionCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOwnProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user?->can('updateOwnProfile', $user) ?? false;
    }

    protected function prepareForValidation(): void
    {
        foreach ([
            'first_name', 'middle_name', 'last_name', 'suffix', 'phone_number',
            'institution', 'department', 'program', 'year_level', 'position_title',
        ] as $field) {
            if ($this->has($field)) {
                $this->merge([$field => is_string($this->input($field))
                    ? trim($this->input($field))
                    : $this->input($field)]);
            }
        }
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $user = $this->user();
        $options = app(ProfileOptionCatalog::class);

        return [
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'suffix' => ['nullable', 'string', 'max:30'],
            'phone_number' => ['required', 'digits:11'],
            'institution' => ['nullable', 'string', 'max:150', Rule::in($options->values(ProfileOptionField::Institution, $user?->institution))],
            'department' => ['nullable', 'string', 'max:150', Rule::in($options->values(ProfileOptionField::Department, $user?->department))],
            'program' => ['nullable', 'string', 'max:150', Rule::in($options->values(ProfileOptionField::Program, $user?->program))],
            'year_level' => [
                Rule::requiredIf($user?->role === UserRole::Applicant && $user?->applicant_type === ApplicantType::Student),
                'nullable',
                'string',
                'max:150',
                Rule::in($options->values(ProfileOptionField::YearLevel, $user?->year_level)),
            ],
            'position_title' => [
                Rule::requiredIf($user?->role === UserRole::Adviser),
                'nullable',
                'string',
                'max:150',
            ],
            'expected_endorsement_count' => [
                Rule::excludeIf($user?->role !== UserRole::Adviser),
                'nullable',
                'integer',
                'between:0,10000',
            ],
        ];
    }
}
