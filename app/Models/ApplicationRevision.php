<?php

namespace App\Models;

use App\Enums\ApplicationRevisionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ApplicationRevision extends Model
{
    protected $fillable = [
        'research_application_id',
        'application_decision_release_id',
        'revision_number',
        'status',
        'due_at',
        'submitted_by_user_id',
        'submitted_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'revision_number' => 'integer',
            'status' => ApplicationRevisionStatus::class,
            'due_at' => 'datetime',
            'submitted_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function researchApplication(): BelongsTo
    {
        return $this->belongsTo(ResearchApplication::class);
    }

    public function decisionRelease(): BelongsTo
    {
        return $this->belongsTo(ApplicationDecisionRelease::class, 'application_decision_release_id');
    }

    public function requirements(): HasMany
    {
        return $this->hasMany(ApplicationRevisionRequirement::class);
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id')->withTrashed();
    }
}
