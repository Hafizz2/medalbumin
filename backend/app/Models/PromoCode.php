<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PromoCode extends Model
{
    protected $guarded = ['id', 'used_count'];

    protected $casts = [
        'value' => 'decimal:2',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function university(): BelongsTo
    {
        return $this->belongsTo(University::class);
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(PromoRedemption::class);
    }

    public function isLive(): bool
    {
        return $this->is_active
            && (! $this->starts_at || $this->starts_at->isPast())
            && (! $this->ends_at || $this->ends_at->isFuture())
            && ($this->max_uses === null || $this->used_count < $this->max_uses);
    }

    /** Returns null when usable, otherwise a reason the student can read. */
    public function rejectionFor(User $user, Plan $plan): ?string
    {
        if (! $this->isLive()) {
            return 'This promo code has expired.';
        }
        if ($this->plan_id && $this->plan_id !== $plan->id) {
            return 'This code is for a different plan.';
        }
        if ($this->university_id && $this->university_id !== $user->university_id) {
            return 'This code is only for students of '.$this->university?->name.'.';
        }
        if ($this->redemptions()->where('user_id', $user->id)->exists()) {
            return 'You have already used this code.';
        }

        return null;
    }

    public function discountFor(Plan $plan): float
    {
        $price = (float) $plan->price;

        return match ($this->type) {
            'percent' => round($price * min(100, (float) $this->value) / 100, 2),
            'fixed' => min($price, (float) $this->value),
            default => 0.0,
        };
    }

    public function label(): string
    {
        return match ($this->type) {
            'percent' => rtrim(rtrim(number_format((float) $this->value, 2), '0'), '.').'% off',
            'fixed' => 'ETB '.number_format((float) $this->value).' off',
            'free_days' => 'Free '.(int) $this->value.' days',
        };
    }
}
