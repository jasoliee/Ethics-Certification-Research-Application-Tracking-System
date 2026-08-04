<?php

namespace App\Models;

use App\Enums\ReviewerAssignmentStatus;
use App\Enums\ReviewerConflictStatus;
use Database\Factories\ReviewerAssignmentFactory;
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
        'conflict_status',
        'conflict_cleared_at',
        'conflict_declared_at',
        'assigned_at',
        'review_deadline_at',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'assignment_status' => ReviewerAssignmentStatus::class,
            'conflict_status' => ReviewerConflictStatus::class,
            'conflict_cleared_at' => 'datetime',
            'conflict_declared_at' => 'datetime',
            'assigned_at' => 'datetime',
            'review_deadline_at' => 'datetime',
            'submitted_at' => 'datetime',
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
}
