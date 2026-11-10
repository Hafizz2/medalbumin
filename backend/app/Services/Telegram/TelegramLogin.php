<?php

namespace App\Services\Telegram;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * "Log In With Telegram" — Telegram's OpenID Connect sign-in
 * (https://core.telegram.org/bots/telegram-login). Authorization Code flow with PKCE.
 *
 * Setup: @BotFather → your bot → Login Widget → add Allowed URLs
 * (the site origin and {APP_URL}/auth/telegram/callback), then copy the
 * Client ID and Client Secret into .env.
 */
class TelegramLogin
{
    private const ISSUER = 'https://oauth.telegram.org';

    public function isConfigured(): bool
    {
        return (bool) (config('services.telegram.client_id') && config('services.telegram.client_secret'));
    }

    public function redirectUri(): string
    {
        return route('auth.telegram.callback');
    }

    /**
     * Starts a login. $context is remembered until the callback (intent, device info…).
     */
    public function authorizationUrl(array $context): string
    {
        $state = Str::random(40);
        $verifier = Str::random(64);
        Cache::put('tg-login:'.$state, ['verifier' => $verifier, 'context' => $context], now()->addMinutes(10));

        return self::ISSUER.'/auth?'.http_build_query([
            'client_id' => config('services.telegram.client_id'),
            'redirect_uri' => $this->redirectUri(),
            'response_type' => 'code',
            'scope' => 'openid profile phone',
            'state' => $state,
            'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '='),
            'code_challenge_method' => 'S256',
        ]);
    }

    /**
     * Completes the login from the callback. Returns [identity, context].
     *
     * @return array{0: array{sub:string, telegram_id:?string, name:?string, username:?string, photo:?string, phone:?string}, 1: array}
     */
    public function complete(string $code, string $state): array
    {
        $pending = Cache::pull('tg-login:'.$state);
        if (! $pending) {
            throw ValidationException::withMessages(['telegram' => 'This Telegram login expired. Please try again.']);
        }

        $response = Http::asForm()->acceptJson()->timeout(15)
            ->withBasicAuth((string) config('services.telegram.client_id'), (string) config('services.telegram.client_secret'))
            ->post(self::ISSUER.'/token', [
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => $this->redirectUri(),
                'client_id' => config('services.telegram.client_id'),
                'code_verifier' => $pending['verifier'],
            ]);

        $idToken = $response->json('id_token');
        if (! $response->successful() || ! $idToken) {
            throw ValidationException::withMessages(['telegram' => 'Telegram did not confirm the login. Please try again.']);
        }

        return [$this->verifyIdToken($idToken), $pending['context']];
    }

    /**
     * Verifies a Telegram id_token (signature, issuer, audience, expiry) and returns the identity.
     */
    public function verifyIdToken(string $idToken): array
    {
        try {
            $keys = Cache::remember('jwks.telegram', now()->addHours(6), fn () => Http::timeout(10)->get(self::ISSUER.'/.well-known/jwks.json')->throw()->json());
            JWT::$leeway = 30;
            $claims = (array) JWT::decode($idToken, JWK::parseKeySet($keys, 'RS256'));
        } catch (Throwable) {
            Cache::forget('jwks.telegram'); // keys may have rotated
            throw ValidationException::withMessages(['telegram' => 'Telegram login could not be verified.']);
        }

        $aud = array_map('strval', (array) ($claims['aud'] ?? []));
        if (($claims['iss'] ?? null) !== self::ISSUER || ! in_array((string) config('services.telegram.client_id'), $aud, true)) {
            throw ValidationException::withMessages(['telegram' => 'This Telegram login was not issued for MedAlbumin.']);
        }

        $phone = isset($claims['phone_number']) ? '+'.ltrim((string) $claims['phone_number'], '+') : null;

        return [
            'sub' => (string) $claims['sub'],
            'telegram_id' => isset($claims['id']) ? (string) $claims['id'] : (string) $claims['sub'],
            'name' => $claims['name'] ?? trim(($claims['given_name'] ?? '').' '.($claims['family_name'] ?? '')) ?: null,
            'username' => $claims['preferred_username'] ?? null,
            'photo' => $claims['picture'] ?? null,
            'phone' => $phone,
        ];
    }

    /** Forgets a login that was cancelled and returns what it was started with. */
    public function abandon(string $state): array
    {
        return $state !== '' ? (Cache::pull('tg-login:'.$state)['context'] ?? []) : [];
    }

    /** One-time ticket handed to the mobile app through a deep link (valid 5 minutes, single use). */
    public function issueTicket(array $identity, array $context): string
    {
        $ticket = Str::random(48);
        Cache::put('tg-ticket:'.hash('sha256', $ticket), ['identity' => $identity, 'context' => $context], now()->addMinutes(5));

        return $ticket;
    }

    /** Reads a ticket; pass $consume=false to check it first (e.g. before the device-limit check). */
    public function redeemTicket(string $ticket, bool $consume = true): array
    {
        $key = 'tg-ticket:'.hash('sha256', $ticket);
        $data = $consume ? Cache::pull($key) : Cache::get($key);
        if (! $data) {
            throw ValidationException::withMessages(['ticket' => 'This Telegram login expired. Please try again.']);
        }

        return $data;
    }
}
