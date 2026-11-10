<?php

namespace App\Services\Payments;

use App\Models\Payment;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Chapa (https://developer.chapa.co) hosted checkout. Chapa's checkout also
 * accepts Telebirr, CBE Birr, M-Pesa and cards, so the Telebirr / CBE Birr
 * buttons in the app go through here until direct merchant APIs are set up.
 */
class ChapaGateway
{
    private const BASE = 'https://api.chapa.co/v1';

    public function isConfigured(): bool
    {
        return (bool) config('services.chapa.secret_key');
    }

    /** Creates a checkout and returns its URL. */
    public function initialize(Payment $payment): string
    {
        $user = $payment->user;
        [$first, $last] = array_pad(explode(' ', trim((string) $user->name), 2), 2, null);

        $response = Http::withToken(config('services.chapa.secret_key'))
            ->acceptJson()->timeout(20)
            ->post(self::BASE.'/transaction/initialize', array_filter([
                'amount' => number_format((float) $payment->amount, 2, '.', ''),
                'currency' => $payment->currency,
                'email' => $user->email,
                'first_name' => $first ?: 'MedAlbumin',
                'last_name' => $last ?: 'Student',
                'phone_number' => $user->phone ? '0'.substr($user->phone, 4) : null,
                'tx_ref' => $payment->tx_ref,
                'callback_url' => route('webhooks.chapa'),
                'return_url' => route('payments.return', ['tx_ref' => $payment->tx_ref]),
                'customization' => [
                    'title' => 'MedAlbumin',
                    'description' => 'MedAlbumin '.$payment->plan->getTranslation('name', 'en').' plan',
                ],
                'meta' => ['hide_receipt' => false],
            ]));

        $url = $response->json('data.checkout_url');
        if (! $response->successful() || ! $url) {
            throw new RuntimeException('Chapa checkout failed: '.$response->body());
        }

        return $url;
    }

    /**
     * Asks Chapa for the real status — never trust the redirect or webhook body alone.
     *
     * @return array{paid:bool, amount:?float, reference:?string, raw:array}
     */
    public function verify(string $txRef): array
    {
        $response = Http::withToken(config('services.chapa.secret_key'))->acceptJson()->timeout(20)
            ->get(self::BASE.'/transaction/verify/'.urlencode($txRef));

        $data = $response->json('data') ?? [];

        return [
            'paid' => $response->successful() && ($data['status'] ?? null) === 'success',
            'amount' => isset($data['amount']) ? (float) $data['amount'] : null,
            'reference' => $data['reference'] ?? null,
            'raw' => $data,
        ];
    }

    public function validWebhookSignature(string $body, ?string $signature): bool
    {
        $secret = config('services.chapa.webhook_secret');
        if (! $secret || ! $signature) {
            return false;
        }

        return hash_equals(hash_hmac('sha256', $body, $secret), $signature)
            || hash_equals(hash_hmac('sha256', $secret, $secret), $signature); // Chapa's "chapa-signature" variant
    }
}
