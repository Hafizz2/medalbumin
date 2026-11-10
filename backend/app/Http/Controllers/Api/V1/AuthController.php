<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\Language;
use App\Models\User;
use App\Services\AuthService;
use App\Services\OtpService;
use App\Services\SocialTokenVerifier;
use App\Services\Telegram\TelegramLogin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Student sign-in. Every successful sign-in returns a bearer token bound to one device.
 */
class AuthController extends Controller
{
    public function __construct(
        private readonly OtpService $otp,
        private readonly AuthService $auth,
        private readonly SocialTokenVerifier $social,
    ) {}

    private function deviceRules(): array
    {
        return [
            'device_id' => ['required', 'string', 'max:100'],
            'device_name' => ['nullable', 'string', 'max:120'],
            'platform' => ['nullable', 'in:ios,android,web'],
            'app_version' => ['nullable', 'string', 'max:20'],
            'push_token' => ['nullable', 'string', 'max:255'],
            'replace_device_id' => ['nullable', 'integer'],
            'referral_code' => ['nullable', 'string', 'max:12'],
        ];
    }

    /** The guest who is upgrading their account, if the app sent their token. */
    private function currentGuest(Request $request): ?User
    {
        $token = $request->bearerToken();
        $user = $token ? PersonalAccessToken::findToken($token)?->tokenable : null;

        return $user instanceof User && $user->is_guest ? $user : null;
    }

    private function signedIn(User $user, string $token, bool $isNew): JsonResponse
    {
        return response()->json([
            'token' => $token,
            'is_new' => $isNew,
            'user' => new UserResource($user->fresh(['university'])),
        ]);
    }

    /** Step 1: send a 6-digit code by SMS. The same step creates new accounts. */
    public function sendOtp(Request $request): JsonResponse
    {
        $data = $request->validate(['phone' => ['required', 'string', 'max:20']]);
        $phone = OtpService::normalizePhone($data['phone']);
        if (! $phone) {
            throw ValidationException::withMessages(['phone' => 'Enter a valid Ethiopian mobile number, e.g. 91 234 5678.']);
        }

        return response()->json(['phone' => $phone, 'masked' => substr($phone, 0, 7).' *** '.substr($phone, -4)] + $this->otp->send($phone, $request->ip()));
    }

    /** Step 2: verify the code and sign in. */
    public function verifyOtp(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:20'],
            'code' => ['required', 'digits:6'],
        ] + $this->deviceRules());

        $phone = OtpService::normalizePhone($data['phone']) ?? throw ValidationException::withMessages(['phone' => 'Invalid phone number.']);
        $otp = $this->otp->verify($phone, $data['code']);

        $existing = User::query()->where('phone', $phone)->first();
        // Check the device limit before using up the code, so the student can pick a device to sign out.
        $this->auth->assertDeviceAllowed($existing, $data);

        $user = $this->auth->userForPhone($phone, $this->currentGuest($request), $data['referral_code'] ?? null);
        $token = $this->auth->issueToken($user, $data);
        $this->otp->consume($otp, $data['code']);

        return $this->signedIn($user, $token, ! $existing);
    }

    /**
     * "Continue with Telegram". The app opens /auth/telegram/redirect?intent=app in a browser;
     * after Telegram confirms, the browser returns to the app with a one-time `ticket`.
     * Apps using Telegram's native login SDK can send the `id_token` instead.
     */
    public function telegram(Request $request, TelegramLogin $telegram): JsonResponse
    {
        $data = $request->validate([
            'ticket' => ['required_without:id_token', 'nullable', 'string', 'max:100'],
            'id_token' => ['required_without:ticket', 'nullable', 'string'],
        ] + $this->deviceRules());

        // Peek first: if the device limit stops this sign-in, the same ticket can be retried.
        $identity = $data['ticket'] ?? null
            ? $telegram->redeemTicket($data['ticket'], consume: false)['identity']
            : $telegram->verifyIdToken($data['id_token']);

        $existing = User::query()->where('telegram_id', $identity['telegram_id'])->first()
            ?? ($identity['phone'] ? User::query()->where('phone', OtpService::normalizePhone($identity['phone']) ?? $identity['phone'])->first() : null);
        $this->auth->assertDeviceAllowed($existing, $data);

        if ($data['ticket'] ?? null) {
            $telegram->redeemTicket($data['ticket']); // now single-use
        }
        $user = $this->auth->userForTelegram($identity, $this->currentGuest($request), $data['referral_code'] ?? null);

        return $this->signedIn($user, $this->auth->issueToken($user, $data), ! $existing);
    }

    public function google(Request $request): JsonResponse
    {
        return $this->socialSignIn($request, 'google');
    }

    public function apple(Request $request): JsonResponse
    {
        return $this->socialSignIn($request, 'apple');
    }

    private function socialSignIn(Request $request, string $provider): JsonResponse
    {
        $data = $request->validate([
            'id_token' => ['required', 'string'],
            // Apple only sends the name on the very first sign-in, so the app passes it along.
            'name' => ['nullable', 'string', 'max:120'],
        ] + $this->deviceRules());

        $identity = $this->social->verify($provider, $data['id_token']);
        $identity['name'] ??= $data['name'] ?? null;

        $existing = User::query()->where($provider.'_id', $identity['sub'])->first()
            ?? ($identity['email'] ? User::query()->where('email', $identity['email'])->first() : null);
        $this->auth->assertDeviceAllowed($existing, $data);

        $user = $this->auth->userForSocial($provider, $identity, $this->currentGuest($request), $data['referral_code'] ?? null);

        return $this->signedIn($user, $this->auth->issueToken($user, $data), ! $existing);
    }

    /** "Browse as guest · 20 free questions". Progress carries over when they sign up. */
    public function guest(Request $request): JsonResponse
    {
        $data = $request->validate($this->deviceRules() + ['app_locale' => ['nullable', Rule::in(Language::activeCodes())]]);

        // Re-use the guest account already created on this device.
        $user = User::query()->where('is_guest', true)
            ->whereHas('devices', fn ($q) => $q->where('device_id', $data['device_id']))
            ->first()
            ?? User::query()->forceCreate([
                'is_guest' => true,
                'app_locale' => $data['app_locale'] ?? 'en',
                'content_locale' => $data['app_locale'] ?? 'en',
            ]);

        return $this->signedIn($user, $this->auth->issueToken($user, $data), $user->wasRecentlyCreated);
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($deviceId = $user->currentDeviceId()) {
            $user->devices()->whereKey($deviceId)->update(['push_token' => null, 'revoked_at' => now()]);
        }
        $token = $user->currentAccessToken();
        $token instanceof PersonalAccessToken && $token->delete();
        Auth::forgetGuards();

        return response()->json(['message' => 'Signed out.']);
    }
}
