<?php

namespace App\Services\Applications;

use App\Enums\UserRole;
use App\Models\DeadlineConfiguration;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

/**
 * Resolves and enforces the configured applicant submission period on the server.
 */
class ApplicationSubmissionWindow
{
    /**
     * Return the highest-priority configured application-submission deadline.
     */
    public function configuration(): ?DeadlineConfiguration
    {
        // Match the stable key suffix so local/demo prefixes do not bypass the submission window.
        return DeadlineConfiguration::query()
            ->where('is_active', true)
            ->where('deadline_key', 'like', '%application-submission%')
            ->where(function (Builder $query): void {
                $query
                    ->whereNull('audience_role')
                    ->orWhere('audience_role', UserRole::Applicant->value);
            })
            ->orderByDesc('priority')
            ->orderByDesc('due_at')
            ->first();
    }

    /**
     * Return a shared state for dashboard messaging, buttons, and final validation.
     *
     * @return array<string, mixed>
     */
    public function status(): array
    {
        $deadline = $this->configuration();

        // Absence of approved configuration fails closed while preserving existing drafts.
        if (! $deadline) {
            return [
                'configured' => false,
                'open' => false,
                'state' => 'unconfigured',
                'message' => 'Application submission is unavailable until the RES configures a submission period.',
                'deadline' => null,
            ];
        }

        // A future start date exposes the configured deadline without opening formal submission early.
        if ($deadline->starts_at?->isFuture()) {
            return [
                'configured' => true,
                'open' => false,
                'state' => 'upcoming',
                'message' => 'Application submission opens on '.$deadline->starts_at->format('M j, Y \a\t g:i A').'.',
                'deadline' => $deadline,
            ];
        }

        // An elapsed due date closes submission even if a browser retained an enabled button.
        if ($deadline->due_at->isPast()) {
            return [
                'configured' => true,
                'open' => false,
                'state' => 'closed',
                'message' => 'The application submission period closed on '.$deadline->due_at->format('M j, Y \a\t g:i A').'.',
                'deadline' => $deadline,
            ];
        }

        // The current time is inside the configured inclusive submission window.
        return [
            'configured' => true,
            'open' => true,
            'state' => 'open',
            'message' => 'Application submission is open.',
            'deadline' => $deadline,
        ];
    }

    /**
     * Return dashboard-ready timing information, including upcoming and closed states.
     *
     * @return array<string, mixed>|null
     */
    public function dashboardPayload(?array $status = null): ?array
    {
        // Accept a caller-resolved status so one dashboard request does not repeat the deadline query.
        $status ??= $this->status();
        $deadline = $status['deadline'];

        // Keep the existing dashboard empty state when no application deadline has been configured.
        if (! $deadline) {
            return null;
        }

        // Remaining days never become a misleading negative count in the interface.
        $days = $status['state'] === 'closed'
            ? 0
            : max(0, (int) ceil(now()->diffInDays($deadline->due_at, false)));

        return [
            'title' => $deadline->title,
            'days' => $days,
            'due_at' => $deadline->due_at,
            'due_label' => $deadline->due_at->format('M j, Y (g:i A)'),
            'state' => $status['state'],
            'message' => $status['message'],
        ];
    }

    /**
     * Throw a field-level validation error when formal submission is not currently permitted.
     */
    public function assertOpen(): void
    {
        $status = $this->status();

        // Server-side enforcement remains authoritative regardless of frontend button state.
        if (! $status['open']) {
            throw ValidationException::withMessages([
                'submission_window' => $status['message'],
            ]);
        }
    }
}
