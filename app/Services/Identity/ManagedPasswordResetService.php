<?php

namespace App\Services\Identity;

use App\Models\User;
use App\Notifications\AccountSetupNotification;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Throwable;

class ManagedPasswordResetService
{
    public function __construct(private readonly AuditLogService $auditLog) {}

    public function send(User $actor, User $subject): void
    {
        Gate::forUser($actor)->authorize('initiatePasswordReset', $subject);

        if (! $this->deliver($actor, $subject, true)) {
            throw ValidationException::withMessages([
                'password_reset' => 'A reset link could not be sent yet. Please try again later.',
            ]);
        }
    }

    public function sendForCreatedAccount(User $actor, User $subject): bool
    {
        Gate::forUser($actor)->authorize('initiatePasswordReset', $subject);

        return $this->deliver($actor, $subject, false);
    }

    /** @param iterable<int, User> $subjects @return array{sent: int, failed: int} */
    public function sendMany(User $actor, iterable $subjects): array
    {
        $sent = 0;
        $failed = 0;

        foreach ($subjects as $subject) {
            Gate::forUser($actor)->authorize('initiatePasswordReset', $subject);
            $this->deliver($actor, $subject, true) ? $sent++ : $failed++;
        }

        return compact('sent', 'failed');
    }

    private function deliver(User $actor, User $subject, bool $resend): bool
    {
        $initialSetup = ! $subject->password_setup_completed_at;

        try {
            // Broker creation invalidates the previous token before the new single-use link is delivered.
            $token = Password::broker()->createToken($subject);
            $this->auditLog->record($actor, $resend ? 'user.setup_link_resent' : 'user.setup_link_generated', $subject, [
                'purpose' => $initialSetup ? 'initial_setup' : 'password_reset',
                'result' => 'generated',
            ]);
            $subject->notify(new AccountSetupNotification($token, $initialSetup));
            $subject->forceFill([
                'setup_email_status' => 'sent',
                'setup_email_sent_at' => now(),
                'setup_email_failed_at' => null,
            ])->save();

            $this->auditLog->record($actor, 'user.setup_email_sent', $subject, [
                'purpose' => $initialSetup ? 'initial_setup' : 'password_reset',
                'result' => 'sent',
            ]);

            return true;
        } catch (Throwable) {
            try {
                $subject->forceFill([
                    'setup_email_status' => 'failed',
                    'setup_email_failed_at' => now(),
                ])->save();

                $this->auditLog->record($actor, 'user.setup_email_failed', $subject, [
                    'purpose' => $initialSetup ? 'initial_setup' : 'password_reset',
                    'result' => 'failed',
                ]);
            } catch (Throwable) {
                // The account remains pending even if persistence is unavailable during failure handling.
            }

            return false;
        }
    }
}
