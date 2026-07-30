<?php

namespace App\Models;

use App\Enums\ProfileOptionField;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['profile_option_id', 'field', 'value', 'normalized_value'])]
class ProfileOptionAlias extends Model
{
    protected function casts(): array
    {
        return [
            'field' => ProfileOptionField::class,
        ];
    }

    public function profileOption(): BelongsTo
    {
        return $this->belongsTo(ProfileOption::class);
    }
}
