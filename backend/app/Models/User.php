<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasRoles, LogsActivity, Notifiable, SoftDeletes, TwoFactorAuthenticatable;

    protected $guarded = ['id', 'is_staff', 'status', 'suspended_reason'];

    protected $hidden = [
        'password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes',
        'google_id', 'apple_id', 'telegram_id',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
            'last_active_at' => 'datetime',
            'exam_date' => 'date',
            'password' => 'hashed',
            'dark_mode' => 'boolean',
            'daily_reminder' => 'boolean',
            'notification_prefs' => 'array',
            'is_guest' => 'boolean',
            'is_staff' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (User $user) {
            $user->referral_code ??= static::generateReferralCode();
        });
    }

    public static function generateReferralCode(): string
    {
        do {
            $code = strtoupper(Str::random(7));
        } while (static::withTrashed()->where('referral_code', $code)->exists());

        return $code;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'suspended_reason', 'is_staff'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    // Relationships

    public function university(): BelongsTo
    {
        return $this->belongsTo(University::class);
    }

    public function devices(): HasMany
    {
        return $this->hasMany(Device::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function activeSubscription(): HasOne
    {
        return $this->hasOne(Subscription::class)->ofMany(['ends_at' => 'max'], function (Builder $q) {
            $q->where('status', 'active')->where('starts_at', '<=', now())->where('ends_at', '>', now());
        });
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function quizSessions(): HasMany
    {
        return $this->hasMany(QuizSession::class);
    }

    public function bookmarks(): BelongsToMany
    {
        return $this->belongsToMany(Question::class, 'bookmarks')->withPivot('created_at');
    }

    public function userNotes(): HasMany
    {
        return $this->hasMany(UserNote::class);
    }

    public function dailyActivities(): HasMany
    {
        return $this->hasMany(DailyActivity::class);
    }

    public function studyPlans(): HasMany
    {
        return $this->hasMany(StudyPlan::class);
    }

    public function matchProfile(): HasOne
    {
        return $this->hasOne(MatchProfile::class);
    }

    public function referrer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referred_by');
    }

    // Helpers

    /** Whether the student wants this notification category (account notices are always on). */
    public function wantsNotifications(string $category): bool
    {
        return $category === 'account' || ($this->notification_prefs[$category] ?? true) !== false;
    }

    public function isPremium(): bool
    {
        return $this->activeSubscription()->exists();
    }

    /** The device this API request comes from (tokens are named "device:{id}"). */
    public function currentDeviceId(): ?int
    {
        $token = $this->currentAccessToken();
        $name = $token instanceof \Laravel\Sanctum\PersonalAccessToken ? $token->name : null;

        return $name && str_starts_with($name, 'device:') ? (int) substr($name, 7) : null;
    }

    public function isSuspended(): bool
    {
        return $this->status === 'suspended';
    }

    public function initials(): string
    {
        $parts = preg_split('/\s+/', trim((string) $this->name)) ?: [];
        $ini = collect($parts)->filter()->take(2)->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->implode('');

        return $ini !== '' ? $ini : 'MN';
    }

    public function shortName(): string
    {
        $parts = preg_split('/\s+/', trim((string) $this->name)) ?: [];
        if (count($parts) < 2) {
            return (string) ($this->name ?: 'Student');
        }

        return $parts[0].' '.mb_strtoupper(mb_substr($parts[1], 0, 1)).'.';
    }

    public function avatarUrl(): ?string
    {
        return $this->avatar_path ? Storage::disk('public')->url($this->avatar_path) : null;
    }

    public function maskedPhone(): ?string
    {
        if (! $this->phone) {
            return null;
        }
        $p = $this->phone;

        return substr($p, 0, 7).' *** '.substr($p, -4);
    }

    public function scopeStudents(Builder $q): Builder
    {
        return $q->where('is_staff', false);
    }

    public function scopeStaff(Builder $q): Builder
    {
        return $q->where('is_staff', true);
    }
}
