<?php

namespace App\Models;

use App\Enums\ReviewDecision;
use App\Enums\ReviewSubmissionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReviewSubmission extends Model
{
    protected $fillable = [
        'reviewer_assignment_id',
        'current_version_id',
        'status',
        'decision',
        'decision_comment',
        'draft_decision',
        'draft_decision_comment',
        'has_unsubmitted_changes',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ReviewSubmissionStatus::class,
            'decision' => ReviewDecision::class,
            'draft_decision' => ReviewDecision::class,
            'has_unsubmitted_changes' => 'boolean',
            'submitted_at' => 'datetime',
        ];
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(ReviewerAssignment::class, 'reviewer_assignment_id');
    }

    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(ReviewSubmissionVersion::class, 'current_version_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(ReviewSubmissionVersion::class);
    }
}
