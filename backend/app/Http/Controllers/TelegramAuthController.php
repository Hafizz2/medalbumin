<?php

namespace App\Http\Controllers;

use App\Models\OtpCode;
use App\Services\AuthService;
use App\Services\Otp\TelegramGatewayChannel;
use App\Services\Telegram\TelegramLogin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Browser side of "Continue with Telegram" for both the website and the mobile app.
 *
 *  App:     opens /auth/telegram/redirect?intent=app&return_to=<app url>
 *           → Telegram → /auth/telegram/callback → <app url>?ticket=… → POST /api/v1/auth/telegram
 *  Website: /auth/telegram/redirect?intent=web → Telegram → callback → signed in → /account
 */
class TelegramAuthController extends Controller
{
    public function __construct(private readonly TelegramLogin $telegram) {}

    /** Only the MedAlbumin app (and Expo Go during development) may receive login tickets. */
    private function safeReturnTo(?string $url): ?string
    {
        if (! $url) {
            return null;
        }
        foreach ((array) config('services.telegram.app_return_schemes') as $scheme) {
            if (str_starts_with($url, $scheme)) {
                return $url;
            }
        }

        return null;
    }

    public function redirect(Request $request): RedirectResponse|Response
    {
        $intent = $request->query('intent') === 'app' ? 'app' : 'web';
        $returnTo = $intent === 'app' ? ($this->safeReturnTo($request->query('return_to')) ?? config('services.telegram.app_return_default')) : null;

        if (! $this->telegram->isConfigured()) {
            return $this->fail($intent, $returnTo, 'Telegram login is not set up yet.');
        }

        return redirect()->away($this->telegram->authorizationUrl([
            'intent' => $intent,
            'return_to' => $returnTo,
            'referral_code' => substr((string) $request->query('ref'), 0, 12) ?: null,
        ]));
    }

    public function callback(Request $request, AuthService $auth): RedirectResponse|Response
    {
        if (! $request->filled('code') || ! $request->filled('state')) {
            // The student cancelled on Telegram's page — send them back where they came from.
            $context = $this->telegram->abandon((string) $request->query('state'));

            return $this->fail($context['intent'] ?? 'web', $context['return_to'] ?? null, 'Telegram login was cancelled.');
        }

        try {
            [$identity, $context] = $this->telegram->complete($request->query('code'), $request->query('state'));
        } catch (ValidationException $e) {
            return $this->fail('web', null, collect($e->errors())->flatten()->first());
        }

        if (($context['intent'] ?? 'web') === 'app') {
            $ticket = $this->telegram->issueTicket($identity, $context);
            $url = $context['return_to'].(str_contains($context['return_to'], '?') ? '&' : '?').'ticket='.urlencode($ticket);

            // A tiny page instead of a bare redirect: some Android browsers block custom-scheme redirects.
            return response()->view('auth.telegram-return', ['url' => $url]);
        }

        try {
            $user = $auth->userForTelegram($identity, referralCode: $context['referral_code'] ?? null);
        } catch (ValidationException $e) {
            return $this->fail('web', null, collect($e->errors())->flatten()->first());
        }
        if ($user->isSuspended()) {
            return $this->fail('web', null, 'This account is suspended. Contact support on Telegram.');
        }

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return redirect()->intended(route('account'));
    }

    private function fail(string $intent, ?string $returnTo, string $message): RedirectResponse|Response
    {
        if ($intent === 'app' && $returnTo) {
            return response()->view('auth.telegram-return', ['url' => $returnTo.(str_contains($returnTo, '?') ? '&' : '?').'error='.urlencode($message)]);
        }

        return redirect()->route('account.login')->withErrors(['telegram' => $message]);
    }

    /** Telegram Gateway delivery reports (sent / delivered / read / expired). */
    public function gatewayReport(Request $request): JsonResponse
    {
        $valid = TelegramGatewayChannel::validReport(
            (string) config('services.telegram_gateway.token'),
            $request->getContent(),
            $request->header('X-Request-Timestamp'),
            $request->header('X-Request-Signature'),
        );
        if (! $valid) {
            return response()->json(['ok' => false], 401);
        }

        $status = $request->input('delivery_status.status');
        if ($request->input('request_id') && in_array($status, ['sent', 'delivered', 'read', 'expired', 'revoked'], true)) {
            OtpCode::query()->where('provider_request_id', $request->input('request_id'))->update(['delivery_status' => $status]);
        }

        return response()->json(['ok' => true]);
    }
}
