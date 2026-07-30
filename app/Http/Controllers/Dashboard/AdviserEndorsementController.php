<?php

namespace App\Http\Controllers\Dashboard;

use App\Enums\AdviserReturnReason;
use App\Http\Controllers\Controller;
use App\Http\Requests\Adviser\EndorseResearchApplicationRequest;
use App\Http\Requests\Adviser\ReturnResearchApplicationRequest;
use App\Models\ResearchApplication;
use App\Services\Applications\AdviserEndorsementService;
use Illuminate\Http\RedirectResponse;

/**
 * Receives explicit Adviser endorsement and correction decisions.
 */
class AdviserEndorsementController extends Controller
{
    public function endorse(
        EndorseResearchApplicationRequest $request,
        ResearchApplication $researchApplication,
        AdviserEndorsementService $endorsements,
    ): RedirectResponse {
        $endorsements->endorse(
            $request->user(),
            $researchApplication,
            $request->validated('endorsement_remarks'),
        );

        return redirect()
            ->route('adviser.applications.show', $researchApplication)
            ->with('status', 'Application endorsed for RES screening.');
    }

    public function returnForCorrection(
        ReturnResearchApplicationRequest $request,
        ResearchApplication $researchApplication,
        AdviserEndorsementService $endorsements,
    ): RedirectResponse {
        $validated = $request->validated();
        $endorsements->returnForCorrection(
            $request->user(),
            $researchApplication,
            AdviserReturnReason::from($validated['return_reason']),
            $validated['endorsement_remarks'],
        );

        return redirect()
            ->route('adviser.applications.show', $researchApplication)
            ->with('status', 'Application returned to the applicant for correction.');
    }
}
