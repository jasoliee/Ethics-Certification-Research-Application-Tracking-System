<?php

namespace App\Http\Requests\Identity;

use App\Enums\ProfileOptionField;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProfileOptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageProfileOptions', User::class) ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'option_field' => ['required', Rule::enum(ProfileOptionField::class)],
            'option_value' => ['required', 'string', 'max:150'],
        ];
    }
}
