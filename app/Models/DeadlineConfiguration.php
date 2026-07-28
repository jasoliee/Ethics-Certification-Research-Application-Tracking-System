<?php

namespace App\Models;

use App\Enums\DeadlineManualStatus;
use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Model;

/**
 * Stores one configured process schedule and optional RES manual override.
 */
class DeadlineConfiguration extends Model
{
    protected $fillable = [
        'deadline_key',
        'title',
        'audience_role',
        'semester_label',
        'starts_at',
        'due_at',
        'manual_status',
        'priority',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'audience_role' => UserRole::class,
            'starts_at' => 'datetime',
            'due_at' => 'datetime',
            'manual_status' => DeadlineManualStatus::class,
            'is_active' => 'boolean',
        ];
    }
}
