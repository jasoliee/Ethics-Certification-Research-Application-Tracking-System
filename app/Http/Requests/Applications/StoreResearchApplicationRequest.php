<?php

namespace App\Http\Requests\Applications;

use App\Models\ResearchApplication;
use App\Services\Applications\ApplicationInformationService;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Authorizes and validates creation of the applicant's single editable draft.
 */
class StoreResearchApplicationRequest extends FormRequest
{
    /**
     * Only authenticated applicants passing the application policy may create a draft.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', ResearchApplication::class) ?? false;
    }

    /**
     * Reuse the shared application-information validation contract.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return app(ApplicationInformationService::class)->rules($this->user());
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
