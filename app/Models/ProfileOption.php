<?php

namespace App\Models;

use App\Enums\ProfileOptionField;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['field', 'value', 'normalized_value', 'sort_order', 'is_active', 'created_by_user_id'])]
class ProfileOption extends Model
{
    protected function casts(): array
    {
        return [
            'field' => ProfileOptionField::class,
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
