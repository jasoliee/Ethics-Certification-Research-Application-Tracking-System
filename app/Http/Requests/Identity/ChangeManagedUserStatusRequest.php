<?php

namespace App\Http\Requests\Identity;

use App\Enums\AccountStatus;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangeManagedUserStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        $subject = $this->route('managedUser');

        return $subject instanceof User && ($this->user()?->can('changeStatus', $subject) ?? false);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return ['account_status' => ['required', Rule::enum(AccountStatus::class)]];
    }
}
