<?php

namespace App\Models;

use App\Enums\CertificateVersionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CertificateVersion extends Model
{
    protected $fillable = [
        'certificate_id',
        'certificate_version',
        'status',
        'stored_file_path',
        'original_file_name',
        'mime_type',
        'file_size_bytes',
        'sha256',
        'official_template_version',
        'official_template_sha256',
        'certificate_background_id',
        'background_sha256',
        'generator_version',
        'generated_by_user_id',
        'generated_at',
        'issued_date',
        'valid_until',
        'signatory_name_snapshot',
        'signature_sha256_snapshot',
        'qr_code_path',
        'qr_code_sha256',
        'qr_code_width',
        'qr_code_height',
        'regenerated_at',
        'regeneration_reason',
        'released_by_user_id',
        'released_at',
        'claimed_by_user_id',
        'claimed_at',
    ];

    protected function casts(): array
    {
        return [
            'certificate_version' => 'integer',
            'status' => CertificateVersionStatus::class,
            'file_size_bytes' => 'integer',
            'generated_at' => 'datetime',
            'issued_date' => 'date',
            'valid_until' => 'date',
            'regenerated_at' => 'datetime',
            'released_at' => 'datetime',
            'claimed_at' => 'datetime',
        ];
    }

    public function certificate(): BelongsTo
    {
        return $this->belongsTo(Certificate::class);
    }

    public function background(): BelongsTo
    {
        return $this->belongsTo(CertificateBackground::class, 'certificate_background_id');
    }

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by_user_id')->withTrashed();
    }
}
