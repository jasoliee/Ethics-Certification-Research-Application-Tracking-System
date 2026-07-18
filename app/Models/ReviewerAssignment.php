<?php

namespace App\Models;

use App\Enums\ReviewerAssignmentStatus;
use Database\Factories\ReviewerAssignmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReviewerAssignment extends Model
{
    /** @use HasFactory<ReviewerAssignmentFactory> */
    use HasFactory;

    protected $fillable = [
        'research_application_id',
        'reviewer_user_id',
        'review_type',
        'assignment_status',
        'assigned_at',
        'review_deadline_at',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'assignment_status' => ReviewerAssignmentStatus::class,
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
}
