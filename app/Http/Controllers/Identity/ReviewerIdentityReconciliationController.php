<?php

namespace App\Http\Controllers\Identity;

use App\Http\Controllers\Controller;
use App\Models\ReviewerIdentityReconciliation;
use App\Services\Identity\ReviewerIdentityReconciliationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ReviewerIdentityReconciliationController extends Controller
{
    public function keepSeparate(
        Request $request,
        ReviewerIdentityReconciliation $reviewerIdentityReconciliation,
        ReviewerIdentityReconciliationService $service,
    ): RedirectResponse {
        $validated = $request->validate(['resolution_notes' => ['nullable', 'string', 'max:1000']]);
        $service->keepSeparate(
            $request->user(),
            $reviewerIdentityReconciliation,
            $validated['resolution_notes'] ?? null,
        );

        return back()->with('status', 'The Reviewer and Adviser identities were confirmed as separate records.');
    }

    public function merge(
        Request $request,
        ReviewerIdentityReconciliation $reviewerIdentityReconciliation,
        ReviewerIdentityReconciliationService $service,
    ): RedirectResponse {
        $validated = $request->validate([
            'confirm_merge' => ['required', 'accepted'],
            'resolution_notes' => ['nullable', 'string', 'max:1000'],
        ]);
        $service->merge(
            $request->user(),
            $reviewerIdentityReconciliation,
            $validated['resolution_notes'] ?? null,
        );

        return back()->with('status', 'Reviewer history was linked to the selected Adviser account; the duplicate identity was preserved as inactive.');
    }
}
