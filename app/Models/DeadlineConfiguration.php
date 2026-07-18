<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Model;

class DeadlineConfiguration extends Model
{
    protected $fillable = [
        'deadline_key',
        'title',
        'audience_role',
        'starts_at',
        'due_at',
        'priority',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'audience_role' => UserRole::class,
            'starts_at' => 'datetime',
            'due_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }
}
