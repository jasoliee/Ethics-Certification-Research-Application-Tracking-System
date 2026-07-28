<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Applications\UploadApplicationDocumentRequest;
use App\Models\ApplicationDocument;
use App\Models\DocumentRequirement;
use App\Models\ResearchApplication;
use App\Services\Applications\ApplicationDocumentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Handles private application upload, inline-preview, and download endpoints.
 */
class ApplicationDocumentController extends Controller
{
    /**
     * Upload or replace one active requirement document on an eligible applicant draft.
     */
    public function store(
        UploadApplicationDocumentRequest $request,
        ResearchApplication $researchApplication,
        DocumentRequirement $documentRequirement,
        ApplicationDocumentService $documents,
    ): RedirectResponse {
        $documents->upload(
            $request->user(),
            $researchApplication,
            $documentRequirement,
            $request->file('document'),
        );

        return back()->with('status', 'Requirement document uploaded securely.');
    }

    /**
     * Detach the route-bound current document while preserving private version history.
     */
    public function destroy(
        Request $request,
        ResearchApplication $researchApplication,
        ApplicationDocument $applicationDocument,
        ApplicationDocumentService $documents,
    ): RedirectResponse {
        $documents->remove(
            $request->user(),
            $researchApplication,
            $applicationDocument,
        );

        return back()->with('status', 'Requirement document removed. Upload a replacement before submission.');
    }

    /**
     * Stream browser-safe documents inline through an authorized controller route.
     */
    public function preview(
        Request $request,
        ResearchApplication $researchApplication,
        ApplicationDocument $applicationDocument,
        ApplicationDocumentService $documents,
    ): StreamedResponse|RedirectResponse {
        // Verify nested ownership before applying the parent application's role-aware policy.
        $documents->assertBelongsTo($researchApplication, $applicationDocument);
        Gate::authorize('viewDocument', $researchApplication);

        // Unsupported formats use the secure attachment route instead of unsafe inline rendering.
        if (! $applicationDocument->supportsInlinePreview()) {
            return redirect()
                ->route($this->downloadRoute($request), [$researchApplication, $applicationDocument])
                ->with('status', 'This file type is available as a secure download.');
        }

        return $this->fileResponse($applicationDocument, 'inline');
    }

    /**
     * Stream every authorized document as an attachment without exposing its private path.
     */
    public function download(
        Request $request,
        ResearchApplication $researchApplication,
        ApplicationDocument $applicationDocument,
        ApplicationDocumentService $documents,
    ): StreamedResponse {
        // Verify nested ownership before applying the parent application's role-aware policy.
        $documents->assertBelongsTo($researchApplication, $applicationDocument);
        Gate::authorize('viewDocument', $researchApplication);

        return $this->fileResponse($applicationDocument, 'attachment');
    }

    /**
     * Build a private-disk response with defensive content headers.
     */
    private function fileResponse(
        ApplicationDocument $document,
        string $disposition,
    ): StreamedResponse {
        $disk = Storage::disk('local');
        abort_unless($disk->exists($document->stored_file_path), 404);

        // Prevent uploaded content from gaining ambient page privileges during inline rendering.
        return $disk->response(
            $document->stored_file_path,
            $document->original_file_name,
            [
                'Content-Type' => $document->mime_type ?? 'application/octet-stream',
                'Content-Security-Policy' => "default-src 'none'; img-src 'self' data:; style-src 'unsafe-inline'; sandbox",
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, no-store, max-age=0',
            ],
            $disposition,
        );
    }

    /**
     * Keep unsupported-preview fallbacks inside the current role's authorized route group.
     */
    private function downloadRoute(Request $request): string
    {
        return match (true) {
            $request->routeIs('adviser.*') => 'adviser.applications.documents.download',
            $request->routeIs('res.*') => 'res.applications.documents.download',
            default => 'applicant.applications.documents.download',
        };
    }
}
