<?php

namespace App\Models;

use App\Enums\ReceiptCheckStatus;
use App\Enums\ReviewType;
use App\Enums\ScreeningCompletenessStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Stores the single current administrative screening decision; every correction is audited.
 */
class ApplicationScreening extends Model
{
    protected $fillable = [
        'research_application_id',
        'screened_by_user_id',
        'completeness_status',
        'receipt_check_status',
        'required_documents_verified',
        'receipt_status_recorded',
        'basic_eligibility_confirmed',
        'screening_notes',
        'review_type',
        'classification_reason',
        'classified_at',
    ];

    protected function casts(): array
    {
        return [
            'completeness_status' => ScreeningCompletenessStatus::class,
            'receipt_check_status' => ReceiptCheckStatus::class,
            'required_documents_verified' => 'boolean',
            'receipt_status_recorded' => 'boolean',
            'basic_eligibility_confirmed' => 'boolean',
            'review_type' => ReviewType::class,
            'classified_at' => 'datetime',
        ];
    }

    public function researchApplication(): BelongsTo
    {
        return $this->belongsTo(ResearchApplication::class);
    }

    public function screenedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'screened_by_user_id')->withTrashed();
    }
}
