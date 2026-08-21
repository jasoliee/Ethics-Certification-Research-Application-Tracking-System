<?php

namespace App\Services\Applications;

use App\Enums\ApplicantType;
use App\Enums\ApplicationStage;
use App\Enums\ApplicationStatus;
use App\Models\ResearchApplication;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\Settings\AcademicTermResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Creates or updates the applicant's single editable application draft.
 */
class ResearchApplicationDraftService
{
    public function __construct(
        private readonly AuditLogService $auditLog,
        private readonly AcademicTermResolver $terms,
        private readonly ApplicationCodeGenerator $codes,
        private readonly ApplicationSubmissionLimit $submissionLimit,
    ) {}

    /**
     * Persist validated information and advance the editable draft to document submission.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function save(
        User $actor,
        array $attributes,
        ?ResearchApplication $requestedApplication = null,
    ): ResearchApplication {
        // Requested records are authorized before the transaction and again after row locking.
        if ($requestedApplication) {
            Gate::forUser($actor)->authorize('update', $requestedApplication);
        } else {
            Gate::forUser($actor)->authorize('create', ResearchApplication::class);
        }

        return DB::transaction(function () use ($actor, $attributes, $requestedApplication): ResearchApplication {
            $recipientNames = collect($attributes['certificate_recipients'] ?? [])
                ->map(fn (string $name): string => Str::squish($name))
                ->filter()
                ->values();
            unset($attributes['certificate_recipients']);

            if ($recipientNames->map(fn (string $name): string => mb_strtolower($name))->unique()->count() !== $recipientNames->count()) {
                throw ValidationException::withMessages([
                    'certificate_recipients' => 'Each certificate recipient name may be added only once.',
                ]);
            }

            // Serialize draft creation and the submitted-application limit for one Applicant.
            User::query()->whereKey($actor->id)->lockForUpdate()->firstOrFail();

            // Lock the unique draft slot first so every save follows the same row-lock order.
            $existingDraft = ResearchApplication::query()
                ->where('draft_owner_user_id', $actor->id)
                ->lockForUpdate()
                ->first();

            // Lock a route-bound record separately because returned records do not occupy the draft slot.
            $application = $requestedApplication
                ? ResearchApplication::query()->whereKey($requestedApplication->id)->lockForUpdate()->firstOrFail()
                : $existingDraft;

            // Keep a returned record from colliding with another editable draft's unique database slot.
            if ($existingDraft && $application && ! $existingDraft->is($application)) {
                throw ValidationException::withMessages([
                    'application' => 'Finish your existing draft before reopening another application.',
                ]);
            }

            // Recheck authorization against the locked current database state.
            if ($application) {
                Gate::forUser($actor)->authorize('update', $application);
            }

            $created = false;

            // Create-or-resolve the unique draft slot so concurrent Start requests converge on one row.
            if (! $application) {
                // The applicant row lock makes the formal-submission count authoritative under concurrent requests.
                $this->submissionLimit->assertCanCreate($actor);
                $applicantType = $actor->applicant_type ?? ApplicantType::Student;
                $candidate = ResearchApplication::query()->createOrFirst(
                    ['draft_owner_user_id' => $actor->id],
                    [
                        ...$attributes,
                        'academic_term_id' => $this->terms->current()?->id,
                        'application_code' => $this->codes->next(
                            $applicantType,
                            (string) $attributes['institution'],
                        ),
                        'applicant_user_id' => $actor->id,
                        'applicant_type' => $applicantType->value,
                        'application_type' => 'new_application',
                        'application_status' => ApplicationStatus::Draft,
                        'current_stage' => ApplicationStage::ApplicationInformation,
                        'status_updated_at' => now(),
                    ],
                );
                $created = $candidate->wasRecentlyCreated;
                $application = ResearchApplication::query()
                    ->whereKey($candidate->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                // A concurrent winner is treated as the actor's existing draft and must pass current policy state.
                if (! $created) {
                    Gate::forUser($actor)->authorize('update', $application);
                }
            }

            // Adviser corrections remain part of the initial submission and do not consume a review-revision cycle.
            if ($application->application_status === ApplicationStatus::ReturnedByAdviser) {
                $application->application_status = ApplicationStatus::Incomplete;
            }

            // Copy only Form Request-validated fields and never accept applicant/adviser email snapshots.
            $application->fill([
                ...$attributes,
                // Structured dates replace legacy prose whenever an Applicant saves the current form.
                'expected_duration' => null,
                'draft_owner_user_id' => $actor->id,
                'applicant_type' => ($actor->applicant_type ?? ApplicantType::Student)->value,
                'current_stage' => ApplicationStage::DocumentSubmission,
                'status_updated_at' => now(),
            ]);
            $application->save();
            $application->certificateRecipients()->delete();
            $application->certificateRecipients()->createMany(
                $recipientNames->map(fn (string $name, int $index): array => [
                    'recipient_name' => $name,
                    'normalized_name' => mb_strtolower($name),
                    'sort_order' => $index + 1,
                ])->all(),
            );

            // Record draft creation once while keeping every information save independently traceable.
            if ($created) {
                $this->auditLog->record($actor, 'application.draft_created', $application, [
                    'result' => 'created',
                ]);
            }

            // Store field names rather than potentially sensitive abstract or participant text.
            $this->auditLog->record($actor, 'application.information_updated', $application, [
                'updated_fields' => array_keys($attributes),
                'result' => 'updated',
            ]);

            return $application->refresh()->load('certificateRecipients');
        }, 3);
    }

    /**
     * Permanently remove one unsubmitted draft and its private application directory.
     */
    public function discard(User $actor, ResearchApplication $application): void
    {
        Gate::forUser($actor)->authorize('discard', $application);

        $applicationId = DB::transaction(function () use ($actor, $application): int {
            $locked = ResearchApplication::query()
                ->whereKey($application->id)
                ->lockForUpdate()
                ->firstOrFail();
            Gate::forUser($actor)->authorize('discard', $locked);

            $this->auditLog->record($actor, 'application.draft_discarded', metadata: [
                'application_id' => $locked->id,
                'application_code' => $locked->application_code,
                'document_count' => $locked->documents()->count(),
                'result' => 'deleted',
            ], academicTerm: $locked->academicTerm()->first());
            $locked->delete();

            return $locked->id;
        }, 3);

        $disk = Storage::disk('local');
        $directory = "applications/{$applicationId}";

        try {
            $disk->deleteDirectory($directory);
        } catch (\RuntimeException) {
            // A synchronized/local filesystem can make a child disappear while
            // Flysystem is walking the directory. Treat an already-removed
            // directory as success; otherwise retry once and surface failure.
            clearstatcache(true);

            if ($disk->directoryExists($directory)) {
                $disk->deleteDirectory($directory);
            }
        }
    }
}
