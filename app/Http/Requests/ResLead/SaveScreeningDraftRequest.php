<?php

namespace App\Http\Requests\ResLead;

use App\Enums\ReviewType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveScreeningDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        $application = $this->route('researchApplication');
        if (! $application) {
            return false;
        }

        $ability = $application->screening()->exists() ? 'updateScreening' : 'classify';

        return (bool) $this->user()?->can($ability, $application);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'review_type' => ['nullable', Rule::enum(ReviewType::class)],
            'classification_reason' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
