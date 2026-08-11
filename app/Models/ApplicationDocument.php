<?php

namespace App\Models;

use App\Enums\RequirementStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Stores one private, versioned requirement-document record.
 */
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
        'file_sha256',
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

    /**
     * Limit direct byte rendering to formats browsers can safely display themselves.
     */
    public function supportsInlinePreview(): bool
    {
        return in_array($this->mime_type, [
            'application/pdf',
            'image/jpeg',
            'image/png',
        ], true);
    }

    /**
     * Describe how the authenticated document dialog should present this file.
     *
     * Office files intentionally use a first-party fallback page. Sending a private
     * URL to a public Office viewer would disclose protected research documents.
     */
    public function previewKind(): string
    {
        return match ($this->mime_type) {
            'application/pdf' => 'pdf',
            'image/jpeg', 'image/png' => 'image',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'office',
            default => 'download',
        };
    }

    /**
     * Return a user-facing type derived from the server-verified MIME value.
     */
    public function fileTypeLabel(): string
    {
        return match ($this->mime_type) {
            'application/pdf' => 'PDF document',
            'application/msword' => 'Microsoft Word document (.doc)',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'Microsoft Word document (.docx)',
            'application/vnd.ms-excel' => 'Microsoft Excel workbook (.xls)',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'Microsoft Excel workbook (.xlsx)',
            'image/jpeg' => 'JPEG image',
            'image/png' => 'PNG image',
            default => 'Document',
        };
    }
}
