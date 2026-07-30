<?php

namespace App\Http\Requests\Settings;

use App\Enums\UserRole;
use App\Support\DeadlineProcessCatalog;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates one complete semester deadline configuration from the RES Lead.
 */
class UpdateDeadlineSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === UserRole::ResLead;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        $rules = [
            'semester' => ['required', 'string', 'max:50'],
            'academic_year' => ['required', 'string', 'max:20', 'regex:/^\d{4}-\d{4}$/'],
            'term_starts_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
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

            if (! $definition['exact_date']) {
                $rules["processes.{$key}.starts_at"] = [
                    'required',
                    'date_format:Y-m-d\TH:i',
                    'after_or_equal:now',
                ];
                $rules["processes.{$key}.due_at"][] = "after_or_equal:processes.{$key}.starts_at";
            }
        }

        return $rules;
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'term_starts_on.after_or_equal' => 'The semester starting date cannot be in the past.',
            'term_ends_on.after_or_equal' => 'The semester ending date must be on or after its starting date.',
            'processes.*.starts_at.after_or_equal' => 'Process opening dates and times cannot be in the past.',
            'processes.*.due_at.after_or_equal' => 'Process deadlines and release dates cannot be in the past or before their opening time.',
        ];
    }
}
