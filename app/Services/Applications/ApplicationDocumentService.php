<?php

namespace App\Services\Applications;

use App\Enums\ApplicationRevisionStatus;
use App\Enums\ApplicationStage;
use App\Enums\ApplicationStatus;
use App\Enums\RequirementStatus;
use App\Enums\UserRole;
use App\Models\ApplicationDocument;
use App\Models\ApplicationRevision;
use App\Models\ApplicationRevisionRequirement;
use App\Models\DocumentRequirement;
use App\Models\ResearchApplication;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\Settings\DeadlineProcessAvailability;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Stores private, versioned application documents and preserves replacement history.
 */
class ApplicationDocumentService
{
    /**
     * Maps server-detected MIME types to safe, non-executable storage extensions.
     *
     * @var array<string, string>
     */
    public const ALLOWED_MIME_EXTENSIONS = [
        'application/pdf' => 'pdf',
        'application/msword' => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'application/vnd.ms-excel' => 'xls',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
    ];

    /**
     * Limit each requirement upload to one hundred megabytes.
     */
    public const MAX_FILE_KILOBYTES = 102400;

    public function __construct(
        private readonly AuditLogService $auditLog,
        private readonly DeadlineProcessAvailability $deadlines,
    ) {}

    /**
     * Store a new current version after ownership, requirement, MIME, and state validation.
     */
    public function upload(
        User $actor,
        ResearchApplication $application,
        DocumentRequirement $requirement,
        UploadedFile $file,
    ): ApplicationDocument {
        Gate::forUser($actor)->authorize('upload', $application);

        // The route-bound requirement must remain active and applicable to this application's research type.
        if (! $requirement->is_active || ! $requirement->appliesTo($application->research_type)) {
            throw ValidationException::withMessages([
                'document' => 'The selected requirement is not active for this research type.',
            ]);
        }

        // Use the server-inspected MIME type, not the browser-provided filename extension.
        $mimeType = (string) $file->getMimeType();
        $extension = self::ALLOWED_MIME_EXTENSIONS[$mimeType] ?? null;

        if ($extension === null) {
            throw ValidationException::withMessages([
                'document' => 'Upload a PDF, Word document, Excel workbook, JPEG image, or PNG image.',
            ]);
        }

        $fileHash = hash_file('sha256', (string) $file->getRealPath());

        if (! is_string($fileHash)) {
            throw ValidationException::withMessages([
                'document' => 'The document could not be verified securely.',
            ]);
        }

        // Randomized paths prevent traversal, collisions, and disclosure of original filenames.
        $directory = "applications/{$application->id}/requirements/{$requirement->id}";
        $storedName = Str::uuid().'.'.$extension;
        $storedPath = $file->storeAs($directory, $storedName, 'local');

        if (! is_string($storedPath)) {
            throw ValidationException::withMessages([
                'document' => 'The document could not be stored securely.',
            ]);
        }

        try {
            // Lock the application and current version rows so rapid replacements receive stable versions.
            return DB::transaction(function () use (
                $actor,
                $application,
                $requirement,
                $file,
                $storedPath,
                $mimeType,
                $fileHash,
            ): ApplicationDocument {
                $lockedApplication = ResearchApplication::query()
                    ->whereKey($application->id)
                    ->lockForUpdate()
                    ->firstOrFail();
                Gate::forUser($actor)->authorize('upload', $lockedApplication);

                $currentDocuments = ApplicationDocument::query()
                    ->where('research_application_id', $lockedApplication->id)
                    ->where('document_requirement_id', $requirement->id)
                    ->lockForUpdate()
                    ->get();
                $current = $currentDocuments->firstWhere('is_current', true);
                // Replacements within one revision cycle retain the same displayed document version.
                $documentVersion = max(1, (int) $lockedApplication->current_revision_cycle);

                // Retain previous private files and database history while moving the current pointer atomically.
                ApplicationDocument::query()
                    ->whereIn('id', $currentDocuments->pluck('id'))
                    ->where('is_current', true)
                    ->update(['is_current' => false]);

                $document = ApplicationDocument::create([
                    'research_application_id' => $lockedApplication->id,
                    'document_requirement_id' => $requirement->id,
                    'uploaded_by_user_id' => $actor->id,
                    'original_file_name' => Str::limit(basename($file->getClientOriginalName()), 255, ''),
                    'stored_file_path' => $storedPath,
                    'mime_type' => $mimeType,
                    'file_size_bytes' => $file->getSize(),
                    'file_sha256' => $fileHash,
                    'document_version' => $documentVersion,
                    'validation_status' => RequirementStatus::Completed,
                    'is_current' => true,
                    'uploaded_at' => now(),
                ]);

                // Keep the editable workflow in Document Submission until formal submission succeeds.
                $lockedApplication->update([
                    'current_stage' => ApplicationStage::DocumentSubmission,
                    'status_updated_at' => now(),
                ]);

                $this->auditLog->record(
                    $actor,
                    $current ? 'application.requirement_replaced' : 'application.requirement_uploaded',
                    $document,
                    [
                        'application_id' => $lockedApplication->id,
                        'requirement_code' => $requirement->code,
                        'document_version' => $documentVersion,
                        'mime_type' => $mimeType,
                        'file_size_bytes' => $file->getSize(),
                        'result' => $current ? 'replaced' : 'uploaded',
                    ],
                );

                return $document->load('requirement');
            }, 3);
        } catch (Throwable $exception) {
            // Remove only the uncommitted new private file; previously stored versions remain untouched.
            Storage::disk('local')->delete($storedPath);

            throw $exception;
        }
    }

    /**
     * Store a required Applicant revision as a new immutable document version.
     */
    public function uploadRevision(
        User $actor,
        ResearchApplication $application,
        ApplicationRevision $revision,
        ApplicationRevisionRequirement $revisionRequirement,
        UploadedFile $file,
    ): ApplicationDocument {
        Gate::forUser($actor)->authorize('submitRevision', $application);
        abort_unless($revision->research_application_id === $application->id, 404);
        abort_unless($revisionRequirement->application_revision_id === $revision->id, 404);

        $mimeType = (string) $file->getMimeType();
        $extension = self::ALLOWED_MIME_EXTENSIONS[$mimeType] ?? null;
        if ($extension === null) {
            throw ValidationException::withMessages([
                'document' => 'Upload a PDF, Word document, Excel workbook, JPEG image, or PNG image.',
            ])->errorBag('revisionUpload');
        }

        $fileHash = hash_file('sha256', (string) $file->getRealPath());
        if (! is_string($fileHash)) {
            throw ValidationException::withMessages([
                'document' => 'The revised document could not be verified securely.',
            ])->errorBag('revisionUpload');
        }

        $directory = "applications/{$application->id}/revisions/{$revision->revision_number}/requirements/{$revisionRequirement->document_requirement_id}";
        $storedPath = $file->storeAs($directory, Str::uuid().'.'.$extension, 'local');
        if (! is_string($storedPath)) {
            throw ValidationException::withMessages([
                'document' => 'The revised document could not be stored securely.',
            ])->errorBag('revisionUpload');
        }

        try {
            return DB::transaction(function () use (
                $actor,
                $application,
                $revision,
                $revisionRequirement,
                $file,
                $mimeType,
                $fileHash,
                $storedPath,
            ): ApplicationDocument {
                $lockedApplication = ResearchApplication::query()
                    ->whereKey($application->id)
                    ->lockForUpdate()
                    ->firstOrFail();
                $lockedRevision = ApplicationRevision::query()
                    ->whereKey($revision->id)
                    ->where('research_application_id', $lockedApplication->id)
                    ->lockForUpdate()
                    ->firstOrFail();
                $lockedRequirement = ApplicationRevisionRequirement::query()
                    ->whereKey($revisionRequirement->id)
                    ->where('application_revision_id', $lockedRevision->id)
                    ->with('requirement')
                    ->lockForUpdate()
                    ->firstOrFail();
                Gate::forUser($actor)->authorize('submitRevision', $lockedApplication);

                if ($lockedApplication->application_status !== ApplicationStatus::RevisionWindowOpen
                    || $lockedRevision->status !== ApplicationRevisionStatus::PendingUploads) {
                    throw ValidationException::withMessages([
                        'document' => 'This revision is no longer accepting document replacements.',
                    ])->errorBag('revisionUpload');
                }

                $this->deadlines->assertOpen(
                    'revision-period',
                    UserRole::Applicant,
                    'Applicant revision submission',
                );
                if ($lockedRevision->due_at->isPast()) {
                    throw ValidationException::withMessages([
                        'document' => 'The application-specific revision deadline has passed.',
                    ])->errorBag('revisionUpload');
                }

                $targetVersion = ((int) $lockedRevision->revision_number) + 1;
                $documents = ApplicationDocument::query()
                    ->where('research_application_id', $lockedApplication->id)
                    ->where('document_requirement_id', $lockedRequirement->document_requirement_id)
                    ->lockForUpdate()
                    ->get();
                $current = $documents->firstWhere('is_current', true);

                if ($current
                    && (int) $current->document_version === $targetVersion
                    && hash_equals((string) $current->file_sha256, $fileHash)) {
                    Storage::disk('local')->delete($storedPath);
                    $lockedRequirement->update([
                        'replacement_application_document_id' => $current->id,
                    ]);

                    return $current->load('requirement');
                }

                ApplicationDocument::query()
                    ->whereIn('id', $documents->pluck('id'))
                    ->where('is_current', true)
                    ->update(['is_current' => false]);

                $document = ApplicationDocument::create([
                    'research_application_id' => $lockedApplication->id,
                    'document_requirement_id' => $lockedRequirement->document_requirement_id,
                    'uploaded_by_user_id' => $actor->id,
                    'original_file_name' => Str::limit(basename($file->getClientOriginalName()), 255, ''),
                    'stored_file_path' => $storedPath,
                    'mime_type' => $mimeType,
                    'file_size_bytes' => $file->getSize(),
                    'file_sha256' => $fileHash,
                    'document_version' => $targetVersion,
                    'validation_status' => RequirementStatus::Completed,
                    'is_current' => true,
                    'uploaded_at' => now(),
                ]);
                $lockedRequirement->update([
                    'replacement_application_document_id' => $document->id,
                ]);

                $this->auditLog->record($actor, 'application.revision_document_uploaded', $document, [
                    'application_id' => $lockedApplication->id,
                    'application_revision_id' => $lockedRevision->id,
                    'revision_number' => $lockedRevision->revision_number,
                    'requirement_code' => $lockedRequirement->requirement?->code,
                    'document_version' => $targetVersion,
                    'mime_type' => $mimeType,
                    'file_size_bytes' => $file->getSize(),
                    'result' => $current ? 'replaced' : 'uploaded',
                ]);

                return $document->load('requirement');
            }, 3);
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($storedPath);

            throw $exception;
        }
    }

    /**
     * Detach the current version from a requirement without deleting its private history.
     */
    public function remove(
        User $actor,
        ResearchApplication $application,
        ApplicationDocument $document,
    ): void {
        $this->assertBelongsTo($application, $document);
        Gate::forUser($actor)->authorize('upload', $application);

        DB::transaction(function () use ($actor, $application, $document): void {
            $lockedApplication = ResearchApplication::query()
                ->whereKey($application->id)
                ->lockForUpdate()
                ->firstOrFail();
            Gate::forUser($actor)->authorize('upload', $lockedApplication);

            $lockedDocument = ApplicationDocument::query()
                ->whereKey($document->id)
                ->where('research_application_id', $lockedApplication->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $lockedDocument->is_current) {
                throw ValidationException::withMessages([
                    'document' => 'This document is no longer the current requirement file.',
                ]);
            }

            $lockedDocument->update(['is_current' => false]);
            $lockedApplication->update([
                'current_stage' => ApplicationStage::DocumentSubmission->value,
                'status_updated_at' => now(),
            ]);

            $this->auditLog->record($actor, 'application.requirement_removed', $lockedDocument, [
                'application_id' => $lockedApplication->id,
                'requirement_code' => $lockedDocument->requirement?->code,
                'document_version' => $lockedDocument->document_version,
                'result' => 'detached',
            ]);
        }, 3);
    }

    /**
     * Verify that a route-bound document belongs to the route-bound application.
     */
    public function assertBelongsTo(
        ResearchApplication $application,
        ApplicationDocument $document,
    ): void {
        // A mismatched nested identifier receives a generic not-found response to prevent record probing.
        abort_unless($document->research_application_id === $application->id, 404);
    }
}
