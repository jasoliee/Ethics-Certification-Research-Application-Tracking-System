<?php

namespace App\Http\Requests\Applications;

use App\Models\ResearchApplication;
use App\Services\Applications\ApplicationInformationService;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Authorizes and validates edits to an applicant-owned eligible draft.
 */
class UpdateResearchApplicationRequest extends FormRequest
{
    /**
     * Resolve the route-bound record through the policy before validation runs.
     */
    public function authorize(): bool
    {
        $application = $this->route('researchApplication');

        return $application instanceof ResearchApplication
            && ($this->user()?->can('update', $application) ?? false);
    }

    /**
     * Preserve the application's current option values while applying the shared rules.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return app(ApplicationInformationService::class)->rules(
            $this->user(),
            $this->route('researchApplication'),
        );
    }

    /**
     * Reuse shared messages for option and Adviser validation.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return app(ApplicationInformationService::class)->messages();
    }
}
