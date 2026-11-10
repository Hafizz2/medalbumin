<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

class Department extends Model
{
    use HasTranslations;

    public array $translatable = ['name'];

    protected $guarded = ['id'];

    protected $casts = ['is_active' => 'boolean'];

    public function subcategories(): HasMany
    {
        return $this->hasMany(Subcategory::class)->orderBy('sort');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(Question::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(Note::class);
    }

    public function videos(): HasMany
    {
        return $this->hasMany(Video::class);
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true)->orderBy('sort');
    }
}
