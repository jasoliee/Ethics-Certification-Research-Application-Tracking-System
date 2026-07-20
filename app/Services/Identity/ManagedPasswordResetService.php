<?php

namespace App\Services\Identity;

use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

class ManagedPasswordResetService
{
    public function __construct(private readonly AuditLogService $auditLog) {}

    public function send(User $actor, User $subject): void
    {
        Gate::forUser($actor)->authorize('initiatePasswordReset', $subject);
        $status = Password::broker()->sendResetLink(['email' => $subject->email]);

        if ($status !== Password::RESET_LINK_SENT) {
            throw ValidationException::withMessages([
                'password_reset' => 'A reset link could not be sent yet. Please try again later.',
            ]);
        }

        $this->auditLog->record($actor, 'user.password_reset_requested', $subject);
    }
}
