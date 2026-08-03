<?php

namespace App\Policies;

use App\Enums\ApplicationStatus;
use App\Enums\UserRole;
use App\Models\ResearchApplication;
use App\Models\User;

/**
 * Enforces Applicant ownership and formally submitted role visibility.
 */
class ResearchApplicationPolicy
{
    /**
     * Allow applicants to open the single draft-creation workflow.
     */
    public function create(User $user): bool
    {
        return $user->role === UserRole::Applicant;
    }

    /**
     * Scope record visibility by ownership, formal Adviser submission, assignment, or RES authority.
     */
    public function view(User $user, ResearchApplication $researchApplication): bool
    {
        return match ($user->role) {
            UserRole::Applicant => $researchApplication->applicant_user_id === $user->id,
            UserRole::Adviser => $researchApplication->adviser_user_id === $user->id
                && $researchApplication->isFormallySubmitted(),
            UserRole::Reviewer => $researchApplication->reviewerAssignments()
                ->where('reviewer_user_id', $user->id)
                ->exists(),
            UserRole::ResLead => true,
        };
    }

    /**
     * Permit only the owner to edit draft, incomplete, or formally returned information.
     */
    public function update(User $user, ResearchApplication $researchApplication): bool
    {
        return $user->role === UserRole::Applicant
            && $researchApplication->applicant_user_id === $user->id
            && in_array($researchApplication->application_status, [
                ApplicationStatus::Draft,
                ApplicationStatus::Incomplete,
                ApplicationStatus::ReturnedByAdviser,
            ], true)
            && ($researchApplication->application_status === ApplicationStatus::ReturnedByAdviser
                || $researchApplication->draft_owner_user_id === $user->id);
    }

    /**
     * Apply the same editable-state boundary to private requirement uploads and replacements.
     */
    public function upload(User $user, ResearchApplication $researchApplication): bool
    {
        return $this->update($user, $researchApplication)
            && $researchApplication->application_status !== ApplicationStatus::ReturnedByAdviser;
    }

    /**
     * Permit only the owner to archive an unsubmitted Draft or Incomplete application.
     */
    public function discard(User $user, ResearchApplication $researchApplication): bool
    {
        return $user->role === UserRole::Applicant
            && $researchApplication->applicant_user_id === $user->id
            && in_array($researchApplication->application_status, [
                ApplicationStatus::Draft,
                ApplicationStatus::Incomplete,
            ], true)
            && $researchApplication->submitted_at === null;
    }

    /**
     * Restrict protected document access to users already authorized for the parent application.
     */
    public function viewDocument(User $user, ResearchApplication $researchApplication): bool
    {
        return $this->view($user, $researchApplication);
    }

    /**
     * Permit an eligible owner to cross the submission boundary only from an editable state.
     */
    public function submit(User $user, ResearchApplication $researchApplication): bool
    {
        return $this->update($user, $researchApplication)
            && $researchApplication->application_status !== ApplicationStatus::ReturnedByAdviser;
    }

    /**
     * Restrict initial Adviser decisions to the currently assigned, formally submitted record.
     */
    public function decideAsAdviser(User $user, ResearchApplication $researchApplication): bool
    {
        return $user->role === UserRole::Adviser
            && $researchApplication->adviser_user_id === $user->id
            && $researchApplication->application_status === ApplicationStatus::SubmittedToAdviser
            && $researchApplication->isFormallySubmitted()
            && (int) $researchApplication->current_revision_cycle === 1;
    }

    /**
     * Restrict initial administrative classification to RES Leads and eligible screening states.
     */
    public function classify(User $user, ResearchApplication $researchApplication): bool
    {
        return $user->role === UserRole::ResLead
            && $researchApplication->isFormallySubmitted()
            && in_array($researchApplication->application_status, [
                ApplicationStatus::AdviserEndorsed,
                ApplicationStatus::UnderResScreening,
            ], true);
    }

    /**
     * Allow RES Leads to correct a persisted screening while the service protects started review work.
     */
    public function updateScreening(User $user, ResearchApplication $researchApplication): bool
    {
        return $user->role === UserRole::ResLead
            && $researchApplication->isFormallySubmitted()
            && in_array($researchApplication->application_status, [
                ApplicationStatus::UnderResScreening,
                ApplicationStatus::AwaitingReviewerAssignment,
                ApplicationStatus::UnderExpeditedReview,
                ApplicationStatus::UnderFullBoardReview,
                ApplicationStatus::Exempted,
            ], true)
            && $researchApplication->screening()->exists();
    }

    /**
     * Restrict initial reviewer assignment to a classified application awaiting assignment.
     */
    public function assignReviewers(User $user, ResearchApplication $researchApplication): bool
    {
        return $user->role === UserRole::ResLead
            && $researchApplication->application_status === ApplicationStatus::AwaitingReviewerAssignment;
    }
}
