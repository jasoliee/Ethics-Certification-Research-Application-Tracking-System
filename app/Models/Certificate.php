<?php

namespace App\Models;

use App\Enums\CertificateStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Certificate extends Model
{
    protected $fillable = [
        'research_application_id',
        'applicant_user_id',
        'certificate_number',
        'status',
        'generation_failure_code',
        'current_certificate_version_id',
        'released_by_user_id',
        'released_at',
        'issued_date',
        'valid_until',
        'claimed_by_user_id',
        'claimed_certificate_version_id',
        'claimed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => CertificateStatus::class,
            'released_at' => 'datetime',
            'issued_date' => 'date',
            'valid_until' => 'date',
            'claimed_at' => 'datetime',
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

    public function versions(): HasMany
    {
        return $this->hasMany(CertificateVersion::class);
    }

    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(CertificateVersion::class, 'current_certificate_version_id');
    }

    public function claimedVersion(): BelongsTo
    {
        return $this->belongsTo(CertificateVersion::class, 'claimed_certificate_version_id');
    }

    public function releasedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'released_by_user_id')->withTrashed();
    }

    public function claimedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'claimed_by_user_id')->withTrashed();
    }
}
