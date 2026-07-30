<?php

namespace App\Services\Applications;

use App\Enums\UserRole;
use App\Models\DeadlineConfiguration;
use App\Services\Settings\DeadlineProcessAvailability;
use Illuminate\Validation\ValidationException;

/**
 * Resolves and enforces the configured applicant submission period on the server.
 */
class ApplicationSubmissionWindow
{
    public function __construct(
        private readonly DeadlineProcessAvailability $availability,
    ) {}

    /**
     * Return the highest-priority configured application-submission deadline.
     */
    public function configuration(): ?DeadlineConfiguration
    {
        return $this->availability->configuration('application-submission', UserRole::Applicant);
    }

    /**
     * Return a shared state for dashboard messaging, buttons, and final validation.
     *
     * @return array<string, mixed>
     */
    public function status(): array
    {
        return $this->availability->status(
            'application-submission',
            UserRole::Applicant,
            'Application submission',
        );
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
