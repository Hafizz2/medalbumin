<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Translatable\HasTranslations;

class Video extends Model
{
    use HasTranslations, LogsActivity, SoftDeletes;

    public array $translatable = ['title', 'description'];

    protected $guarded = ['id', 'views_count', 'completions_count', 'watch_seconds_total'];

    protected $casts = ['variants' => 'array', 'captions' => 'array'];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['title', 'status', 'access'])->logOnlyDirty()->dontSubmitEmptyLogs();
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

    public function chapters(): HasMany
    {
        return $this->hasMany(VideoChapter::class)->orderBy('start_seconds');
    }

    public function questions(): BelongsToMany
    {
        return $this->belongsToMany(Question::class);
    }

    public function scopePublished(Builder $q): Builder
    {
        return $q->where('status', 'published');
    }

    public function breadcrumb(): string
    {
        $parts = array_filter([
            $this->department?->short_name ?: $this->department?->name,
            $this->subcategory?->name,
            $this->topic?->name,
        ]);

        return implode(' › ', $parts);
    }

    public function completionPct(): int
    {
        return $this->views_count > 0 ? (int) round($this->completions_count / $this->views_count * 100) : 0;
    }

    public function thumbnailUrl(): ?string
    {
        if ($this->thumbnail_path) {
            return Storage::disk('public')->url($this->thumbnail_path);
        }

        return $this->source === 'youtube' && $this->source_path
            ? 'https://i.ytimg.com/vi/'.$this->source_path.'/hqdefault.jpg'
            : null;
    }

    /** Playable sources, best quality first. */
    public function streams(): array
    {
        if ($this->source === 'youtube') {
            return [['quality' => 'youtube', 'url' => 'https://www.youtube.com/watch?v='.$this->source_path, 'youtube_id' => $this->source_path]];
        }
        if ($this->source === 'url') {
            return [['quality' => 'source', 'url' => $this->source_path]];
        }
        $out = [];
        foreach ($this->variants ?? [] as $quality => $path) {
            $out[] = ['quality' => (string) $quality, 'url' => Storage::disk('public')->url($path)];
        }
        if (! $out && $this->source_path) {
            $out[] = ['quality' => 'source', 'url' => Storage::disk('public')->url($this->source_path)];
        }

        return $out;
    }

    public function captionUrls(): array
    {
        return collect($this->captions ?? [])->map(fn ($p) => Storage::disk('public')->url($p))->all();
    }

    public function durationLabel(): string
    {
        $s = $this->duration_seconds;

        return sprintf('%02d:%02d', intdiv($s, 60), $s % 60);
    }
}
