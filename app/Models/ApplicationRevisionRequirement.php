<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApplicationRevisionRequirement extends Model
{
    protected $fillable = [
        'application_revision_id',
        'document_requirement_id',
        'source_application_document_id',
        'replacement_application_document_id',
        'is_required',
    ];

    protected function casts(): array
    {
        return ['is_required' => 'boolean'];
    }

    public function revision(): BelongsTo
    {
        return $this->belongsTo(ApplicationRevision::class, 'application_revision_id');
    }

    public function requirement(): BelongsTo
    {
        return $this->belongsTo(DocumentRequirement::class, 'document_requirement_id');
    }

    public function sourceDocument(): BelongsTo
    {
        return $this->belongsTo(ApplicationDocument::class, 'source_application_document_id');
    }

    public function replacementDocument(): BelongsTo
    {
        return $this->belongsTo(ApplicationDocument::class, 'replacement_application_document_id');
    }
}
