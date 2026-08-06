<?php

namespace App\Services\Settings;

use App\Enums\DeadlineManualStatus;
use App\Models\DeadlineConfiguration;
use Illuminate\Support\Carbon;

/**
 * Calculates one date-, term-, and toggle-aware state for configured processes.
 */
class DeadlineStateResolver
{
    public function __construct(
        private readonly AcademicTermResolver $terms,
    ) {}

    /**
     * @return array{open: bool, state: string}
     */
    public function status(DeadlineConfiguration $deadline): array
    {
        if (! $deadline->is_active) {
            return ['open' => false, 'state' => 'inactive'];
        }

        $currentTerm = $this->terms->current();

        if ($deadline->academic_term_id !== null) {
            if (! $currentTerm || $currentTerm->id !== $deadline->academic_term_id) {
                return ['open' => false, 'state' => 'outside_term'];
            }
        } elseif ($this->terms->hasConfiguredTerms()) {
            return ['open' => false, 'state' => 'unscoped'];
        }

        if ($deadline->manual_status === DeadlineManualStatus::Closed) {
            return ['open' => false, 'state' => 'manually_closed'];
        }

        // Explicit Open overrides process dates while retaining active-term and configuration boundaries.
        if ($deadline->manual_status === DeadlineManualStatus::Open) {
            return ['open' => true, 'state' => 'manually_open'];
        }

        $now = Carbon::now('Asia/Manila');
        $startsAt = $deadline->starts_at?->copy()->timezone('Asia/Manila');
        $dueAt = $deadline->due_at?->copy()->timezone('Asia/Manila');

        if (! $startsAt || ! $dueAt || $startsAt->greaterThan($dueAt)) {
            return ['open' => false, 'state' => 'invalid_schedule'];
        }

        if ($now->lessThan($startsAt)) {
            return ['open' => false, 'state' => 'upcoming'];
        }

        if ($now->greaterThan($dueAt)) {
            return ['open' => false, 'state' => 'closed'];
        }

        return ['open' => true, 'state' => 'open'];
    }
}
