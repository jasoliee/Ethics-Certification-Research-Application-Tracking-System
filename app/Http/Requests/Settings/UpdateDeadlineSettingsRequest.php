<?php

namespace App\Http\Requests\Settings;

use App\Enums\UserRole;
use App\Support\DeadlineProcessCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates one complete semester deadline configuration from the REU Lead.
 */
class UpdateDeadlineSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === UserRole::ResLead;
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('academic_year_start') && $this->filled('academic_year_end')) {
            $this->merge([
                'academic_year' => $this->string('academic_year_start')->trim()
                    .'-'.$this->string('academic_year_end')->trim(),
            ]);
        }
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        $rules = [
            'semester' => ['required', 'string', Rule::in(['1st Semester', '2nd Semester', '3rd Semester', 'Mid Year'])],
            'academic_year' => ['required', 'string', 'max:20', 'regex:/^\d{4}-\d{4}$/'],
            'academic_year_start' => ['nullable', 'required_with:academic_year_end', 'integer', 'digits:4'],
            'academic_year_end' => ['nullable', 'required_with:academic_year_start', 'integer', 'digits:4', 'gt:academic_year_start'],
            'term_starts_on' => ['required', 'date_format:Y-m-d'],
            'term_ends_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:term_starts_on'],
            'processes' => ['required', 'array'],
        ];

        // Require every repository-approved process so a partial request cannot silently erase a schedule.
        foreach (DeadlineProcessCatalog::definitions() as $key => $definition) {
            $rules["processes.{$key}"] = ['required', 'array'];
            $rules["processes.{$key}.due_at"] = [
                'required',
                'date_format:Y-m-d\TH:i',
                'after_or_equal:now',
            ];
            $rules["processes.{$key}.is_open"] = ['required', 'boolean'];
            $rules["processes.{$key}.override_changed"] = ['required', 'boolean'];
            $rules["processes.{$key}.starts_at"] = ['required', 'date_format:Y-m-d\TH:i', 'after_or_equal:now'];
            $rules["processes.{$key}.due_at"][] = "after_or_equal:processes.{$key}.starts_at";
        }

        return $rules;
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'term_ends_on.after_or_equal' => 'The semester ending date must be on or after its starting date.',
            'academic_year_end.gt' => 'The school-year ending year must be later than its starting year.',
            'processes.*.due_at.after_or_equal' => 'Each process deadline must be on or after its opening time.',
            'processes.*.starts_at.after_or_equal' => 'Process opening dates and times cannot be in the past.',
        ];
    }
}
