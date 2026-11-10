<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

class Specialty extends Model
{
    use HasTranslations;

    public const COMPETITION = ['low' => 'Low', 'medium' => 'Medium', 'high' => 'High', 'very_high' => 'Very high'];

    public array $translatable = ['name'];

    protected $guarded = ['id'];

    protected $casts = ['is_active' => 'boolean'];

    public function results(): HasMany
    {
        return $this->hasMany(MatchResult::class);
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true)->orderBy('sort');
    }
}
