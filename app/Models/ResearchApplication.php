<?php

namespace App\Models;

use App\Enums\ApplicationStage;
use App\Enums\ApplicationStatus;
use App\Enums\ResearchType;
use Database\Factories\ResearchApplicationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Represents the Applicant-owned ethics application across its approved workflow states.
 */
class ResearchApplication extends Model
{
    /** @use HasFactory<ResearchApplicationFactory> */
    use HasFactory;

    protected $fillable = [
        'application_code',
        'applicant_user_id',
        'draft_owner_user_id',
        'adviser_user_id',
        'applicant_type',
        'research_title',
        'research_type',
        'research_category',
        'institution',
        'department',
        'program',
        'abstract',
        'target_participants',
        'expected_duration',
        'application_type',
        'application_status',
        'current_stage',
        'review_type',
        'submitted_at',
        'status_updated_at',
    ];

    protected function casts(): array
    {
        return [
            'application_status' => ApplicationStatus::class,
            'current_stage' => ApplicationStage::class,
            'research_type' => ResearchType::class,
            'submitted_at' => 'datetime',
            'status_updated_at' => 'datetime',
        ];
    }

    public function applicant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'applicant_user_id')->withTrashed();
    }

    public function adviser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'adviser_user_id')->withTrashed();
    }

    /**
     * Resolve the user holding the database-enforced editable-draft slot.
     */
    public function draftOwner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'draft_owner_user_id')->withTrashed();
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ApplicationDocument::class);
    }

    public function reviewerAssignments(): HasMany
    {
        return $this->hasMany(ReviewerAssignment::class);
    }

    /**
     * Determine whether this record has crossed the formal applicant-submission boundary.
     */
    public function isFormallySubmitted(): bool
    {
        // Archived records remain historical and must not re-enter active Adviser application surfaces.
        return $this->submitted_at !== null
            && ! in_array($this->application_status, [
                ApplicationStatus::Draft,
                ApplicationStatus::Incomplete,
                ApplicationStatus::Archived,
            ], true);
    }
}
