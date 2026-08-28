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
use App\Rules\SafeApplicationDocumentUpload;
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
     * Limit each requirement upload to one hundred megabytes.
     */
    public const MAX_FILE_KILOBYTES = SafeApplicationDocumentUpload::MAX_FILE_KILOBYTES;

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

        // Require the client extension, server MIME inspection, and magic bytes to agree.
        $inspection = SafeApplicationDocumentUpload::inspect($file);
        if (isset($inspection['error'])) {
            throw ValidationException::withMessages([
                'document' => $inspection['error'],
            ]);
        }
        $mimeType = $inspection['mime_type'];
        $extension = $inspection['storage_extension'];

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

        $obsoletePaths = [];
        try {
            // Lock the application and current version rows so rapid replacements receive stable versions.
            $document = DB::transaction(function () use (
                $actor,
                $application,
                $requirement,
                $file,
                $storedPath,
                $mimeType,
                $fileHash,
                &$obsoletePaths,
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
                // Initial-submission replacements remain Version 1 until a reviewed revision requires a new version.
                $documentVersion = max(1, (int) ($current?->document_version ?? 1));

                // Files replaced before the formal submission boundary are drafts, not
                // business history. Remove those obsolete bytes and rows once the new
                // verified upload is committed; submitted artifacts remain immutable.
                $obsoleteDrafts = $currentDocuments->filter(
                    fn (ApplicationDocument $item): bool => $item->formally_submitted_at === null,
                );
                $obsoletePaths = $obsoleteDrafts->pluck('stored_file_path')->filter()->all();
                ApplicationDocument::query()->whereIn('id', $obsoleteDrafts->pluck('id'))->delete();

                ApplicationDocument::query()
                    ->whereIn('id', $currentDocuments->pluck('id')->diff($obsoleteDrafts->pluck('id')))
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

            Storage::disk('local')->delete($obsoletePaths);

            return $document;
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

        $inspection = SafeApplicationDocumentUpload::inspect($file);
        if (isset($inspection['error'])) {
            throw ValidationException::withMessages([
                'document' => $inspection['error'],
            ])->errorBag('revisionUpload');
        }
        $mimeType = $inspection['mime_type'];
        $extension = $inspection['storage_extension'];

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

        $obsoletePaths = [];
        try {
            $document = DB::transaction(function () use (
                $actor,
                $application,
                $revision,
                $revisionRequirement,
                $file,
                $mimeType,
                $fileHash,
                $storedPath,
                &$obsoletePaths,
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

                $documents = ApplicationDocument::query()
                    ->where('research_application_id', $lockedApplication->id)
                    ->where('document_requirement_id', $lockedRequirement->document_requirement_id)
                    ->lockForUpdate()
                    ->get();
                $current = $documents->firstWhere('is_current', true);
                $existingRevisionReplacement = $documents->firstWhere(
                    'id',
                    $lockedRequirement->replacement_application_document_id,
                );
                // Increment only for the first actual replacement of this requirement in this review cycle.
                // Re-uploading the same requirement before submission keeps that business version.
                $targetVersion = $existingRevisionReplacement
                    ? (int) $existingRevisionReplacement->document_version
                    : max(1, (int) $documents->max('document_version')) + 1;

                if ($targetVersion > 4) {
                    throw ValidationException::withMessages([
                        'document' => 'The maximum business document version (Version 4) has been reached.',
                    ])->errorBag('revisionUpload');
                }

                if ($current
                    && (int) $current->document_version === $targetVersion
                    && hash_equals((string) $current->file_sha256, $fileHash)) {
                    Storage::disk('local')->delete($storedPath);
                    $lockedRequirement->update([
                        'replacement_application_document_id' => $current->id,
                    ]);

                    return $current->load('requirement');
                }

                if ($existingRevisionReplacement && $existingRevisionReplacement->formally_submitted_at === null) {
                    $obsoletePaths[] = $existingRevisionReplacement->stored_file_path;
                    ApplicationDocument::query()->whereKey($existingRevisionReplacement->id)->delete();
                    $documents = $documents->reject(
                        fn (ApplicationDocument $item): bool => $item->id === $existingRevisionReplacement->id,
                    );
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

            Storage::disk('local')->delete($obsoletePaths);

            return $document;
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

        $obsoletePath = null;
        DB::transaction(function () use ($actor, $application, $document, &$obsoletePath): void {
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

            $deleteDraft = $lockedDocument->formally_submitted_at === null;
            if ($deleteDraft) {
                $obsoletePath = $lockedDocument->stored_file_path;
            } else {
                $lockedDocument->update(['is_current' => false]);
            }
            $lockedApplication->update([
                'current_stage' => ApplicationStage::DocumentSubmission->value,
                'status_updated_at' => now(),
            ]);

            $this->auditLog->record($actor, 'application.requirement_removed', $lockedDocument, [
                'application_id' => $lockedApplication->id,
                'requirement_code' => $lockedDocument->requirement?->code,
                'document_version' => $lockedDocument->document_version,
                'result' => $deleteDraft ? 'deleted_unsubmitted_draft' : 'detached',
            ]);

            if ($deleteDraft) {
                $lockedDocument->delete();
            }
        }, 3);

        if ($obsoletePath !== null) {
            Storage::disk('local')->delete($obsoletePath);
        }
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
