<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApplicantSurveyResponse extends Model
{
    protected $fillable = [
        'research_application_id',
        'applicant_user_id',
        'ratings',
        'positive_feedback',
        'improvement_feedback',
        'additional_comments',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'ratings' => 'array',
            'completed_at' => 'datetime',
        ];
    }

    public function researchApplication(): BelongsTo
    {
        return $this->belongsTo(ResearchApplication::class);
    }

    public function applicant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'applicant_user_id')->withTrashed();
    }
}
