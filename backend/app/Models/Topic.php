<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

class Topic extends Model
{
    use HasTranslations;

    public array $translatable = ['name'];

    protected $guarded = ['id'];

    public function subcategory(): BelongsTo
    {
        return $this->belongsTo(Subcategory::class);
    }

    public function questions(): HasMany
    {
        return $this->hasMany(Question::class);
    }
}
