<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Payment extends Model
{
    use LogsActivity;

    public const GATEWAYS = [
        'telebirr' => 'Telebirr',
        'cbe_birr' => 'CBE Birr',
        'chapa' => 'Chapa',
        'bank_transfer' => 'Bank transfer',
        'promo' => 'Promo code',
        'manual' => 'Manual',
    ];

    protected $guarded = ['id'];

    protected $casts = [
        'amount' => 'decimal:2',
        'discount' => 'decimal:2',
        'gateway_payload' => 'array',
        'reviewed_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['status', 'reject_reason'])->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function promoCode(): BelongsTo
    {
        return $this->belongsTo(PromoCode::class);
    }

    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class);
    }

    public function gatewayLabel(): string
    {
        $label = self::GATEWAYS[$this->gateway] ?? $this->gateway;

        return $this->gateway === 'bank_transfer' && $this->bank_name ? $this->bank_name.' transfer' : $label;
    }

    public function receiptUrl(): ?string
    {
        return $this->receipt_path ? route('admin.payments.receipt', $this) : null;
    }

    /** Another payment already used this bank reference — likely a reused receipt. */
    public function referenceReused(): bool
    {
        return $this->reference !== null && static::query()
            ->where('reference', $this->reference)
            ->where('id', '!=', $this->id)
            ->whereIn('status', ['pending', 'approved'])
            ->exists();
    }

    public function amountMatches(): bool
    {
        return $this->plan && abs((float) $this->amount - ((float) $this->plan->price - (float) $this->discount)) < 0.01;
    }
}
