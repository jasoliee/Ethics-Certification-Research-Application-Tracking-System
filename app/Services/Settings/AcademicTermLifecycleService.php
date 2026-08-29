<?php

namespace App\Services\Settings;

use App\Enums\AcademicTermStatus;
use App\Enums\UserRole;
use App\Models\AcademicTerm;
use App\Models\DeadlineConfiguration;
use App\Models\TimelineCalendarEvent;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AcademicTermLifecycleService
{
    public function __construct(private readonly AuditLogService $auditLog) {}

    public function pause(User $actor, AcademicTerm $term): AcademicTerm
    {
        return $this->transition($actor, $term, AcademicTermStatus::Paused);
    }

    public function end(User $actor, AcademicTerm $term): AcademicTerm
    {
        return $this->transition($actor, $term, AcademicTermStatus::Ended);
    }

    public function reactivate(User $actor, AcademicTerm $term): AcademicTerm
    {
        return $this->transition($actor, $term, AcademicTermStatus::Active);
    }

    private function transition(User $actor, AcademicTerm $term, AcademicTermStatus $target): AcademicTerm
    {
        abort_unless($actor->role === UserRole::ResLead, 403);

        return DB::transaction(function () use ($actor, $term, $target): AcademicTerm {
            $locked = AcademicTerm::query()->whereKey($term->id)->lockForUpdate()->firstOrFail();
            $current = $locked->status;

            if ($target === AcademicTermStatus::Paused && ($current !== AcademicTermStatus::Active || ! $locked->is_active)) {
                throw ValidationException::withMessages(['academic_term' => 'Only an active academic term may be paused.']);
            }
            if ($target === AcademicTermStatus::Ended && ! in_array($current, [AcademicTermStatus::Active, AcademicTermStatus::Paused], true)) {
                throw ValidationException::withMessages(['academic_term' => 'This academic term has already ended.']);
            }
            if ($target === AcademicTermStatus::Active) {
                if (! in_array($current, [AcademicTermStatus::Paused, AcademicTermStatus::Ended], true)) {
                    throw ValidationException::withMessages(['academic_term' => 'Only a paused or ended academic term may be reactivated.']);
                }

                $anotherConfiguredTermExists = AcademicTerm::query()
                    ->whereKeyNot($locked->id)
                    ->where('is_active', true)
                    ->whereIn('status', [AcademicTermStatus::Active->value, AcademicTermStatus::Paused->value])
                    ->exists();
                if ($anotherConfiguredTermExists) {
                    throw ValidationException::withMessages(['academic_term' => 'End the currently configured term before reactivating another term.']);
                }
            }

            $isEnded = $target === AcademicTermStatus::Ended;
            $locked->update([
                'status' => $target,
                'is_active' => ! $isEnded,
            ]);

            if ($target !== AcademicTermStatus::Paused) {
                DeadlineConfiguration::query()
                    ->where('academic_term_id', $locked->id)
                    ->update(['is_active' => ! $isEnded]);
                TimelineCalendarEvent::query()
                    ->where('academic_term_id', $locked->id)
                    ->update(['is_active' => ! $isEnded]);
            }

            $this->auditLog->record($actor, 'settings.academic_term_'.$target->value, $locked, [
                'academic_term_id' => $locked->id,
                'from_status' => $current?->value,
                'to_status' => $target->value,
                'result' => 'updated',
            ], $locked);

            return $locked->refresh();
        }, 3);
    }
}
