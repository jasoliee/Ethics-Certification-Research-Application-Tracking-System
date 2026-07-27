<?php

namespace App\Models;

use App\Enums\ResearchType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Defines an active, mandatory, and optionally research-type-scoped document requirement.
 */
class DocumentRequirement extends Model
{
    protected $fillable = [
        'code',
        'name',
        'description',
        'is_mandatory',
        'research_types',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_mandatory' => 'boolean',
            'research_types' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function applicationDocuments(): HasMany
    {
        return $this->hasMany(ApplicationDocument::class);
    }

    /**
     * Check whether this active configuration applies to the selected research type.
     */
    public function appliesTo(ResearchType|string|null $researchType): bool
    {
        $value = $researchType instanceof ResearchType ? $researchType->value : $researchType;
        $configuredTypes = $this->research_types ?? [];

        return $configuredTypes === []
            || ($value !== null && in_array($value, $configuredTypes, true));
    }
}
