<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Paper extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['is_premium' => 'boolean'];

    public const CATEGORIES = ['exit' => 'Exit exam', 'qualification' => 'Qualification', 'block' => 'Block exams'];

    public function questions(): HasMany
    {
        return $this->hasMany(Question::class)->orderBy('paper_position');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function scopePublished(Builder $q): Builder
    {
        return $q->where('status', 'published');
    }

    public function label(): string
    {
        return trim($this->name.' '.($this->year ?? ''));
    }
}
