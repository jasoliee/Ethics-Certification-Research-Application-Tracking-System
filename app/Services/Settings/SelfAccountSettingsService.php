<?php

namespace App\Services\Settings;

use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Applies audited self-service credential changes without exposing secrets.
 */
class SelfAccountSettingsService
{
    public function __construct(private readonly AuditLogService $auditLog) {}

    public function updateUsername(User $actor, string $username): User
    {
        return DB::transaction(function () use ($actor, $username): User {
            $previousUsername = $actor->username;
            $actor->forceFill(['username' => $username])->save();

            $this->auditLog->record($actor, 'settings.username_updated', $actor, [
                'previous_username' => $previousUsername,
                'new_username' => $username,
                'result' => 'updated',
            ]);

            return $actor->refresh();
        });
    }

    public function updatePassword(User $actor, string $password): void
    {
        DB::transaction(function () use ($actor, $password): void {
            $actor->forceFill([
                'password' => Hash::make($password),
                'password_changed_at' => now(),
                'remember_token' => Str::random(60),
            ])->save();

            $this->auditLog->record($actor, 'settings.password_updated', $actor, [
                'result' => 'updated',
            ]);
        });
    }
}
