<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Defines one semester and academic-year timeframe for records and schedules.
 */
class AcademicTerm extends Model
{
    protected $fillable = [
        'semester',
        'academic_year',
        'starts_at',
        'ends_at',
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

    public function scopeCurrent(Builder $query, ?Carbon $at = null): Builder
    {
        $at ??= now();

        return $query
            ->where('is_active', true)
            ->where('starts_at', '<=', $at)
            ->where('ends_at', '>=', $at);
    }

    public function label(): string
    {
        return "{$this->semester}, A.Y. {$this->academic_year}";
    }

    public function isCurrent(?Carbon $at = null): bool
    {
        $at ??= now();

        return $this->is_active
            && $this->starts_at?->lessThanOrEqualTo($at)
            && $this->ends_at?->greaterThanOrEqualTo($at);
    }

    public function filterLabel(?Carbon $at = null): string
    {
        return ($this->isCurrent($at) ? 'Current - ' : '').$this->label();
    }

    public function deadlineConfigurations(): HasMany
    {
        return $this->hasMany(DeadlineConfiguration::class);
    }

    public function timelineCalendarEvents(): HasMany
    {
        return $this->hasMany(TimelineCalendarEvent::class);
    }

    public function researchApplications(): HasMany
    {
        return $this->hasMany(ResearchApplication::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }
}
