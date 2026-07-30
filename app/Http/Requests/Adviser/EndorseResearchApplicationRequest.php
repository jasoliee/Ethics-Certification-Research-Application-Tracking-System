<?php

namespace App\Http\Requests\Adviser;

use Illuminate\Foundation\Http\FormRequest;

class EndorseResearchApplicationRequest extends FormRequest
{
    protected $errorBag = 'adviserEndorse';

    public function authorize(): bool
    {
        $application = $this->route('researchApplication');

        return $application && $this->user()?->can('decideAsAdviser', $application);
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'endorsement_remarks' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
