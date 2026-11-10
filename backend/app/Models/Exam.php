<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Exam extends Model
{
    use LogsActivity;

    public const TYPES = ['grand' => 'Grand mock (national rank)', 'past_paper' => 'Past paper', 'department' => 'Department test', 'daily' => 'Daily mini'];

    public const DEFAULT_RULES = ['shuffle' => true, 'shuffle_options' => true, 'allow_back' => false, 'show_answers' => true, 'leaderboard' => true];

    protected $guarded = ['id', 'registrations_count'];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'blueprint' => 'array',
        'rules' => 'array',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['title', 'status', 'starts_at', 'access'])->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    public function questions(): BelongsToMany
    {
        return $this->belongsToMany(Question::class, 'exam_questions')->withPivot('position')->orderByPivot('position');
    }

    public function registrations(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'exam_registrations')->withPivot('created_at');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(QuizSession::class);
    }

    public function paper(): BelongsTo
    {
        return $this->belongsTo(Paper::class);
    }

    public function university(): BelongsTo
    {
        return $this->belongsTo(University::class);
    }

    public function rule(string $key): bool
    {
        return (bool) (($this->rules ?? []) + self::DEFAULT_RULES)[$key];
    }

    public function scopeVisible(Builder $q): Builder
    {
        return $q->whereIn('status', ['scheduled', 'live', 'results_out', 'published', 'automatic']);
    }

    /** Whether a student may start the exam right now. */
    public function isOpen(): bool
    {
        if (in_array($this->status, ['published', 'automatic'], true)) {
            return true;
        }
        if (! in_array($this->status, ['scheduled', 'live'], true) || ! $this->starts_at) {
            return false;
        }
        $closes = $this->ends_at ?? $this->starts_at->copy()->addMinutes($this->duration_minutes + 60);

        return now()->between($this->starts_at, $closes);
    }

    public function canBeTakenBy(User $user): bool
    {
        return match ($this->access) {
            'premium' => $user->isPremium(),
            'university' => $user->university_id && $user->university_id === $this->university_id,
            default => true,
        };
    }
}
