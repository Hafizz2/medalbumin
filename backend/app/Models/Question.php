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

class Question extends Model
{
    use HasTranslations, LogsActivity, SoftDeletes;

    public const DIFFICULTIES = ['easy' => 'Easy', 'moderate' => 'Moderate', 'hard' => 'Hard'];

    public array $translatable = ['stem', 'explanation'];

    protected $guarded = ['id', 'code', 'attempts_count', 'correct_count', 'total_time_ms'];

    protected $casts = [
        'is_free' => 'boolean',
        'translation_status' => 'array',
        'published_at' => 'datetime',
        'top_correct_pct' => 'float',
    ];

    protected static function booted(): void
    {
        static::created(function (Question $q) {
            $q->forceFill(['code' => 'Q-'.str_pad((string) $q->id, 5, '0', STR_PAD_LEFT)])->saveQuietly();
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'correct_option', 'stem', 'explanation', 'difficulty', 'is_free'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
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

    public function paper(): BelongsTo
    {
        return $this->belongsTo(Paper::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function options(): HasMany
    {
        return $this->hasMany(QuestionOption::class)->orderBy('key');
    }

    public function reports(): HasMany
    {
        return $this->hasMany(QuestionReport::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(QuestionComment::class);
    }

    public function videos(): BelongsToMany
    {
        return $this->belongsToMany(Video::class);
    }

    public function scopePublished(Builder $q): Builder
    {
        return $q->where('status', 'published');
    }

    public function correctPct(): ?float
    {
        return $this->attempts_count > 0 ? round($this->correct_count / $this->attempts_count * 100, 1) : null;
    }

    public function avgTimeSeconds(): ?int
    {
        return $this->attempts_count > 0 ? (int) round($this->total_time_ms / $this->attempts_count / 1000) : null;
    }

    public function imageUrl(): ?string
    {
        return $this->image_path ? Storage::disk('public')->url($this->image_path) : null;
    }

    /** Locale shown to a student: their content locale if a published translation exists, else English. */
    public function displayLocale(string $wanted): string
    {
        if ($wanted === 'en' || ! in_array($wanted, Language::activeCodes(), true)) {
            return 'en';
        }

        return ($this->translation_status[$wanted] ?? null) === 'published' ? $wanted : 'en';
    }

    /** ok | draft | none — used for the language tabs in the admin editor. */
    public function translationState(string $locale): string
    {
        if ($locale === 'en') {
            return 'ok';
        }
        $st = $this->translation_status[$locale] ?? null;

        return $st === 'published' ? 'ok' : ($st === 'draft' ? 'draft' : 'none');
    }

    public function breadcrumb(): string
    {
        $dept = $this->department?->short_name ?: $this->department?->name;

        return trim($dept.($this->subcategory ? ' › '.$this->subcategory->name : ''));
    }

    public function sourceLabel(): ?string
    {
        if ($this->paper) {
            return $this->paper->label();
        }

        return $this->exam_year ? 'Exam '.$this->exam_year : null;
    }
}
