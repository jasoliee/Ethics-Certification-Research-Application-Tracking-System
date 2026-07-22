<?php

namespace App\Services\Identity;

use App\Enums\AccountStatus;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ManagedUserMassActionService
{
    public function __construct(
        private readonly UserManagementQueryService $queries,
        private readonly UserAccountService $accounts,
        private readonly ManagedPasswordResetService $passwordResets,
        private readonly AuditLogService $auditLog,
    ) {}

    /** @param array<int, int> $userIds @return array{affected: int, sent: int, failed: int} */
    public function execute(User $actor, string $action, array $userIds): array
    {
        $query = $this->queries->visibleTo($actor);

        if ($action === 'resend_all_pending') {
            $pending = $query->where('account_status', AccountStatus::PendingSetup->value);
            $affected = (clone $pending)->count();
            // Lazy batches keep the all-pending action bounded without silently skipping later accounts.
            $delivery = $this->passwordResets->sendMany($actor, $pending->lazyById(50));

            return ['affected' => $affected, ...$delivery];
        } else {
            $subjects = $query->whereKey($userIds)->get();

            if ($subjects->count() !== count(array_unique($userIds))) {
                throw new AuthorizationException('One or more selected accounts are not available to manage.');
            }
        }

        if ($action === 'resend_setup') {
            $delivery = $this->passwordResets->sendMany($actor, $subjects);

            return ['affected' => $subjects->count(), ...$delivery];
        }

        DB::transaction(function () use ($actor, $action, $subjects): void {
            foreach ($subjects as $subject) {
                if ($action === 'deactivate') {
                    $this->accounts->changeStatus($actor, $subject, AccountStatus::Inactive);

                    continue;
                }

                Gate::forUser($actor)->authorize('delete', $subject);
                $this->auditLog->record($actor, 'user.archived', $subject, ['result' => 'archived']);
                $subject->delete();
            }
        });

        return ['affected' => $subjects->count(), 'sent' => 0, 'failed' => 0];
    }
}
