<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Simple key/value store for admin-editable settings (match rules, referral reward, etc.).
 */
class Setting extends Model
{
    public const CREATED_AT = null;

    protected $primaryKey = 'key';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = [];

    protected $casts = ['value' => 'json'];

    public static function get(string $key, mixed $default = null): mixed
    {
        $all = Cache::rememberForever('settings.all', fn () => static::query()->pluck('value', 'key')->all());

        return array_key_exists($key, $all) ? $all[$key] : $default;
    }

    public static function put(string $key, mixed $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget('settings.all');
    }
}
