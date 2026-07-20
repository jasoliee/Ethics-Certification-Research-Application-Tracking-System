<?php

namespace App\Services\Identity;

use App\Enums\ApplicantType;
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

        // Advisers see only applicant accounts tied through creation or an assigned application.
        return $query
            ->where('role', UserRole::Applicant->value)
            ->where(function (Builder $visible) use ($actor): void {
                $visible
                    ->where('created_by_user_id', $actor->id)
                    ->orWhereHas('researchApplications', fn (Builder $applications) => $applications->where('adviser_user_id', $actor->id));
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
                    ->orWhereLike('institution', '%'.$search.'%')
                    ->orWhereLike('department', '%'.$search.'%');
            });
        }

        if ($role = UserRole::tryFrom((string) ($filters['role'] ?? ''))) {
            $query->where('role', $role->value);
        }

        if ($applicantType = ApplicantType::tryFrom((string) ($filters['applicant_type'] ?? ''))) {
            $query->where('applicant_type', $applicantType->value);
        }

        if (in_array($filters['account_status'] ?? null, ['active', 'inactive'], true)) {
            $query->where('account_status', $filters['account_status']);
        }

        return $query;
    }
}
