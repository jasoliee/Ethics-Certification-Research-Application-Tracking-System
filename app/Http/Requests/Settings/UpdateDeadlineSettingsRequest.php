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
            'semester_label' => ['required', 'string', 'max:100'],
            'processes' => ['required', 'array'],
        ];

        // Require every repository-approved process so a partial request cannot silently erase a schedule.
        foreach (DeadlineProcessCatalog::keys() as $key) {
            $rules["processes.{$key}"] = ['required', 'array'];
            $rules["processes.{$key}.starts_at"] = ['required', 'date_format:Y-m-d\TH:i'];
            $rules["processes.{$key}.due_at"] = [
                'required',
                'date_format:Y-m-d\TH:i',
                "after_or_equal:processes.{$key}.starts_at",
            ];
            $rules["processes.{$key}.is_open"] = ['required', 'boolean'];
        }

        return $rules;
    }
}
