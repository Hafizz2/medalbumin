<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscription extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['starts_at' => 'datetime', 'ends_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function scopeCurrent(Builder $q): Builder
    {
        return $q->where('status', 'active')->where('starts_at', '<=', now())->where('ends_at', '>', now());
    }
}
