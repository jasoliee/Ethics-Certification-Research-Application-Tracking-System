<?php

namespace App\Policies;

use App\Enums\ApplicationStatus;
use App\Enums\UserRole;
use App\Models\User;

class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return in_array($actor->role, [UserRole::ResLead, UserRole::Adviser], true);
    }

    public function create(User $actor): bool
    {
        return $this->viewAny($actor);
    }

    public function view(User $actor, User $subject): bool
    {
        return $this->canManageProfile($actor, $subject);
    }

    public function update(User $actor, User $subject): bool
    {
        return $this->canManageProfile($actor, $subject);
    }

    public function changeStatus(User $actor, User $subject): bool
    {
        return $actor->role === UserRole::ResLead
            && $subject->role !== UserRole::ResLead
            && ! $actor->is($subject);
    }

    public function initiatePasswordReset(User $actor, User $subject): bool
    {
        return ! $actor->is($subject) && $this->canManageProfile($actor, $subject);
    }

    public function delete(User $actor, User $subject): bool
    {
        return $actor->role === UserRole::ResLead
            && $subject->role !== UserRole::ResLead
            && ! $actor->is($subject);
    }

    public function import(User $actor): bool
    {
        return $this->create($actor);
    }

    /**
     * Reserve all archived-account restoration actions for the RES Lead.
     */
    public function restoreArchivedAccounts(User $actor): bool
    {
        return $actor->role === UserRole::ResLead;
    }

    public function completeOnboarding(User $actor, User $subject): bool
    {
        return $actor->is($subject);
    }

    public function updateOwnProfile(User $actor, User $subject): bool
    {
        return $actor->is($subject)
            && $actor->account_status === 'active'
            && ! $actor->trashed();
    }

    public function updateOwnSecurity(User $actor, User $subject): bool
    {
        return $this->updateOwnProfile($actor, $subject);
    }

    public function manageCertificateSignatory(User $actor, User $subject): bool
    {
        return $actor->is($subject)
            && $actor->role === UserRole::ResLead
            && $actor->account_status === 'active'
            && ! $actor->trashed();
    }

    public function viewAuditLog(User $actor): bool
    {
        return $actor->role === UserRole::ResLead;
    }

    public function manageProfileOptions(User $actor): bool
    {
        return $actor->role === UserRole::ResLead;
    }

    private function canManageProfile(User $actor, User $subject): bool
    {
        if ($actor->role === UserRole::ResLead) {
            return $subject->role !== UserRole::ResLead;
        }

        if ($actor->role !== UserRole::Adviser || $subject->role !== UserRole::Applicant) {
            return false;
        }

        // Draft Adviser selection is private Applicant work and cannot grant directory access.
        // An Adviser may manage an account only after creating it or receiving a formal submission.
        return $subject->created_by_user_id === $actor->id
            || $subject->researchApplications()
                ->where('adviser_user_id', $actor->id)
                ->whereNotNull('submitted_at')
                ->whereNotIn('application_status', [
                    ApplicationStatus::Draft->value,
                    ApplicationStatus::Incomplete->value,
                ])
                ->exists();
    }
}
