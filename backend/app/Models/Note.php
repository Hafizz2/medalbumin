<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Translatable\HasTranslations;

class Note extends Model
{
    use HasTranslations, LogsActivity, SoftDeletes;

    public array $translatable = ['title', 'body'];

    protected $guarded = ['id', 'reads_count'];

    protected $casts = ['published_at' => 'datetime'];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['title', 'status', 'visibility'])->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function subcategory(): BelongsTo
    {
        return $this->belongsTo(Subcategory::class);
    }

    public function topic(): BelongsTo
    {
        return $this->belongsTo(Topic::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function userNotes(): MorphMany
    {
        return $this->morphMany(UserNote::class, 'notable');
    }

    public function scopePublished(Builder $q): Builder
    {
        return $q->where('status', 'published');
    }

    public function breadcrumb(): string
    {
        $dept = $this->department?->short_name ?: $this->department?->name;

        return trim($dept.($this->subcategory ? ' › '.$this->subcategory->name : ''));
    }

    /** Estimated reading time from the English body (≈200 words/min). */
    public static function estimateMinutes(string $html): int
    {
        $words = str_word_count(strip_tags($html));

        return max(1, (int) ceil($words / 200));
    }
}
