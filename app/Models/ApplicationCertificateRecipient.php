<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ApplicationCertificateRecipient extends Model
{
    protected $fillable = [
        'research_application_id',
        'recipient_name',
        'normalized_name',
        'sort_order',
    ];

    protected function casts(): array
    {
        return ['sort_order' => 'integer'];
    }

    public function researchApplication(): BelongsTo
    {
        return $this->belongsTo(ResearchApplication::class);
    }

    public function certificate(): HasOne
    {
        return $this->hasOne(Certificate::class);
    }
}
