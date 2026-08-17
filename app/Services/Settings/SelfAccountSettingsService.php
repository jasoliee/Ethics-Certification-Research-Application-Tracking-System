<?php

namespace App\Services\Settings;

use App\Enums\ApplicantType;
use App\Enums\UserRole;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Applies audited self-service credential changes without exposing secrets.
 */
class SelfAccountSettingsService
{
    public function __construct(private readonly AuditLogService $auditLog) {}

    /** @param array<string, mixed> $attributes */
    public function updateProfile(User $actor, array $attributes): User
    {
        return DB::transaction(function () use ($actor, $attributes): User {
            $allowed = [
                'first_name',
                'middle_name',
                'last_name',
                'suffix',
                'phone_number',
                'institution',
                'department',
            ];

            if ($actor->role === UserRole::Applicant) {
                $allowed = [...$allowed, 'program', 'year_level'];

                if ($actor->applicant_type === ApplicantType::Faculty) {
                    $allowed[] = 'position_title';
                }
            } elseif ($actor->role === UserRole::Adviser) {
                $allowed = [...$allowed, 'position_title', 'expected_endorsement_count'];
            } elseif ($actor->role === UserRole::ResLead) {
                $allowed[] = 'position_title';
            }

            $updates = collect($attributes)->only($allowed)->all();
            $updates['name'] = User::formatName(
                (string) ($updates['first_name'] ?? $actor->first_name),
                array_key_exists('middle_name', $updates) ? $updates['middle_name'] : $actor->middle_name,
                (string) ($updates['last_name'] ?? $actor->last_name),
                array_key_exists('suffix', $updates) ? $updates['suffix'] : $actor->suffix,
            );
            $changedFields = array_keys(array_filter(
                $updates,
                fn (mixed $value, string $field): bool => $actor->getAttribute($field) !== $value,
                ARRAY_FILTER_USE_BOTH,
            ));

            $actor->forceFill($updates)->save();
            $this->auditLog->record($actor, 'settings.profile_updated', $actor, [
                'changed_fields' => $changedFields,
                'result' => $changedFields === [] ? 'unchanged' : 'updated',
            ]);

            return $actor->refresh();
        });
    }

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

    public function updateEmail(User $actor, string $email, ?string $currentSessionId = null): User
    {
        return DB::transaction(function () use ($actor, $email, $currentSessionId): User {
            $previousEmail = $actor->email;
            $actor->forceFill([
                'email' => $email,
                'email_verified_at' => null,
                'remember_token' => Str::random(60),
            ])->save();
            $revoked = $this->revokeOtherSessions($actor, $currentSessionId);

            $this->auditLog->record($actor, 'settings.email_updated', $actor, [
                'previous_email' => $previousEmail,
                'new_email' => $email,
                'revoked_other_login_count' => $revoked,
                'result' => 'updated',
            ]);

            return $actor->refresh();
        });
    }

    public function updatePassword(User $actor, string $password, ?string $currentSessionId = null): void
    {
        DB::transaction(function () use ($actor, $password, $currentSessionId): void {
            $actor->forceFill([
                'password' => Hash::make($password),
                'password_changed_at' => now(),
                'remember_token' => Str::random(60),
            ])->save();
            $revoked = $this->revokeOtherSessions($actor, $currentSessionId);

            $this->auditLog->record($actor, 'settings.password_updated', $actor, [
                'revoked_other_login_count' => $revoked,
                'result' => 'updated',
            ]);
        });
    }

    private function revokeOtherSessions(User $actor, ?string $currentSessionId): int
    {
        if (config('session.driver') !== 'database'
            || blank($currentSessionId)
            || ! Schema::hasTable((string) config('session.table', 'sessions'))) {
            return 0;
        }

        return DB::table((string) config('session.table', 'sessions'))
            ->where('user_id', $actor->id)
            ->where('id', '!=', $currentSessionId)
            ->delete();
    }
}
