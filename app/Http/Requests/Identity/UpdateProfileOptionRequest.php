<?php

namespace App\Http\Requests\Identity;

use App\Enums\ProfileOptionField;
use App\Models\ProfileOption;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateProfileOptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->route('profileOption') instanceof ProfileOption
            && ($this->user()?->can('manageProfileOptions', User::class) ?? false);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $option = $this->route('profileOption');
        $isInstitute = $option instanceof ProfileOption && $option->field === ProfileOptionField::Institute;

        return [
            'option_value' => ['required', 'string', 'max:150'],
            'option_acronym' => [
                Rule::requiredIf($isInstitute),
                Rule::prohibitedIf(! $isInstitute),
                'nullable',
                'string',
                'max:12',
                'regex:/^[A-Z0-9]{2,12}$/',
                Rule::unique('profile_options', 'acronym')->ignore($option?->id),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('option_acronym')) {
            $this->merge([
                'option_acronym' => Str::upper(Str::squish((string) $this->input('option_acronym'))),
            ]);
        }
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'option_acronym.required' => 'Enter an acronym for the Institute.',
            'option_acronym.regex' => 'Use 2 to 12 uppercase letters or numbers for the Institute acronym.',
            'option_acronym.unique' => 'That Institute acronym is already assigned to another option.',
        ];
    }
}
