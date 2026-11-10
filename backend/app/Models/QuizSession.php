<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class QuizSession extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'config' => 'array',
        'score_pct' => 'float',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function paper(): BelongsTo
    {
        return $this->belongsTo(Paper::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(QuizAnswer::class)->orderBy('position');
    }

    public function isTimed(): bool
    {
        return $this->mode === 'timed';
    }

    public function deadline(): ?Carbon
    {
        return $this->time_limit_seconds && $this->started_at
            ? $this->started_at->copy()->addSeconds($this->time_limit_seconds)
            : null;
    }

    public function isExpired(): bool
    {
        $deadline = $this->deadline();

        // 30 s grace for network latency
        return $deadline !== null && now()->greaterThan($deadline->copy()->addSeconds(30));
    }
}
