<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

class Subcategory extends Model
{
    use HasTranslations;

    public array $translatable = ['name'];

    protected $guarded = ['id'];

    protected $casts = ['is_active' => 'boolean'];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function topics(): HasMany
    {
        return $this->hasMany(Topic::class)->orderBy('sort');
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

    public function path(): string
    {
        return $this->department->getTranslation('name', app()->getLocale()).' › '.$this->name;
    }
}
