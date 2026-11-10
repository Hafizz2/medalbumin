<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Languages are managed in Admin → Languages.
 * - English is the default and the fallback for any missing translation.
 * - A new language starts as a draft: editors can translate content and the
 *   app interface, but students only see it once a super admin activates it.
 */
class Language extends Model
{
    use LogsActivity;

    public const DEFAULT = 'en';

    protected $primaryKey = 'code';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = [];

    protected $casts = ['is_active' => 'boolean', 'is_default' => 'boolean', 'activated_at' => 'datetime'];

    protected static function booted(): void
    {
        static::saved(fn () => static::flush());
        static::deleted(fn () => static::flush());
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['is_active', 'native_name', 'direction'])->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    public static function flush(): void
    {
        Cache::forget('languages.all');
    }

    /** All configured languages (active and draft), default first. */
    public static function cached(): \Illuminate\Database\Eloquent\Collection
    {
        $rows = Cache::rememberForever('languages.all', function () {
            try {
                return static::query()->orderByDesc('is_default')->orderBy('sort')->orderBy('name')->get()->toArray();
            } catch (\Throwable) {
                return []; // before migrations run
            }
        });

        $items = static::hydrate($rows ?: [['code' => 'en', 'name' => 'English', 'native_name' => 'English', 'direction' => 'ltr', 'is_active' => true, 'is_default' => true, 'sort' => 0]]);

        return $items;
    }

    public static function active(): Collection
    {
        return static::cached()->where('is_active', true)->values();
    }

    /** Codes editors may write content in (active + draft). */
    public static function contentCodes(): array
    {
        return static::cached()->pluck('code')->all();
    }

    /** Codes students may choose. */
    public static function activeCodes(): array
    {
        return static::active()->pluck('code')->all();
    }

    /** ['en' => 'English', 'am' => 'አማርኛ', …] for editor tabs. */
    public static function options(bool $activeOnly = false): array
    {
        return ($activeOnly ? static::active() : static::cached())->mapWithKeys(fn ($l) => [$l->code => $l->native_name])->all();
    }

    public static function isRtl(string $code): bool
    {
        return static::cached()->firstWhere('code', $code)?->direction === 'rtl';
    }

    /** A student's preferred locale if it is live, else English. */
    public static function resolve(?string $code): string
    {
        return $code && in_array($code, static::activeCodes(), true) ? $code : self::DEFAULT;
    }
}
