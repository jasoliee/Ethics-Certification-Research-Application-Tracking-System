<?php

namespace App\Models;

use App\Enums\ReviewFormArtifactStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReviewFormArtifact extends Model
{
    protected $fillable = [
        'review_form_submission_id',
        'review_submission_version_id',
        'certificate_background_id',
        'background_sha256',
        'artifact_version',
        'business_version',
        'status',
        'stored_file_path',
        'original_file_name',
        'mime_type',
        'file_size_bytes',
        'sha256',
        'template_code',
        'template_version',
        'template_sha256',
        'generator_version',
        'generated_at',
    ];

    protected function casts(): array
    {
        return [
            'artifact_version' => 'integer',
            'business_version' => 'integer',
            'status' => ReviewFormArtifactStatus::class,
            'file_size_bytes' => 'integer',
            'generated_at' => 'datetime',
        ];
    }

    public function formSubmission(): BelongsTo
    {
        return $this->belongsTo(ReviewFormSubmission::class, 'review_form_submission_id');
    }

    public function submissionVersion(): BelongsTo
    {
        return $this->belongsTo(ReviewSubmissionVersion::class, 'review_submission_version_id');
    }

    public function background(): BelongsTo
    {
        return $this->belongsTo(CertificateBackground::class, 'certificate_background_id');
    }
}
