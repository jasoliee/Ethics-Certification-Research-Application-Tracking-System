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
            && ($researchApplication->submitted_at === null
                || $researchApplication->application_status === ApplicationStatus::ReturnedByAdviser);
    }

    /**
     * Apply the same editable-state boundary to private requirement uploads and replacements.
     */
    public function upload(User $user, ResearchApplication $researchApplication): bool
    {
        return $this->update($user, $researchApplication);
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
        return $user->role === UserRole::Applicant
            && $researchApplication->applicant_user_id === $user->id
            && in_array($researchApplication->application_status, [
                ApplicationStatus::Draft,
                ApplicationStatus::Incomplete,
                ApplicationStatus::ReturnedByAdviser,
            ], true)
            && ($researchApplication->submitted_at === null
                || $researchApplication->application_status === ApplicationStatus::ReturnedByAdviser);
    }
}
