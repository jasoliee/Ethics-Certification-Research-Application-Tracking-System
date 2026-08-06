<?php

namespace App\Models;

use App\Enums\ReviewCommentCategory;
use App\Enums\ReviewCommentScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReviewComment extends Model
{
    protected $fillable = [
        'reviewer_assignment_id',
        'application_document_id',
        'scope',
        'category',
        'page_number',
        'body',
        'status',
        'resolved_at',
        'resolved_by_user_id',
        'released_at',
    ];

    protected function casts(): array
    {
        return [
            'scope' => ReviewCommentScope::class,
            'category' => ReviewCommentCategory::class,
            'page_number' => 'integer',
            'released_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(ReviewerAssignment::class, 'reviewer_assignment_id');
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(ApplicationDocument::class, 'application_document_id');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_user_id')->withTrashed();
    }
}
