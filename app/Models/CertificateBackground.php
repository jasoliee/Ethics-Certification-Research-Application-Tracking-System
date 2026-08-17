<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CertificateBackground extends Model
{
    public const TYPE_CERTIFICATE = 'certificate';

    public const TYPE_REVIEW_WORKSHEET = 'review_worksheet';

    protected $fillable = [
        'background_type',
        'asset_version',
        'source_kind',
        'original_file_name',
        'stored_file_path',
        'mime_type',
        'file_size_bytes',
        'sha256',
        'width_pixels',
        'height_pixels',
        'page_count',
        'uploaded_by_user_id',
        'is_active',
        'activated_at',
        'superseded_at',
    ];

    protected function casts(): array
    {
        return [
            'asset_version' => 'integer',
            'file_size_bytes' => 'integer',
            'width_pixels' => 'integer',
            'height_pixels' => 'integer',
            'page_count' => 'integer',
            'is_active' => 'boolean',
            'activated_at' => 'datetime',
            'superseded_at' => 'datetime',
        ];
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id')->withTrashed();
    }

    public function certificateVersions(): HasMany
    {
        return $this->hasMany(CertificateVersion::class);
    }

    public function typeLabel(): string
    {
        return $this->background_type === self::TYPE_REVIEW_WORKSHEET
            ? 'Review Worksheet Background'
            : 'Certificate Background';
    }
}
