<?php

namespace App\Models;

use App\Enums\ReviewType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Stores the single current administrative screening decision; every correction is audited.
 */
class ApplicationScreening extends Model
{
    protected $fillable = [
        'research_application_id',
        'screened_by_user_id',
        'review_type',
        'classification_reason',
        'classified_at',
    ];

    protected function casts(): array
    {
        return [
            'review_type' => ReviewType::class,
            'classified_at' => 'datetime',
        ];
    }

    public function researchApplication(): BelongsTo
    {
        return $this->belongsTo(ResearchApplication::class);
    }

    public function screenedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'screened_by_user_id')->withTrashed();
    }
}
