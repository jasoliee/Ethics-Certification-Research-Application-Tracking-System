<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A declared application-specific disqualifier used before reviewer assignment. */
class ReviewerConflict extends Model
{
    protected $fillable = [
        'research_application_id',
        'reviewer_user_id',
        'declared_by_user_id',
        'reason',
        'declared_at',
        'cleared_by_user_id',
        'cleared_at',
    ];

    protected function casts(): array
    {
        return [
            'declared_at' => 'datetime',
            'cleared_at' => 'datetime',
        ];
    }

    public function researchApplication(): BelongsTo
    {
        return $this->belongsTo(ResearchApplication::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_user_id')->withTrashed();
    }

    public function declaredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'declared_by_user_id')->withTrashed();
    }

    public function clearedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cleared_by_user_id')->withTrashed();
    }
}
