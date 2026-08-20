<?php

namespace App\Models;

use App\Enums\ApplicationStage;
use App\Enums\ApplicationStatus;
use App\Enums\ResearchType;
use App\Enums\ReviewConsensusStatus;
use App\Enums\ReviewDecision;
use Database\Factories\ResearchApplicationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Represents the Applicant-owned ethics application across its approved workflow states.
 */
class ResearchApplication extends Model
{
    /** @use HasFactory<ResearchApplicationFactory> */
    use HasFactory;

    protected $fillable = [
        'academic_term_id',
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
        'expected_start_date',
        'expected_end_date',
        'application_type',
        'application_status',
        'current_stage',
        'review_type',
        'current_revision_cycle',
        'review_consensus_status',
        'review_consensus_cycle',
        'review_consensus_decision',
        'review_consensus_signature',
        'review_consensus_evaluated_at',
        'review_conflicted_at',
        'submitted_at',
        'status_updated_at',
    ];

    protected function casts(): array
    {
        return [
            'application_status' => ApplicationStatus::class,
            'current_stage' => ApplicationStage::class,
            'research_type' => ResearchType::class,
            'current_revision_cycle' => 'integer',
            'review_consensus_status' => ReviewConsensusStatus::class,
            'review_consensus_cycle' => 'integer',
            'review_consensus_decision' => ReviewDecision::class,
            'review_consensus_evaluated_at' => 'datetime',
            'review_conflicted_at' => 'datetime',
            'expected_start_date' => 'date',
            'expected_end_date' => 'date',
            'submitted_at' => 'datetime',
            'status_updated_at' => 'datetime',
        ];
    }

    public function applicant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'applicant_user_id')->withTrashed();
    }

    public function academicTerm(): BelongsTo
    {
        return $this->belongsTo(AcademicTerm::class);
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

    public function decisionReleases(): HasMany
    {
        return $this->hasMany(ApplicationDecisionRelease::class);
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(ApplicationRevision::class);
    }

    public function surveyResponse(): HasOne
    {
        return $this->hasOne(ApplicantSurveyResponse::class);
    }

    public function certificate(): HasOne
    {
        return $this->hasOne(Certificate::class);
    }

    public function certificates(): HasMany
    {
        return $this->hasMany(Certificate::class);
    }

    public function certificateRecipients(): HasMany
    {
        return $this->hasMany(ApplicationCertificateRecipient::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Resolve the database-enforced single initial RES screening decision.
     */
    public function screening(): HasOne
    {
        return $this->hasOne(ApplicationScreening::class);
    }

    public function endorsements(): HasMany
    {
        return $this->hasMany(Endorsement::class);
    }

    public function latestEndorsement(): HasOne
    {
        return $this->hasOne(Endorsement::class)->latestOfMany();
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

    /**
     * Preserve whether this record has ever crossed formal submission, including returned corrections.
     */
    public function hasBeenFormallySubmitted(): bool
    {
        return $this->submitted_at !== null;
    }

    /**
     * Prefer structured dates while keeping historical free-text duration readable.
     */
    public function expectedDurationLabel(): string
    {
        if ($this->expected_start_date && $this->expected_end_date) {
            return $this->expected_start_date->format('M j, Y')
                .' to '
                .$this->expected_end_date->format('M j, Y');
        }

        return $this->expected_duration ?: 'Not specified';
    }

    public function statusLabel(): string
    {
        if ($this->application_status === ApplicationStatus::RevisionWindowOpen) {
            return 'For Revision C'.max(1, ((int) $this->current_revision_cycle) - 1);
        }

        return $this->application_status->label();
    }
}
