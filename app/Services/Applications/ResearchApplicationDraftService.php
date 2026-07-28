<?php

namespace App\Services\Applications;

use App\Enums\ApplicantType;
use App\Enums\ApplicationStage;
use App\Enums\ApplicationStatus;
use App\Models\ResearchApplication;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Creates or updates the applicant's single editable application draft.
 */
class ResearchApplicationDraftService
{
    public function __construct(private readonly AuditLogService $auditLog) {}

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
                $candidate = ResearchApplication::query()->createOrFirst(
                    ['draft_owner_user_id' => $actor->id],
                    [
                        ...$attributes,
                        'application_code' => $this->uniqueCode(),
                        'applicant_user_id' => $actor->id,
                        'applicant_type' => ($actor->applicant_type ?? ApplicantType::Student)->value,
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

            // A formally returned record re-enters the draft boundary before it becomes visible for resubmission.
            if ($application->application_status === ApplicationStatus::ReturnedByAdviser) {
                $application->application_status = ApplicationStatus::Incomplete;
                $application->submitted_at = null;
            }

            // Copy only Form Request-validated fields and never accept applicant/adviser email snapshots.
            $application->fill([
                ...$attributes,
                'draft_owner_user_id' => $actor->id,
                'applicant_type' => ($actor->applicant_type ?? ApplicantType::Student)->value,
                'current_stage' => ApplicationStage::DocumentSubmission,
                'status_updated_at' => now(),
            ]);
            $application->save();

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

            return $application->refresh();
        }, 3);
    }

    /**
     * Archive one unsubmitted draft while preserving its audit and private-document history.
     */
    public function discard(User $actor, ResearchApplication $application): ResearchApplication
    {
        Gate::forUser($actor)->authorize('discard', $application);

        return DB::transaction(function () use ($actor, $application): ResearchApplication {
            $locked = ResearchApplication::query()
                ->whereKey($application->id)
                ->lockForUpdate()
                ->firstOrFail();
            Gate::forUser($actor)->authorize('discard', $locked);

            $locked->update([
                'application_status' => ApplicationStatus::Archived->value,
                'current_stage' => ApplicationStage::Completed->value,
                'draft_owner_user_id' => null,
                'status_updated_at' => now(),
            ]);

            $this->auditLog->record($actor, 'application.draft_discarded', $locked, [
                'result' => 'archived',
            ]);

            return $locked->refresh();
        }, 3);
    }

    /**
     * Generate a non-sequential public application code without exposing an internal database ID.
     */
    private function uniqueCode(): string
    {
        // Retry a bounded number of random candidates before surfacing an exceptional database condition.
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $code = 'ECRATS-'.now()->format('Y').'-'.Str::upper(Str::random(8));

            if (! ResearchApplication::query()->where('application_code', $code)->exists()) {
                return $code;
            }
        }

        throw new RuntimeException('Unable to generate a unique application code.');
    }
}
