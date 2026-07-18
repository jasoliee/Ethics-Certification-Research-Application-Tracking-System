<?php

namespace App\Models;

use App\Enums\RequirementStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApplicationDocument extends Model
{
    protected $fillable = [
        'research_application_id',
        'document_requirement_id',
        'uploaded_by_user_id',
        'original_file_name',
        'stored_file_path',
        'mime_type',
        'file_size_bytes',
        'document_version',
        'validation_status',
        'is_current',
        'uploaded_at',
    ];

    protected function casts(): array
    {
        return [
            'validation_status' => RequirementStatus::class,
            'is_current' => 'boolean',
            'uploaded_at' => 'datetime',
        ];
    }

    public function researchApplication(): BelongsTo
    {
        return $this->belongsTo(ResearchApplication::class);
    }

    public function requirement(): BelongsTo
    {
        return $this->belongsTo(DocumentRequirement::class, 'document_requirement_id');
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }
}
