<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class AuditLogService
{
    /** @param array<string, mixed> $metadata */
    public function record(?User $actor, string $action, ?Model $subject = null, array $metadata = []): AuditLog
    {
        $request = app()->bound('request') ? request() : null;

        return AuditLog::create([
            'actor_user_id' => $actor?->id,
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'metadata' => $metadata ?: null,
            'ip_address' => $request instanceof Request ? $request->ip() : null,
            'user_agent' => $request instanceof Request
                ? mb_substr((string) $request->userAgent(), 0, 1000)
                : null,
        ]);
    }
}
