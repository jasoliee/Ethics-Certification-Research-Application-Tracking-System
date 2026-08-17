<?php

namespace App\Services\Identity;

use App\Enums\AccountStatus;
use App\Enums\UserRole;
use App\Models\ReviewerIdentityReconciliation;
use App\Models\User;
use App\Notifications\DashboardUpdateNotification;
use App\Services\AuditLogService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Resolve possible legacy Reviewer/Adviser duplicate identities without deleting history. */
class ReviewerIdentityReconciliationService
{
    public function __construct(private readonly AuditLogService $auditLog) {}

    public function keepSeparate(
        User $actor,
        ReviewerIdentityReconciliation $candidate,
        ?string $notes = null,
    ): ReviewerIdentityReconciliation {
        $this->authorizeActor($actor);

        return DB::transaction(function () use ($actor, $candidate, $notes): ReviewerIdentityReconciliation {
            $locked = ReviewerIdentityReconciliation::query()->lockForUpdate()->findOrFail($candidate->id);
            $this->assertPending($locked);

            $locked->forceFill([
                'status' => ReviewerIdentityReconciliation::STATUS_KEPT_SEPARATE,
                'resolution_notes' => filled($notes) ? trim((string) $notes) : null,
                'resolved_by_user_id' => $actor->id,
                'resolved_at' => now(),
            ])->save();

            $this->auditLog->record($actor, 'user.reviewer_identity_kept_separate', $locked, [
                'source_user_id' => $locked->source_user_id,
                'target_adviser_user_id' => $locked->target_adviser_user_id,
                'result' => 'resolved',
            ]);

            return $locked;
        }, 3);
    }

    public function merge(
        User $actor,
        ReviewerIdentityReconciliation $candidate,
        ?string $notes = null,
    ): ReviewerIdentityReconciliation {
        $this->authorizeActor($actor);

        return DB::transaction(function () use ($actor, $candidate, $notes): ReviewerIdentityReconciliation {
            $locked = ReviewerIdentityReconciliation::query()->lockForUpdate()->findOrFail($candidate->id);
            $this->assertPending($locked);

            $users = User::withTrashed()
                ->whereKey([$locked->source_user_id, $locked->target_adviser_user_id])
                ->lockForUpdate()
                ->get()
                ->keyBy('id');
            $source = $users->get($locked->source_user_id);
            $target = $users->get($locked->target_adviser_user_id);

            if (! $source || ! $target || $source->is($target)) {
                throw ValidationException::withMessages([
                    'reconciliation' => 'The reconciliation identities are no longer valid.',
                ]);
            }

            if ($source->role !== UserRole::Adviser || $target->role !== UserRole::Adviser
                || $target->trashed() || $target->account_status !== AccountStatus::Active->value) {
                throw ValidationException::withMessages([
                    'reconciliation' => 'Both identities must be valid Adviser records and the destination must be active.',
                ]);
            }

            // A converted source that has since performed Adviser-owned work is no longer a
            // reviewer-only duplicate and needs case-specific administrative handling.
            if ($source->advisedApplications()->exists()
                || $source->endorsements()->exists()
                || $source->createdUsers()->exists()) {
                throw ValidationException::withMessages([
                    'reconciliation' => 'This source identity now owns Adviser records and cannot be merged automatically.',
                ]);
            }

            $sourceAssignments = $source->reviewerAssignments()
                ->select(['id', 'research_application_id', 'review_type'])
                ->lockForUpdate()
                ->get();

            foreach ($sourceAssignments->groupBy('research_application_id') as $applicationId => $assignments) {
                $reviewTypes = $assignments->pluck('review_type')->unique()->all();

                if ($target->reviewerAssignments()
                    ->where('research_application_id', $applicationId)
                    ->whereIn('review_type', $reviewTypes)
                    ->exists()) {
                    throw ValidationException::withMessages([
                        'reconciliation' => 'The destination already has review history for one of the same application cycles. Reassign that case before merging.',
                    ]);
                }
            }

            $movedAssignments = $sourceAssignments->count();
            $source->reviewerAssignments()->update(['reviewer_user_id' => $target->id]);

            // Carry forward application-specific disqualifiers. If both identities already
            // carry the same conflict, leave the source row as preserved duplicate provenance.
            foreach ($source->reviewerConflicts()->lockForUpdate()->get() as $conflict) {
                $targetHasConflict = $target->reviewerConflicts()
                    ->where('research_application_id', $conflict->research_application_id)
                    ->exists();

                if (! $targetHasConflict) {
                    $conflict->forceFill(['reviewer_user_id' => $target->id])->save();
                }
            }

            $classifications = collect([
                ...$target->reviewerClassificationLabels(),
                ...$source->reviewerClassificationLabels(),
            ])->unique()->values()->all();
            $target->forceFill([
                'reviewer_enabled' => true,
                'reviewer_classifications' => $classifications ?: null,
                'reviewer_classification' => $classifications[0] ?? null,
                'reviewer_capacity' => max(
                    (int) ($target->reviewer_capacity ?? 0),
                    (int) ($source->reviewer_capacity ?? 0),
                    1,
                ),
            ])->save();

            // Preserve the source User row and attribution while permanently preventing a
            // second login. Session rows are ephemeral authentication state, not history.
            $source->forceFill([
                'account_status' => AccountStatus::Inactive->value,
                'reviewer_enabled' => false,
                'remember_token' => Str::random(60),
            ])->save();
            DB::table('sessions')->where('user_id', $source->id)->delete();

            $locked->forceFill([
                'status' => ReviewerIdentityReconciliation::STATUS_MERGED,
                'resolution_notes' => filled($notes) ? trim((string) $notes) : null,
                'resolved_by_user_id' => $actor->id,
                'resolved_at' => now(),
            ])->save();

            ReviewerIdentityReconciliation::query()
                ->where('source_user_id', $source->id)
                ->whereKeyNot($locked->id)
                ->where('status', ReviewerIdentityReconciliation::STATUS_PENDING)
                ->update([
                    'status' => ReviewerIdentityReconciliation::STATUS_KEPT_SEPARATE,
                    'resolution_notes' => 'Resolved automatically because this source identity was merged into another Adviser record.',
                    'resolved_by_user_id' => $actor->id,
                    'resolved_at' => now(),
                    'updated_at' => now(),
                ]);

            $this->auditLog->record($actor, 'user.reviewer_identity_merged', $locked, [
                'source_user_id' => $source->id,
                'target_adviser_user_id' => $target->id,
                'assignments_repointed' => $movedAssignments,
                'source_preserved_inactive' => true,
                'result' => 'merged',
            ]);

            $target->notify(new DashboardUpdateNotification([
                'title' => 'Reviewer history reconciled',
                'message' => 'RES linked preserved Reviewer history to your Adviser account.',
                'icon' => 'refresh',
                'tone' => 'green',
                'route' => 'reviewer.dashboard',
            ]));

            return $locked;
        }, 3);
    }

    private function authorizeActor(User $actor): void
    {
        if ($actor->role !== UserRole::ResLead) {
            throw new AuthorizationException('Only the RES Lead may reconcile Reviewer identities.');
        }
    }

    private function assertPending(ReviewerIdentityReconciliation $candidate): void
    {
        if ($candidate->status !== ReviewerIdentityReconciliation::STATUS_PENDING) {
            throw ValidationException::withMessages([
                'reconciliation' => 'This reconciliation has already been resolved.',
            ]);
        }
    }
}
