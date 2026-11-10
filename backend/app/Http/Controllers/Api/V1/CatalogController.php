<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Language;
use App\Models\Paper;
use App\Models\Question;
use App\Models\Setting;
use App\Models\University;
use App\Services\Telegram\TelegramLogin;
use App\Services\UiTranslationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class CatalogController extends Controller
{
    /** Public app configuration, fetched on launch (before sign-in). */
    public function config(TelegramLogin $telegram, UiTranslationService $ui): JsonResponse
    {
        $counts = Cache::remember('catalog.counts', now()->addMinutes(10), fn () => [
            'questions' => Question::query()->published()->count(),
            'papers' => Paper::query()->published()->count(),
        ]);

        return response()->json([
            // Only live languages. `ui_version` changes whenever that language's app text changes.
            'languages' => Language::active()->map(fn (Language $l) => [
                'code' => $l->code,
                'name' => $l->name,
                'native_name' => $l->native_name,
                'direction' => $l->direction,
                'ui_version' => $ui->version($l->code),
            ])->values(),
            'default_language' => Language::DEFAULT,
            'min_app_version' => config('medalbumin.min_app_version'),
            'auth' => [
                'telegram_login' => $telegram->isConfigured(),
                'telegram_login_url' => route('auth.telegram.redirect', ['intent' => 'app']),
                'phone_code_via' => config('services.otp.driver') === 'telegram' ? 'telegram' : 'log',
                'apple' => true,
                'email' => true,
            ],
            'support' => config('services.support'),
            'legal' => [
                'terms_url' => route('legal.terms'),
                'privacy_url' => route('legal.privacy'),
                'delete_account_url' => route('legal.delete-account'),
            ],
            'counts' => $counts,
            'guest_free_questions' => (int) Setting::get('guest_free_questions', 20),
        ]);
    }

    public function universities(): JsonResponse
    {
        return response()->json([
            'data' => University::query()->where('is_active', true)->orderBy('sort')->orderBy('name')->get(['id', 'name', 'short_name', 'city']),
        ]);
    }

    /**
     * App interface text for one live language (English fills any gaps).
     * Send If-None-Match with the last ETag to get 304 when nothing changed.
     */
    public function i18n(Request $request, string $locale, UiTranslationService $ui): JsonResponse
    {
        abort_unless(in_array($locale, Language::activeCodes(), true), 404, 'Language not available.');
        $version = $ui->version($locale);
        $etag = '"'.$version.'"';

        if ($request->header('If-None-Match') === $etag) {
            return response()->json(null, 304)->setEtag($version);
        }

        $bundle = $ui->bundle($locale);

        return response()->json([
            'locale' => $locale,
            'direction' => Language::isRtl($locale) ? 'rtl' : 'ltr',
            'version' => $version,
            'missing' => $bundle['missing'],
            // Nested like the app's en.json: {"home": {"cta": "Continue"}}
            'strings' => (object) \Illuminate\Support\Arr::undot($bundle['strings']),
        ])->setEtag($version)->header('Cache-Control', 'public, max-age=300');
    }
}
