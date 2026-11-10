<?php

namespace App\Services\Otp;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Telegram Gateway (https://core.telegram.org/gateway/api): delivers the login
 * code as a message from Telegram's official "Verification Codes" account.
 * Costs $0.01 per delivered code; undelivered codes are refunded after the ttl,
 * and codes that cannot be sent (no Telegram account) are never charged.
 */
class TelegramGatewayChannel implements OtpChannel
{
    private const BASE = 'https://gatewayapi.telegram.org/';

    public function __construct(
        private readonly string $token,
        private readonly ?string $senderUsername = null,
        private readonly ?string $callbackUrl = null,
    ) {}

    public function name(): string
    {
        return 'telegram';
    }

    private function http(): PendingRequest
    {
        // No automatic retry: a retried send could deliver (and bill) two codes.
        return Http::withToken($this->token)->acceptJson()->asJson()->timeout(15);
    }

    public function send(string $phone, string $code, int $ttlSeconds): ?string
    {
        try {
            $response = $this->http()->post(self::BASE.'sendVerificationMessage', array_filter([
                'phone_number' => $phone,
                'code' => $code,
                'ttl' => max(30, min(3600, $ttlSeconds)),
                'sender_username' => $this->senderUsername,
                'callback_url' => $this->callbackUrl,
                'payload' => 'login',
            ]));
        } catch (Throwable $e) {
            Log::error('Telegram Gateway unreachable: '.$e->getMessage());
            throw new OtpDeliveryException('We could not reach Telegram. Please try again in a moment.');
        }

        if ($response->successful() && $response->json('ok') === true) {
            return $response->json('result.request_id');
        }

        $error = (string) ($response->json('error') ?? $response->status());
        Log::warning('Telegram Gateway refused code', ['error' => $error, 'phone' => substr($phone, 0, 7).'***']);

        throw new OtpDeliveryException(match (true) {
            str_contains($error, 'PHONE_NUMBER_INVALID') => 'This phone number is not valid.',
            str_contains($error, 'FLOOD') || str_contains($error, 'TOO_MANY') => 'Too many codes were requested. Please wait a few minutes.',
            str_contains($error, 'BALANCE') || str_contains($error, 'ACCESS_TOKEN') => 'Login by Telegram code is temporarily unavailable. Use “Continue with Telegram” instead.',
            default => 'We could not send a Telegram code to this number. Make sure Telegram is installed and uses this phone number, or tap “Continue with Telegram”.',
        });
    }

    public function reportVerified(?string $requestId, string $code): void
    {
        if (! $requestId) {
            return;
        }
        try {
            $this->http()->post(self::BASE.'checkVerificationStatus', ['request_id' => $requestId, 'code' => $code]);
        } catch (Throwable) {
            // Statistics only — never block a login on this.
        }
    }

    /**
     * Delivery reports are signed: hex(HMAC_SHA256(timestamp + "\n" + body, SHA256(token))).
     */
    public static function validReport(string $token, string $body, ?string $timestamp, ?string $signature): bool
    {
        if (! $timestamp || ! $signature || abs(time() - (int) $timestamp) > 600) {
            return false;
        }
        $expected = hash_hmac('sha256', $timestamp."\n".$body, hash('sha256', $token, true));

        return hash_equals($expected, strtolower($signature));
    }
}
