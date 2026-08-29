<?php

namespace App\Services\Settings;

use App\Enums\UserRole;
use App\Models\DeadlineConfiguration;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

/**
 * Resolves one configured process window for server-side workflow enforcement.
 */
class DeadlineProcessAvailability
{
    public function __construct(
        private readonly AcademicTermResolver $terms,
        private readonly DeadlineStateResolver $states,
    ) {}

    public function configuration(string $processKey, UserRole $role): ?DeadlineConfiguration
    {
        $query = DeadlineConfiguration::query()
            ->where('is_active', true)
            ->where('deadline_key', 'like', "%{$processKey}")
            ->where(function (Builder $query) use ($role): void {
                $query->whereNull('audience_role')->orWhere('audience_role', $role->value);
            });
        $currentTerm = $this->terms->current();

        if ($currentTerm) {
            $query->where('academic_term_id', $currentTerm->id);
        } elseif ($this->terms->hasConfiguredTerms()) {
            return null;
        } else {
            $query->whereNull('academic_term_id');
        }

        return $query
            ->orderByDesc('priority')
            ->orderByDesc('due_at')
            ->first();
    }

    /**
     * @return array{configured: bool, open: bool, state: string, message: string, deadline: DeadlineConfiguration|null}
     */
    public function status(string $processKey, UserRole $role, string $processLabel): array
    {
        $deadline = $this->configuration($processKey, $role);

        if (! $deadline) {
            return [
                'configured' => false,
                'open' => false,
                'state' => 'unconfigured',
                'message' => "{$processLabel} is unavailable until the REU Lead configures its schedule.",
                'deadline' => null,
            ];
        }

        $state = $this->states->status($deadline);
        $message = match ($state['state']) {
            'manually_open' => "{$processLabel} is manually open by the REU Lead.",
            'manually_closed' => "{$processLabel} is currently closed by the REU Lead.",
            'upcoming' => "{$processLabel} opens on ".$deadline->starts_at?->format('M j, Y \a\t g:i A').'.',
            'closed' => "{$processLabel} closed on ".$deadline->due_at->format('M j, Y \a\t g:i A').'.',
            'open' => "{$processLabel} is open.",
            default => "{$processLabel} is unavailable outside the active semester and academic year.",
        };

        return [
            'configured' => true,
            'open' => $state['open'],
            'state' => $state['state'],
            'message' => $message,
            'deadline' => $deadline,
        ];
    }

    public function assertOpen(string $processKey, UserRole $role, string $processLabel): void
    {
        $status = $this->status($processKey, $role, $processLabel);

        if (! $status['open']) {
            throw ValidationException::withMessages([
                'deadline' => $status['message'],
            ]);
        }
    }
}
