<?php

namespace App\Models;

use App\Enums\ApplicationRevisionStatus;
use App\Enums\ReviewDecision;
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
        'review_cycle',
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
            'review_cycle' => 'integer',
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

    public function reviewSubmissionVersions(): HasMany
    {
        return $this->hasMany(ReviewSubmissionVersion::class);
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

    /**
     * Keep only the newest non-superseded review cycle for each application/reviewer pair.
     *
     * Submitted work from an earlier cycle remains available as history, but it must not
     * appear beside the active revision assignment on Reviewer dashboard/list surfaces.
     */
    public function scopeLatestCycleForReviewer(Builder $query): Builder
    {
        return $query->whereNotExists(function ($newer): void {
            $newer->selectRaw('1')
                ->from('reviewer_assignments as newer_reviewer_assignments')
                ->whereColumn(
                    'newer_reviewer_assignments.research_application_id',
                    'reviewer_assignments.research_application_id',
                )
                ->whereColumn(
                    'newer_reviewer_assignments.reviewer_user_id',
                    'reviewer_assignments.reviewer_user_id',
                )
                ->whereColumn(
                    'newer_reviewer_assignments.review_cycle',
                    '>',
                    'reviewer_assignments.review_cycle',
                )
                ->whereNull('newer_reviewer_assignments.superseded_at')
                ->where(
                    'newer_reviewer_assignments.assignment_status',
                    '!=',
                    ReviewerAssignmentStatus::Superseded->value,
                );
        });
    }

    /**
     * Limit Reviewer "Completed" surfaces to an actually released final approval.
     */
    public function scopeCompletedFinalApproval(Builder $query): Builder
    {
        return $query
            ->where('reviewer_assignments.assignment_status', ReviewerAssignmentStatus::DecisionSubmitted->value)
            ->whereHas('researchApplication.decisionReleases', fn (Builder $releases) => $releases
                ->where('application_decision_releases.decision', ReviewDecision::Approved->value)
                ->whereColumn(
                    'application_decision_releases.review_cycle',
                    'reviewer_assignments.review_cycle',
                ))
            ->whereDoesntHave('researchApplication.revisions', fn (Builder $revisions) => $revisions
                ->where('application_revisions.status', '!=', ApplicationRevisionStatus::Completed->value));
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
