<?php

namespace App\Models;

use App\Enums\AdviserReturnReason;
use App\Enums\EndorsementStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Preserves one immutable Adviser return or endorsement action.
 */
class Endorsement extends Model
{
    protected $fillable = [
        'research_application_id',
        'adviser_user_id',
        'endorsement_status',
        'return_reason',
        'endorsement_remarks',
        'returned_at',
        'endorsed_at',
    ];

    protected function casts(): array
    {
        return [
            'endorsement_status' => EndorsementStatus::class,
            'return_reason' => AdviserReturnReason::class,
            'returned_at' => 'datetime',
            'endorsed_at' => 'datetime',
        ];
    }

    public function researchApplication(): BelongsTo
    {
        return $this->belongsTo(ResearchApplication::class);
    }

    public function adviser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'adviser_user_id')->withTrashed();
    }
}
