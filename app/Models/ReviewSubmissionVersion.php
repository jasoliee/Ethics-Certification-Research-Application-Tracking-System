<?php

namespace App\Models;

use App\Enums\ReviewDecision;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReviewSubmissionVersion extends Model
{
    protected $fillable = [
        'review_submission_id',
        'reviewer_assignment_id',
        'version_number',
        'submission_token',
        'decision',
        'decision_comment',
        'snapshot_schema_version',
        'payload_snapshot',
        'payload_sha256',
        'request_sha256',
        'submitted_by_user_id',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'version_number' => 'integer',
            'decision' => ReviewDecision::class,
            'snapshot_schema_version' => 'integer',
            'payload_snapshot' => 'array',
            'submitted_at' => 'datetime',
        ];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(ReviewSubmission::class, 'review_submission_id');
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(ReviewerAssignment::class, 'reviewer_assignment_id');
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id')->withTrashed();
    }

    public function artifacts(): HasMany
    {
        return $this->hasMany(ReviewFormArtifact::class);
    }
}
