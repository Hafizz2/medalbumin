<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Translatable\HasTranslations;

class Plan extends Model
{
    use HasTranslations, LogsActivity;

    // features: one benefit per line, per language
    public array $translatable = ['name', 'note', 'badge', 'features'];

    protected $guarded = ['id'];

    protected $casts = [
        'price' => 'decimal:2',
        'ends_on' => 'date',
        'is_active' => 'boolean',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['price', 'is_active', 'duration_days', 'ends_on'])->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true)->orderBy('sort');
    }

    /** End date for a subscription to this plan starting at $from. */
    public function endsAtFrom(Carbon $from): Carbon
    {
        if ($this->ends_on) {
            return $this->ends_on->copy()->endOfDay();
        }

        return $from->copy()->addDays($this->duration_days ?: 30);
    }

    /** Benefits shown on the pricing page, in the current language. */
    public function featureList(): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\r?\n/', (string) $this->features))));
    }

    /** Price per month, for comparing plans ("ETB 150 / month"). */
    public function monthlyPrice(): ?float
    {
        $days = $this->ends_on ? max(1, (int) now()->diffInDays($this->ends_on)) : $this->duration_days;

        return $days && $days > 31 ? round((float) $this->price / ($days / 30), 0) : null;
    }

    public function durationLabel(): string
    {
        if ($this->ends_on) {
            return 'Until '.$this->ends_on->format('j M Y');
        }
        $d = $this->duration_days ?: 30;

        return match (true) {
            $d % 365 === 0 => ($d / 365).' year'.($d > 365 ? 's' : ''),
            $d % 30 === 0 => ($d / 30).' month'.($d > 30 ? 's' : ''),
            default => $d.' days',
        };
    }
}
