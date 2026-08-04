<?php

namespace App\Models;

use App\Enums\ReviewDecision;
use App\Enums\ReviewSubmissionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReviewSubmission extends Model
{
    protected $fillable = [
        'reviewer_assignment_id',
        'status',
        'decision',
        'decision_comment',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ReviewSubmissionStatus::class,
            'decision' => ReviewDecision::class,
            'submitted_at' => 'datetime',
        ];
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(ReviewerAssignment::class, 'reviewer_assignment_id');
    }
}
