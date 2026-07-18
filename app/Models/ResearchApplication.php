<?php

namespace App\Models;

use App\Enums\ApplicationStatus;
use Database\Factories\ResearchApplicationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ResearchApplication extends Model
{
    /** @use HasFactory<ResearchApplicationFactory> */
    use HasFactory;

    protected $fillable = [
        'application_code',
        'applicant_user_id',
        'adviser_user_id',
        'applicant_type',
        'research_title',
        'application_type',
        'application_status',
        'review_type',
        'submitted_at',
        'status_updated_at',
    ];

    protected function casts(): array
    {
        return [
            'application_status' => ApplicationStatus::class,
            'submitted_at' => 'datetime',
            'status_updated_at' => 'datetime',
        ];
    }

    public function applicant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'applicant_user_id');
    }

    public function adviser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'adviser_user_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ApplicationDocument::class);
    }

    public function reviewerAssignments(): HasMany
    {
        return $this->hasMany(ReviewerAssignment::class);
    }
}
