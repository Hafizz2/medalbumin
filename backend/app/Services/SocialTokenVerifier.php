<?php

namespace App\Services;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Verifies ID tokens issued to the mobile app by Google and Apple sign-in.
 */
class SocialTokenVerifier
{
    private const PROVIDERS = [
        'google' => [
            'jwks' => 'https://www.googleapis.com/oauth2/v3/certs',
            'issuers' => ['https://accounts.google.com', 'accounts.google.com'],
        ],
        'apple' => [
            'jwks' => 'https://appleid.apple.com/auth/keys',
            'issuers' => ['https://appleid.apple.com'],
        ],
    ];

    /**
     * @return array{sub:string, email:?string, name:?string}
     */
    public function verify(string $provider, string $idToken): array
    {
        $cfg = self::PROVIDERS[$provider];
        $audiences = array_filter((array) config("services.{$provider}.client_ids", []));

        try {
            $keys = Cache::remember("jwks.{$provider}", now()->addHours(6), fn () => Http::timeout(10)->get($cfg['jwks'])->throw()->json());
            $claims = (array) JWT::decode($idToken, JWK::parseKeySet($keys, 'RS256'));
        } catch (Throwable) {
            throw ValidationException::withMessages(['token' => 'Sign-in could not be verified. Please try again.']);
        }

        if (! in_array($claims['iss'] ?? null, $cfg['issuers'], true)) {
            throw ValidationException::withMessages(['token' => 'Invalid token issuer.']);
        }
        $aud = (array) ($claims['aud'] ?? []);
        if ($audiences && ! array_intersect($aud, $audiences)) {
            throw ValidationException::withMessages(['token' => 'This token was not issued for MedAlbumin.']);
        }

        return [
            'sub' => (string) $claims['sub'],
            'email' => isset($claims['email']) && ($claims['email_verified'] ?? true) ? strtolower((string) $claims['email']) : null,
            'name' => $claims['name'] ?? null,
        ];
    }
}
