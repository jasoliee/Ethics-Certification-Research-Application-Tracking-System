<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Applications\UploadApplicationDocumentRequest;
use App\Http\Requests\Applications\UploadApplicationDocumentsRequest;
use App\Models\ApplicationDocument;
use App\Models\DocumentRequirement;
use App\Models\ResearchApplication;
use App\Services\Applications\ApplicationDocumentService;
use App\Services\Applications\ApplicationRequirementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

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
        ApplicationRequirementService $requirements,
    ): RedirectResponse|JsonResponse {
        $documents->upload(
            $request->user(),
            $researchApplication,
            $documentRequirement,
            $request->file('document'),
        );

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Document uploaded.',
                ...$this->requirementRowPayload($researchApplication, $documentRequirement, $requirements),
            ]);
        }

        return back()->with('status', 'Document uploaded.');
    }

    /**
     * Validate and upload every selected requirement independently for per-file feedback.
     */
    public function storeMany(
        UploadApplicationDocumentsRequest $request,
        ResearchApplication $researchApplication,
        ApplicationDocumentService $documents,
        ApplicationRequirementService $requirements,
    ): JsonResponse {
        $uploadedFiles = $request->file('documents', []);
        $requirementIds = collect(array_keys(is_array($uploadedFiles) ? $uploadedFiles : []))
            ->filter(fn (mixed $id): bool => ctype_digit((string) $id))
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values();
        $availableRequirements = DocumentRequirement::query()
            ->whereKey($requirementIds)
            ->get()
            ->keyBy('id');
        $successIds = [];
        $errors = [];

        foreach ($uploadedFiles as $requirementId => $file) {
            $id = ctype_digit((string) $requirementId) ? (int) $requirementId : 0;
            $requirement = $availableRequirements->get($id);

            if (! $requirement) {
                $errors[(string) $requirementId] = 'The selected document requirement is unavailable.';

                continue;
            }

            $validator = Validator::make(
                ['document' => $file],
                ['document' => UploadApplicationDocumentRequest::documentRules()],
            );

            if ($validator->fails()) {
                $errors[(string) $id] = $validator->errors()->first('document');

                continue;
            }

            try {
                $documents->upload(
                    $request->user(),
                    $researchApplication,
                    $requirement,
                    $file,
                );
                $successIds[] = $id;
            } catch (ValidationException $exception) {
                $errors[(string) $id] = collect($exception->errors())->flatten()->first()
                    ?? 'This document could not be uploaded.';
            } catch (Throwable $exception) {
                report($exception);
                $errors[(string) $id] = 'This document could not be stored securely. Try again.';
            }
        }

        $summary = $requirements->summary($researchApplication->refresh());
        $summaryItems = $summary['items']->keyBy(
            fn (array $item): int => $item['requirement']->id,
        );
        $successes = collect($successIds)->mapWithKeys(function (int $id) use ($summaryItems, $researchApplication): array {
            $item = $summaryItems->get($id);

            if (! $item) {
                return [];
            }

            return [(string) $id => [
                'message' => 'Document uploaded.',
                'row_html' => view('dashboard.applications.partials.requirement-upload-row', [
                    'application' => $researchApplication,
                    'item' => $item,
                    'canUpload' => true,
                ])->render(),
            ]];
        })->all();

        return response()->json([
            'message' => count($successes).' '.str('document')->plural(count($successes)).' uploaded.',
            'successes' => $successes,
            'errors' => $errors,
            'progress' => $this->progressPayload($summary),
        ]);
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
    ): StreamedResponse|Response {
        // Verify nested ownership before applying the parent application's role-aware policy.
        $documents->assertBelongsTo($researchApplication, $applicationDocument);
        Gate::authorize('viewDocument', $researchApplication);
        abort_unless(Storage::disk('local')->exists($applicationDocument->stored_file_path), 404);

        // Office formats stay in the authorized viewer and receive a safe download fallback.
        if (! $applicationDocument->supportsInlinePreview()) {
            return response()
                ->view('dashboard.applications.document-preview-fallback', [
                    'document' => $applicationDocument,
                    'downloadUrl' => route(
                        $this->downloadRoute($request),
                        [$researchApplication, $applicationDocument],
                    ),
                ])
                ->withHeaders([
                    'Cache-Control' => 'private, no-store, max-age=0',
                    'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; frame-ancestors 'self'; base-uri 'none'; form-action 'self'",
                    'X-Content-Type-Options' => 'nosniff',
                    'X-Frame-Options' => 'SAMEORIGIN',
                ]);
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
            $request->routeIs('reviewer.*') => 'reviewer.applications.documents.download',
            default => 'applicant.applications.documents.download',
        };
    }

    /**
     * Render only the refreshed upload row so other selected browser files remain intact.
     *
     * @return array{requirement_id: int, row_html: string}
     */
    private function requirementRowPayload(
        ResearchApplication $application,
        DocumentRequirement $requirement,
        ApplicationRequirementService $requirements,
    ): array {
        $summary = $requirements->summary($application->refresh());
        $item = $summary['items']
            ->first(fn (array $candidate): bool => $candidate['requirement']->is($requirement));

        abort_unless($item, 404);

        return [
            'requirement_id' => $requirement->id,
            'progress' => $this->progressPayload($summary),
            'row_html' => view('dashboard.applications.partials.requirement-upload-row', [
                'application' => $application,
                'item' => $item,
                'canUpload' => true,
            ])->render(),
        ];
    }

    /**
     * @param  array<string, mixed>  $summary
     * @return array<string, int|bool>
     */
    private function progressPayload(array $summary): array
    {
        return [
            'mandatory_total' => $summary['mandatory_total'],
            'completed_count' => $summary['completed_count'],
            'missing_count' => $summary['missing_count'],
            'pending_count' => $summary['pending_count'],
            'rejected_count' => $summary['rejected_count'],
            'percentage' => $summary['percentage'],
            'ready' => $summary['ready'],
        ];
    }
}
