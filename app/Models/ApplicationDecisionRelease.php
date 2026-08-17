<?php

namespace App\Models;

use App\Enums\ReviewDecision;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ApplicationDecisionRelease extends Model
{
    protected $fillable = [
        'research_application_id',
        'review_cycle',
        'source_review_type',
        'source_review_submission_id',
        'source_review_submission_version_id',
        'decision',
        'review_consensus_signature',
        'released_feedback_snapshot',
        'released_by_user_id',
        'released_at',
    ];

    protected function casts(): array
    {
        return [
            'decision' => ReviewDecision::class,
            'review_cycle' => 'integer',
            'released_feedback_snapshot' => 'array',
            'released_at' => 'datetime',
        ];
    }

    public function researchApplication(): BelongsTo
    {
        return $this->belongsTo(ResearchApplication::class);
    }

    public function releasedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'released_by_user_id')->withTrashed();
    }

    public function sourceReviewSubmission(): BelongsTo
    {
        return $this->belongsTo(ReviewSubmission::class, 'source_review_submission_id');
    }

    public function sourceReviewSubmissionVersion(): BelongsTo
    {
        return $this->belongsTo(ReviewSubmissionVersion::class, 'source_review_submission_version_id');
    }

    public function releasedComments(): HasMany
    {
        return $this->hasMany(ReviewComment::class);
    }
}
