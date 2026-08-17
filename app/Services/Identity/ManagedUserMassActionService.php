<?php

namespace App\Services\Identity;

use App\Enums\AccountStatus;
use App\Enums\ReviewerAssignmentStatus;
use App\Enums\UserRole;
use App\Models\ReviewerAssignment;
use App\Models\User;
use App\Notifications\DashboardUpdateNotification;
use App\Services\AuditLogService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ManagedUserMassActionService
{
    public function __construct(
        private readonly UserManagementQueryService $queries,
        private readonly UserAccountService $accounts,
        private readonly ManagedPasswordResetService $passwordResets,
        private readonly AuditLogService $auditLog,
    ) {}

    /**
     * @param  array<int, int>  $userIds
     * @param  array{confirm_active_assignments?: bool}  $options
     * @return array{affected: int, sent: int, failed: int, ignored?: int, active_assignments?: int}
     */
    public function execute(User $actor, string $action, array $userIds, array $options = []): array
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

        if (in_array($action, ['show_reviewer', 'hide_reviewer'], true)) {
            return $this->changeReviewerVisibility(
                $actor,
                $subjects,
                $action === 'show_reviewer',
                (bool) ($options['confirm_active_assignments'] ?? false),
            );
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

    /**
     * Grant or revoke only the supplementary Reviewer surface. Existing assignment
     * rows and review history are deliberately never changed by this account action.
     *
     * @param  Collection<int, User>  $subjects
     * @return array{affected: int, sent: int, failed: int, ignored: int, active_assignments: int}
     */
    private function changeReviewerVisibility(
        User $actor,
        Collection $subjects,
        bool $enabled,
        bool $confirmedActiveAssignments,
    ): array {
        return DB::transaction(function () use ($actor, $subjects, $enabled, $confirmedActiveAssignments): array {
            /** @var Collection<int, User> $locked */
            $locked = User::query()
                ->whereKey($subjects->modelKeys())
                ->lockForUpdate()
                ->get();
            $eligible = $locked->filter(fn (User $subject): bool => $subject->role === UserRole::Adviser
                && $subject->account_status === AccountStatus::Active->value);
            $changed = $eligible->filter(fn (User $subject): bool => (bool) $subject->reviewer_enabled !== $enabled);
            $activeAssignments = ! $enabled && $changed->isNotEmpty()
                ? $this->activeAssignmentCount($changed->modelKeys())
                : 0;

            if ($activeAssignments > 0 && ! $confirmedActiveAssignments) {
                throw ValidationException::withMessages([
                    'action' => "{$activeAssignments} active review ".str('assignment')->plural($activeAssignments).' will be preserved. Confirm Hide Reviewer to continue.',
                ]);
            }

            foreach ($changed as $subject) {
                Gate::forUser($actor)->authorize('update', $subject);
                $subjectActiveAssignments = ! $enabled
                    ? $this->activeAssignmentCount([$subject->id])
                    : 0;
                $subject->forceFill(['reviewer_enabled' => $enabled])->save();

                $this->auditLog->record(
                    $actor,
                    $enabled ? 'user.reviewer_access_enabled' : 'user.reviewer_access_disabled',
                    $subject,
                    [
                        'from' => ! $enabled,
                        'to' => $enabled,
                        'active_assignments_preserved' => $subjectActiveAssignments,
                        'result' => 'updated',
                    ],
                );

                $subject->notify(new DashboardUpdateNotification([
                    'title' => $enabled ? 'Reviewer access shown' : 'Reviewer access hidden',
                    'message' => $enabled
                        ? 'The RES Lead enabled Reviewer features for your Adviser account.'
                        : 'The RES Lead disabled new Reviewer access. Existing review records and assignments were preserved.',
                    'icon' => 'file-search',
                    'tone' => $enabled ? 'green' : 'orange',
                    'route' => 'dashboard',
                ]));
            }

            return [
                'affected' => $changed->count(),
                'ignored' => $locked->count() - $changed->count(),
                'active_assignments' => $activeAssignments,
                'sent' => 0,
                'failed' => 0,
            ];
        }, 3);
    }

    /** @param array<int, int> $reviewerIds */
    private function activeAssignmentCount(array $reviewerIds): int
    {
        if ($reviewerIds === []) {
            return 0;
        }

        return ReviewerAssignment::query()
            ->whereIn('reviewer_user_id', $reviewerIds)
            ->whereNull('superseded_at')
            ->where('assignment_status', '!=', ReviewerAssignmentStatus::Superseded->value)
            ->count();
    }
}
