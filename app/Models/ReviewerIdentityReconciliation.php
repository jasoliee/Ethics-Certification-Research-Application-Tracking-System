<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReviewerIdentityReconciliation extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_KEPT_SEPARATE = 'kept_separate';

    public const STATUS_MERGED = 'merged';

    protected $fillable = [
        'source_user_id',
        'target_adviser_user_id',
        'status',
        'matched_fields',
        'reason',
        'resolution_notes',
        'resolved_by_user_id',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'matched_fields' => 'array',
            'resolved_at' => 'datetime',
        ];
    }

    public function sourceUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'source_user_id')->withTrashed();
    }

    public function targetAdviser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_adviser_user_id')->withTrashed();
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_user_id')->withTrashed();
    }
}
