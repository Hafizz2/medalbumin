<?php

namespace App\Services;

use App\Models\Language;
use App\Models\UiTranslation;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * App interface text ("Continue", "Past exam papers", …).
 * English rows define the keys; the mobile app ships the same English file
 * and downloads other languages from /api/v1/i18n/{locale}.
 *
 * Safety rules for translations:
 * - plain text only (no HTML / markup)
 * - every {placeholder} in English must appear in the translation, and no new ones
 * - length capped to avoid breaking layouts
 */
class UiTranslationService
{
    public const MAX_LENGTH = 1000;

    public static function placeholders(string $text): array
    {
        preg_match_all('/\{\{?\s*([a-zA-Z0-9_]+)\s*\}?\}/', $text, $m);
        $names = array_unique($m[1]);
        sort($names);

        return $names;
    }

    /** Returns null when valid, otherwise a readable problem. */
    public function problem(string $source, string $translation): ?string
    {
        if (mb_strlen($translation) > self::MAX_LENGTH) {
            return 'Too long.';
        }
        if ($translation !== strip_tags($translation)) {
            return 'Plain text only — remove HTML tags.';
        }
        $want = self::placeholders($source);
        $got = self::placeholders($translation);
        if ($want !== $got) {
            $missing = array_diff($want, $got);
            $extra = array_diff($got, $want);

            return trim(($missing ? 'Keep {'.implode('}, {', $missing).'}. ' : '').($extra ? 'Remove {'.implode('}, {', $extra).'}.' : ''));
        }

        return null;
    }

    /** key => English text */
    public function source(): array
    {
        return UiTranslation::query()->where('locale', Language::DEFAULT)->orderBy('key')->pluck('value', 'key')->all();
    }

    /**
     * Saves one translation. Empty value removes it (the app falls back to English).
     */
    public function save(string $locale, string $key, ?string $value, ?User $editor = null): void
    {
        abort_if($locale === Language::DEFAULT, 422, 'Edit English source text through the import.');
        abort_unless(in_array($locale, Language::contentCodes(), true), 422, 'Unknown language.');
        $source = UiTranslation::query()->where('locale', Language::DEFAULT)->where('key', $key)->value('value');
        abort_if($source === null, 422, 'Unknown text key.');

        $value = trim((string) $value);
        if ($value === '') {
            UiTranslation::query()->where('locale', $locale)->where('key', $key)->delete();
        } else {
            if ($p = $this->problem($source, $value)) {
                throw ValidationException::withMessages(["values.{$key}" => $p]);
            }
            UiTranslation::query()->updateOrCreate(['locale' => $locale, 'key' => $key], ['value' => $value, 'updated_by' => $editor?->id]);
        }
        $this->flush($locale);
    }

    /**
     * Imports a flat or nested JSON object. For English it defines/updates the keys
     * (keys missing from the file are kept unless $prune). For other languages it
     * only accepts known keys and validates every value.
     *
     * @return array{saved:int, skipped:array<string,string>, removed:int}
     */
    public function import(string $locale, array $data, ?User $editor = null, bool $prune = false): array
    {
        abort_unless(in_array($locale, Language::contentCodes(), true), 422, 'Unknown language.');
        $flat = $this->flatten($data);
        $source = $this->source();
        $saved = 0;
        $skipped = [];
        $removed = 0;

        DB::transaction(function () use ($locale, $flat, $source, $editor, $prune, &$saved, &$skipped, &$removed) {
            foreach ($flat as $key => $value) {
                if (! is_string($value) || ! preg_match('/^[a-zA-Z0-9_.\-]{1,150}$/', $key)) {
                    $skipped[$key] = 'Invalid key or value.';

                    continue;
                }
                if ($locale === Language::DEFAULT) {
                    if ($value !== strip_tags($value)) {
                        $skipped[$key] = 'Plain text only.';

                        continue;
                    }
                    UiTranslation::query()->updateOrCreate(['locale' => $locale, 'key' => $key], ['value' => $value, 'updated_by' => $editor?->id]);
                    $saved++;

                    continue;
                }
                if (! array_key_exists($key, $source)) {
                    $skipped[$key] = 'Not an English key.';

                    continue;
                }
                if ($value === '') {
                    continue;
                }
                if ($p = $this->problem($source[$key], $value)) {
                    $skipped[$key] = $p;

                    continue;
                }
                UiTranslation::query()->updateOrCreate(['locale' => $locale, 'key' => $key], ['value' => $value, 'updated_by' => $editor?->id]);
                $saved++;
            }

            if ($locale === Language::DEFAULT && $prune) {
                $gone = array_diff(array_keys($source), array_keys($flat));
                $removed = UiTranslation::query()->whereIn('key', $gone)->delete();
            }
        });

        $locale === Language::DEFAULT ? $this->flushAll() : $this->flush($locale);

        return ['saved' => $saved, 'skipped' => $skipped, 'removed' => $removed];
    }

    public function flatten(array $data, string $prefix = ''): array
    {
        $out = [];
        foreach ($data as $k => $v) {
            $key = $prefix === '' ? (string) $k : $prefix.'.'.$k;
            is_array($v) ? $out += $this->flatten($v, $key) : $out[$key] = $v;
        }

        return $out;
    }

    /** Translations for one locale, English filled in for anything missing. */
    public function bundle(string $locale): array
    {
        return Cache::rememberForever("ui.bundle.{$locale}", function () use ($locale) {
            $en = $this->source();
            if ($locale === Language::DEFAULT) {
                return ['strings' => $en, 'missing' => 0];
            }
            $tr = UiTranslation::query()->where('locale', $locale)->pluck('value', 'key')->all();

            return ['strings' => array_merge($en, array_intersect_key($tr, $en)), 'missing' => count(array_diff_key($en, $tr))];
        });
    }

    /** Version hash so the app re-downloads only when text changed. */
    public function version(string $locale): string
    {
        return substr(sha1(json_encode($this->bundle($locale)['strings'])), 0, 12);
    }

    public function coverage(): array
    {
        $total = UiTranslation::query()->where('locale', Language::DEFAULT)->count();
        $per = UiTranslation::query()->whereIn('key', UiTranslation::query()->where('locale', Language::DEFAULT)->select('key'))
            ->groupBy('locale')->selectRaw('locale, COUNT(*) n')->pluck('n', 'locale');

        return ['total' => $total, 'translated' => $per->all()];
    }

    public function flush(string $locale): void
    {
        Cache::forget("ui.bundle.{$locale}");
    }

    public function flushAll(): void
    {
        foreach (Language::contentCodes() as $code) {
            $this->flush($code);
        }
    }
}
