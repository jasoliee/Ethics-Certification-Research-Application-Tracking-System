<?php

namespace App\Services\Identity;

use App\Enums\ApplicantType;
use App\Enums\ApplicationStatus;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class UserManagementQueryService
{
    /** @return Builder<User> */
    public function visibleTo(User $actor): Builder
    {
        $query = User::query();

        if ($actor->role === UserRole::ResLead) {
            return $query->where('role', '!=', UserRole::ResLead->value);
        }

        // Advisers see only accounts they created or Applicants who crossed the formal
        // submission boundary with them. Merely selecting an Adviser in a private draft
        // never grants account-directory access.
        return $query
            ->where('role', UserRole::Applicant->value)
            ->where(function (Builder $visible) use ($actor): void {
                $visible
                    ->where('created_by_user_id', $actor->id)
                    ->orWhereHas('researchApplications', fn (Builder $applications) => $applications
                        ->where('adviser_user_id', $actor->id)
                        ->whereNotNull('submitted_at')
                        ->whereNotIn('application_status', [
                            ApplicationStatus::Draft->value,
                            ApplicationStatus::Incomplete->value,
                        ]));
            });
    }

    /** @param array<string, mixed> $filters @return Builder<User> */
    public function applyFilters(Builder $query, array $filters): Builder
    {
        $search = trim((string) ($filters['search'] ?? ''));

        if ($search !== '') {
            $query->where(function (Builder $matches) use ($search): void {
                $matches
                    ->whereLike('name', '%'.$search.'%')
                    ->orWhereLike('email', '%'.$search.'%')
                    ->orWhereLike('institutional_identifier', '%'.$search.'%')
                    ->orWhereLike('institution', '%'.$search.'%');
            });
        }

        if ($role = UserRole::tryFrom((string) ($filters['role'] ?? ''))) {
            if ($role === UserRole::Reviewer) {
                // "Reviewer" remains a useful management filter, but it now means
                // active Advisers whose supplementary Reviewer surface is enabled.
                $query->where('role', UserRole::Adviser->value)
                    ->where('account_status', 'active')
                    ->where('reviewer_enabled', true);
            } else {
                $query->where('role', $role->value);
            }
        }

        if ($applicantType = ApplicantType::tryFrom((string) ($filters['applicant_type'] ?? ''))) {
            $query->where('applicant_type', $applicantType->value);
        }

        if (in_array($filters['account_status'] ?? null, ['pending_setup', 'active', 'inactive'], true)) {
            $query->where('account_status', $filters['account_status']);
        }

        return $query;
    }
}
