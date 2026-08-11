<?php

namespace App\Http\Requests\ResLead;

use App\Enums\ReviewDecision;
use App\Models\ResearchApplication;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReleaseApplicationDecisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $application = $this->route('researchApplication');

        return $application instanceof ResearchApplication
            && ($this->user()?->can('releaseDecision', $application) ?? false);
    }

    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::enum(ReviewDecision::class)],
            'comment_ids' => ['nullable', 'array', 'max:200'],
            'comment_ids.*' => ['integer', 'distinct'],
            'revision_document_ids' => ['nullable', 'array', 'max:100'],
            'revision_document_ids.*' => ['integer', 'distinct'],
        ];
    }
}
