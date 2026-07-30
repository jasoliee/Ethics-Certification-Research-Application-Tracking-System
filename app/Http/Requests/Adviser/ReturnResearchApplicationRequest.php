<?php

namespace App\Http\Requests\Adviser;

use App\Enums\AdviserReturnReason;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReturnResearchApplicationRequest extends FormRequest
{
    protected $errorBag = 'adviserReturn';

    public function authorize(): bool
    {
        $application = $this->route('researchApplication');

        return $application && $this->user()?->can('decideAsAdviser', $application);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'return_reason' => ['required', Rule::enum(AdviserReturnReason::class)],
            'endorsement_remarks' => ['required', 'string', 'max:500'],
        ];
    }
}
