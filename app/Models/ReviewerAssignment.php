<?php

namespace App\Models;

use App\Enums\ReviewerAssignmentStatus;
use Database\Factories\ReviewerAssignmentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ReviewerAssignment extends Model
{
    /** @use HasFactory<ReviewerAssignmentFactory> */
    use HasFactory;

    protected $fillable = [
        'research_application_id',
        'reviewer_user_id',
        'review_type',
        'assignment_status',
        'assignment_sequence',
        'replaces_assignment_id',
        'assigned_at',
        'review_deadline_at',
        'submitted_at',
        'superseded_at',
        'superseded_by_user_id',
        'supersession_reason',
        'superseded_from_status',
    ];

    protected function casts(): array
    {
        return [
            'assignment_status' => ReviewerAssignmentStatus::class,
            'assigned_at' => 'datetime',
            'review_deadline_at' => 'datetime',
            'submitted_at' => 'datetime',
            'superseded_at' => 'datetime',
        ];
    }

    public function researchApplication(): BelongsTo
    {
        return $this->belongsTo(ResearchApplication::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_user_id');
    }

    public function reviewSubmission(): HasOne
    {
        return $this->hasOne(ReviewSubmission::class);
    }

    public function formSubmissions(): HasMany
    {
        return $this->hasMany(ReviewFormSubmission::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(ReviewComment::class);
    }

    public function scopeCurrent(Builder $query): Builder
    {
        return $query->whereNull('superseded_at');
    }

    public function isCurrent(): bool
    {
        return $this->superseded_at === null
            && $this->assignment_status !== ReviewerAssignmentStatus::Superseded;
    }

    public function replaces(): BelongsTo
    {
        return $this->belongsTo(self::class, 'replaces_assignment_id');
    }

    public function supersededBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'superseded_by_user_id')->withTrashed();
    }
}
