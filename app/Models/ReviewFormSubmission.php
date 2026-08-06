<?php

namespace App\Models;

use App\Enums\ReviewDecision;
use App\Enums\ReviewFormStatus;
use App\Enums\ReviewFormType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReviewFormSubmission extends Model
{
    protected $fillable = [
        'reviewer_assignment_id',
        'form_type',
        'status',
        'catalog_version',
        'catalog_snapshot',
        'finalized_payload_snapshot',
        'responses',
        'consent_required',
        'consent_not_required_explanation',
        'recommendation',
        'recommendation_comments',
        'review_date',
        'finalized_at',
    ];

    protected function casts(): array
    {
        return [
            'form_type' => ReviewFormType::class,
            'status' => ReviewFormStatus::class,
            'responses' => 'array',
            'catalog_snapshot' => 'array',
            'finalized_payload_snapshot' => 'array',
            'consent_required' => 'boolean',
            'recommendation' => ReviewDecision::class,
            'review_date' => 'date',
            'finalized_at' => 'datetime',
        ];
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(ReviewerAssignment::class, 'reviewer_assignment_id');
    }
}
