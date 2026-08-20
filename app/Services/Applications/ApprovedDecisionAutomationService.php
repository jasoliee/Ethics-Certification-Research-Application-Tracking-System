<?php

namespace App\Services\Applications;

use App\Enums\AccountStatus;
use App\Enums\ApplicationStatus;
use App\Enums\ReviewConsensusStatus;
use App\Enums\ReviewDecision;
use App\Enums\UserRole;
use App\Models\ResearchApplication;
use App\Models\User;

class ApprovedDecisionAutomationService
{
    public function __construct(private readonly ApplicationRevisionWorkflowService $revisions) {}

    public function releaseWhenApproved(ResearchApplication $application): void
    {
        $application = $application->refresh();
        if ($application->application_status !== ApplicationStatus::ReviewSubmittedPendingRelease
            || $application->review_consensus_status !== ReviewConsensusStatus::Consensus
            || $application->review_consensus_decision !== ReviewDecision::Approved) {
            return;
        }

        $cycle = (int) $application->review_consensus_cycle;
        $source = $application->reviewerAssignments()
            ->current()
            ->where('review_cycle', $cycle)
            ->with('reviewSubmission')
            ->orderBy('assignment_sequence')
            ->orderBy('id')
            ->get()
            ->pluck('reviewSubmission')
            ->filter()
            ->first();
        $resLead = User::query()
            ->where('role', UserRole::ResLead->value)
            ->where('account_status', AccountStatus::Active->value)
            ->whereNotNull('password_setup_completed_at')
            ->orderBy('id')
            ->first();

        if ($source && $resLead) {
            $this->revisions->releaseDecision($resLead, $application, $source);
        }
    }
}
