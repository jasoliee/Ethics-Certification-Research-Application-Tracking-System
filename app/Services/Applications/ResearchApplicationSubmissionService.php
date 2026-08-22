<?php

namespace App\Services\Applications;

use App\Enums\AccountStatus;
use App\Enums\ApplicationStage;
use App\Enums\ApplicationStatus;
use App\Enums\UserRole;
use App\Models\ApplicationDocument;
use App\Models\ResearchApplication;
use App\Models\User;
use App\Notifications\DashboardUpdateNotification;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Performs the idempotent, fully revalidated transition from applicant draft to Adviser review.
 */
class ResearchApplicationSubmissionService
{
    public function __construct(
        private readonly ApplicationInformationService $information,
        private readonly ApplicationRequirementService $requirements,
        private readonly ApplicationSubmissionWindow $submissionWindow,
        private readonly ApplicationSubmissionLimit $submissionLimit,
        private readonly AuditLogService $auditLog,
    ) {}

    /**
     * Submit exactly once after locking and rechecking ownership, window, information, documents, and Adviser.
     */
    public function submit(User $actor, ResearchApplication $application): ResearchApplication
    {
        return DB::transaction(function () use ($actor, $application): ResearchApplication {
            // Match draft creation's lock order and serialize the Applicant's formal-submission count.
            User::query()->whereKey($actor->id)->lockForUpdate()->firstOrFail();

            // Lock the authoritative application so repeated clicks cannot duplicate transitions or notifications.
            $locked = ResearchApplication::query()
                ->whereKey($application->id)
                ->lockForUpdate()
                ->firstOrFail();

            // A repeated owner request after successful submission is a no-op with the original timestamp.
            if ($actor->role === UserRole::Applicant
                && $locked->applicant_user_id === $actor->id
                && $locked->submitted_at !== null
                && $locked->application_status === ApplicationStatus::SubmittedToAdviser) {
                return $locked;
            }

            // Policy authorization is repeated after locking so stale browser state cannot cross the draft boundary.
            Gate::forUser($actor)->authorize('submit', $locked);
            $this->submissionLimit->assertCanSubmit($actor, $locked);
            $this->submissionWindow->assertOpen();
            $this->information->validateApplication($locked);
            $this->requirements->assertReady($locked);

            // Resolve the assigned Adviser from the active user table instead of trusting a saved email address.
            $adviser = User::query()
                ->whereKey($locked->adviser_user_id)
                ->where('role', UserRole::Adviser->value)
                ->where('account_status', AccountStatus::Active->value)
                ->whereNotNull('password_setup_completed_at')
                ->first();

            if (! $adviser) {
                throw ValidationException::withMessages([
                    'adviser_user_id' => 'The assigned Research Adviser is no longer eligible. Update the application before submitting.',
                ]);
            }

            // Cross the formal boundary once and release the applicant's unique editable-draft slot.
            $locked->update([
                'application_status' => ApplicationStatus::SubmittedToAdviser->value,
                'current_stage' => ApplicationStage::AdviserReview->value,
                'draft_owner_user_id' => null,
                'submitted_at' => $locked->submitted_at ?? now(),
                'status_updated_at' => now(),
            ]);
            ApplicationDocument::query()
                ->where('research_application_id', $locked->id)
                ->where('is_current', true)
                ->whereNull('formally_submitted_at')
                ->update(['formally_submitted_at' => $locked->submitted_at]);

            $this->auditLog->record($actor, 'application.submitted', $locked, [
                'result' => 'submitted_to_adviser',
            ]);

            // The database notification shares the transaction, preventing a notification without a submission.
            $adviser->notify(new DashboardUpdateNotification([
                'title' => 'New ethics application',
                'message' => 'A new ethics application has been submitted for your review.',
                'icon' => 'file-text',
                'tone' => 'orange',
                'route' => 'adviser.applications.show',
                'route_parameters' => ['researchApplication' => $locked->id],
                'academic_term_id' => $locked->academic_term_id,
            ]));

            $this->auditLog->record($actor, 'application.adviser_notified', $locked, [
                'adviser_user_id' => $adviser->id,
                'result' => 'notified',
            ]);

            return $locked->refresh();
        }, 3);
    }
}
