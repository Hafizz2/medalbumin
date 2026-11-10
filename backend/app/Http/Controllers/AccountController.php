<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Plan;
use App\Models\Setting;
use App\Models\User;
use App\Services\AuthService;
use App\Services\OtpService;
use App\Services\Payments\ChapaGateway;
use App\Services\Payments\PaymentService;
use App\Services\SubscriptionService;
use App\Services\Telegram\TelegramLogin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Student account on the website. Subscriptions are bought here (not inside the
 * mobile app), which keeps the iOS and Android apps within App Store / Play rules.
 */
class AccountController extends Controller
{
    // ---- Sign in ----

    public function login(Request $request, TelegramLogin $telegram): View
    {
        return view('site.account.login', [
            'telegramReady' => $telegram->isConfigured(),
            'phone' => $request->session()->get('login.phone'),
        ]);
    }

    public function sendCode(Request $request, OtpService $otp): RedirectResponse
    {
        $request->validate(['phone' => ['required', 'string', 'max:20']]);
        $phone = OtpService::normalizePhone($request->input('phone'))
            ?? throw ValidationException::withMessages(['phone' => 'Enter a valid Ethiopian mobile number, e.g. 91 234 5678.']);

        $sent = $otp->send($phone, $request->ip());
        $request->session()->put('login.phone', $phone);

        return back()->with('status', 'We sent a 6-digit code to your Telegram.'.(isset($sent['debug_code']) ? ' (Development code: '.$sent['debug_code'].')' : ''));
    }

    public function verifyCode(Request $request, OtpService $otp, AuthService $auth): RedirectResponse
    {
        $request->validate(['code' => ['required', 'digits:6']]);
        $phone = $request->session()->get('login.phone') ?? throw ValidationException::withMessages(['code' => 'Enter your phone number first.']);

        $code = $otp->verify($phone, $request->input('code'));
        $user = $auth->userForPhone($phone);
        if ($user->isSuspended()) {
            throw ValidationException::withMessages(['code' => 'This account is suspended. Contact support on Telegram.']);
        }
        $otp->consume($code, $request->input('code'));

        Auth::login($user, remember: true);
        $request->session()->forget('login.phone');
        $request->session()->regenerate();

        return redirect()->intended(route('account'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }

    // ---- Account ----

    public function show(Request $request, SubscriptionService $subs): View
    {
        /** @var User $user */
        $user = $request->user();

        return view('site.account.show', [
            'user' => $user,
            'until' => $subs->premiumUntil($user),
            'plans' => Plan::query()->active()->where('price', '>', 0)->get(),
            'payments' => $user->payments()->with('plan')->latest()->limit(10)->get(),
            'pending' => $user->payments()->where('status', 'pending')->where('gateway', 'bank_transfer')->exists(),
        ]);
    }

    public function checkout(Request $request, Plan $plan, PaymentService $payments, ChapaGateway $chapa): View
    {
        abort_unless($plan->is_active && (float) $plan->price > 0, 404);
        $promo = trim((string) $request->query('promo'));
        $quote = null;
        $promoError = null;
        try {
            $quote = $payments->quote($request->user(), $plan, $promo ?: null);
        } catch (ValidationException $e) {
            $promoError = collect($e->errors())->flatten()->first();
            $quote = $payments->quote($request->user(), $plan, null);
            $promo = '';
        }

        return view('site.account.checkout', [
            'plan' => $plan,
            'quote' => $quote,
            'promo' => $promo,
            'promoError' => $promoError,
            'online' => $chapa->isConfigured(),
            'banks' => Setting::get('bank_accounts', []) ?: [],
        ]);
    }

    public function pay(Request $request, Plan $plan, PaymentService $payments): RedirectResponse
    {
        abort_unless($plan->is_active, 404);
        $data = $request->validate([
            'method' => ['required', 'in:chapa,bank_transfer'],
            'promo_code' => ['nullable', 'string', 'max:40'],
            'receipt' => ['required_if:method,bank_transfer', 'nullable', 'file', 'mimes:jpg,jpeg,png,webp,heic,pdf', 'max:10240'],
            'reference' => ['nullable', 'string', 'max:100'],
            'bank_name' => ['nullable', 'string', 'max:80'],
        ], ['receipt.required_if' => 'Upload a photo or PDF of your transfer receipt.']);

        if ($data['method'] === 'bank_transfer' && $request->user()->payments()->where('gateway', 'bank_transfer')->where('status', 'pending')->exists()) {
            return back()->withErrors(['receipt' => 'You already have a receipt waiting for approval.']);
        }

        $payment = $payments->start($request->user(), $plan, $data['method'], $data['promo_code'] ?? null, [
            'receipt' => $request->file('receipt'),
            'reference' => $data['reference'] ?? null,
            'bank_name' => $data['bank_name'] ?? null,
        ]);

        if ($payment->status === 'approved') {
            return redirect()->route('account')->with('status', 'Your plan is active. Open the MedAlbumin app to start studying.');
        }
        if ($payment->checkout_url) {
            return redirect()->away($payment->checkout_url);
        }

        return redirect()->route('account')->with('status', 'Thanks! Your receipt was sent. Our team usually approves it within a few hours, and your app unlocks automatically.');
    }

    /** Back from Chapa: confirm and show the result. */
    public function paymentReturned(Request $request, Payment $payment, PaymentService $payments): RedirectResponse
    {
        abort_unless($payment->user_id === $request->user()->id, 404);
        $payment = $payments->confirmOnline($payment);

        return redirect()->route('account')->with('status', $payment->status === 'approved'
            ? 'Payment received — your plan is active. Open the MedAlbumin app to keep studying.'
            : 'We are confirming your payment. This page updates once it is complete.');
    }
}
