<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TimelineCalendarEvent extends Model
{
    protected $fillable = [
        'milestone_key',
        'label',
        'term_label',
        'starts_at',
        'ends_at',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }
}
