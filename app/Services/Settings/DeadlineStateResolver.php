<?php

namespace App\Services\Settings;

use App\Enums\DeadlineManualStatus;
use App\Models\DeadlineConfiguration;

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

        // Manual Open overrides process dates while retaining active-term and configuration boundaries.
        if ($deadline->manual_status === DeadlineManualStatus::Open) {
            return ['open' => true, 'state' => 'manually_open'];
        }

        if ($deadline->starts_at?->isFuture()) {
            return ['open' => false, 'state' => 'upcoming'];
        }

        if ($deadline->due_at->isPast()) {
            return ['open' => false, 'state' => 'closed'];
        }

        return ['open' => true, 'state' => 'open'];
    }
}
