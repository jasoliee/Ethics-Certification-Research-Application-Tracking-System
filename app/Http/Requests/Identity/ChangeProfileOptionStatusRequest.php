<?php

namespace App\Http\Requests\Identity;

use App\Models\ProfileOption;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class ChangeProfileOptionStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->route('profileOption') instanceof ProfileOption
            && ($this->user()?->can('manageProfileOptions', User::class) ?? false);
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return ['is_active' => ['required', 'boolean']];
    }
}
