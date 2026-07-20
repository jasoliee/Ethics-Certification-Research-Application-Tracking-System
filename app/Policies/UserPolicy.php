<?php

namespace App\Policies;

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
        return $this->changeStatus($actor, $subject);
    }

    public function import(User $actor): bool
    {
        return $this->create($actor);
    }

    private function canManageProfile(User $actor, User $subject): bool
    {
        if ($actor->role === UserRole::ResLead) {
            return $subject->role !== UserRole::ResLead;
        }

        if ($actor->role !== UserRole::Adviser || $subject->role !== UserRole::Applicant) {
            return false;
        }

        // Adviser access is limited to accounts they created or applicants assigned to them in the workflow.
        return $subject->created_by_user_id === $actor->id
            || $subject->researchApplications()->where('adviser_user_id', $actor->id)->exists();
    }
}
