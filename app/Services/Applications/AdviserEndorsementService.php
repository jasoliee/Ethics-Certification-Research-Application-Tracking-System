<?php

namespace App\Services\Applications;

use App\Enums\AccountStatus;
use App\Enums\AdviserReturnReason;
use App\Enums\ApplicationStage;
use App\Enums\ApplicationStatus;
use App\Enums\EndorsementStatus;
use App\Enums\UserRole;
use App\Models\Endorsement;
use App\Models\ResearchApplication;
use App\Models\User;
use App\Notifications\DashboardUpdateNotification;
use App\Services\AuditLogService;
use App\Services\Settings\DeadlineProcessAvailability;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Applies one authorized Adviser decision to an initial formal submission.
 */
class AdviserEndorsementService
{
    public function __construct(
        private readonly ApplicationInformationService $information,
        private readonly ApplicationRequirementService $requirements,
        private readonly DeadlineProcessAvailability $deadlines,
        private readonly AuditLogService $auditLog,
    ) {}

    public function endorse(
        User $actor,
        ResearchApplication $application,
        ?string $remarks = null,
    ): Endorsement {
        return $this->decide(
            $actor,
            $application,
            EndorsementStatus::Endorsed,
            null,
            $remarks,
        );
    }

    public function returnForCorrection(
        User $actor,
        ResearchApplication $application,
        AdviserReturnReason $reason,
        string $remarks,
    ): Endorsement {
        return $this->decide(
            $actor,
            $application,
            EndorsementStatus::Returned,
            $reason,
            $remarks,
        );
    }

    private function decide(
        User $actor,
        ResearchApplication $application,
        EndorsementStatus $decision,
        ?AdviserReturnReason $reason,
        ?string $remarks,
    ): Endorsement {
        return DB::transaction(function () use ($actor, $application, $decision, $reason, $remarks): Endorsement {
            // Lock the application before repeating every workflow gate against current database state.
            $locked = ResearchApplication::query()
                ->whereKey($application->id)
                ->lockForUpdate()
                ->firstOrFail();

            Gate::forUser($actor)->authorize('decideAsAdviser', $locked);
            $this->deadlines->assertOpen(
                'adviser-endorsement',
                UserRole::Adviser,
                'Adviser endorsement',
            );
            $this->information->validateApplication($locked);
            $this->requirements->assertReady($locked);

            $isEndorsed = $decision === EndorsementStatus::Endorsed;
            $endorsement = $locked->endorsements()->create([
                'adviser_user_id' => $actor->id,
                'endorsement_status' => $decision->value,
                'return_reason' => $reason?->value,
                'endorsement_remarks' => filled($remarks) ? trim((string) $remarks) : null,
                'returned_at' => $isEndorsed ? null : now(),
                'endorsed_at' => $isEndorsed ? now() : null,
            ]);

            // Endorsements advance to RES; returns reopen the same initial submission for correction.
            $locked->update([
                'application_status' => $isEndorsed
                    ? ApplicationStatus::AdviserEndorsed->value
                    : ApplicationStatus::ReturnedByAdviser->value,
                'current_stage' => $isEndorsed
                    ? ApplicationStage::ResScreening->value
                    : ApplicationStage::AdviserReview->value,
                'draft_owner_user_id' => null,
                'status_updated_at' => now(),
            ]);

            $action = $isEndorsed
                ? 'application.adviser_endorsed'
                : 'application.returned_by_adviser';
            $this->auditLog->record($actor, $action, $locked, [
                'decision' => $decision->value,
                'return_reason' => $reason?->value,
                'result' => $locked->application_status->value,
            ]);

            $applicant = User::query()->whereKey($locked->applicant_user_id)->first();

            if ($applicant) {
                $applicant->notify(new DashboardUpdateNotification([
                    'title' => $isEndorsed ? 'Application endorsed' : 'Application returned',
                    'message' => $isEndorsed
                        ? 'Your Research Adviser endorsed the application for RES screening.'
                        : 'Your Research Adviser returned the application for required corrections.',
                    'icon' => $isEndorsed ? 'check' : 'refresh',
                    'tone' => $isEndorsed ? 'green' : 'red',
                    'route' => 'applicant.applications.show',
                    'route_parameters' => ['researchApplication' => $locked->id],
                    'academic_term_id' => $locked->academic_term_id,
                ]));
            }

            if ($isEndorsed) {
                // Chunk active RES Leads so one endorsement remains bounded even if administrative staffing grows.
                User::query()
                    ->where('role', UserRole::ResLead->value)
                    ->where('account_status', AccountStatus::Active->value)
                    ->select('id')
                    ->eachById(function (User $resLead) use ($locked): void {
                        $resLead->notify(new DashboardUpdateNotification([
                            'title' => 'Application ready for RES screening',
                            'message' => 'An adviser-endorsed application entered the RES screening queue.',
                            'icon' => 'file-text',
                            'tone' => 'orange',
                            'route' => 'res.applications.show',
                            'route_parameters' => ['researchApplication' => $locked->id],
                            'academic_term_id' => $locked->academic_term_id,
                        ]));
                    }, 100);
            }

            return $endorsement->refresh();
        }, 3);
    }
}
