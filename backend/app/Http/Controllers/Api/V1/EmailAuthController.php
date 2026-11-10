<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\AuthService;
use App\Services\EmailCodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Email accounts: sign up → 6-digit code by email → verified account.
 * Then sign in with email + password; forgotten passwords are reset with an email code.
 */
class EmailAuthController extends Controller
{
    public function __construct(
        private readonly EmailCodeService $codes,
        private readonly AuthService $auth,
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

    private function passwordRule(): Password
    {
        return Password::min(8)->letters()->numbers();
    }

    private function emailOf(Request $request): string
    {
        return EmailCodeService::normalize((string) $request->input('email'));
    }

    private function signedIn(User $user, string $token, bool $isNew): JsonResponse
    {
        return response()->json(['token' => $token, 'is_new' => $isNew, 'user' => new UserResource($user->fresh(['university']))]);
    }

    private function currentGuest(Request $request): ?User
    {
        $token = $request->bearerToken();
        $user = $token ? PersonalAccessToken::findToken($token)?->tokenable : null;

        return $user instanceof User && $user->is_guest ? $user : null;
    }

    /** Step 1 of sign-up: name, email and password. Nothing is created until the email is confirmed. */
    public function register(Request $request): JsonResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'string', 'email:rfc', 'max:190'],
            'password' => ['required', 'string', 'max:100', $this->passwordRule()],
        ]);
        $email = $this->emailOf($request);

        if (User::withTrashed()->where('email', $email)->exists()) {
            throw ValidationException::withMessages(['email' => 'An account with this email already exists. Sign in instead.']);
        }

        $name = trim((string) $request->input('name'));

        return response()->json($this->codes->send($email, 'signup', [
            'name' => $name,
            'password' => Hash::make((string) $request->input('password')),
        ], $name, $request->ip()));
    }

    /** Step 2 of sign-up: the code from the email creates the account and signs in. */
    public function verify(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email'], 'code' => ['required', 'digits:6']] + $this->deviceRules());
        $email = $this->emailOf($request);
        $row = $this->codes->verify($email, 'signup', $data['code']);

        if (User::withTrashed()->where('email', $email)->exists()) {
            $this->codes->consume($row);
            throw ValidationException::withMessages(['email' => 'An account with this email already exists. Sign in instead.']);
        }

        $payload = $row->payload ?? [];
        $attrs = [
            'name' => $payload['name'] ?? null,
            'email' => $email,
            'email_verified_at' => now(),
            'password' => $payload['password'] ?? null,
        ];

        $guest = $this->currentGuest($request);
        if ($guest) {
            $guest->forceFill($attrs + ['is_guest' => false])->save();
            $user = $guest;
        } else {
            $user = User::query()->forceCreate($attrs);
            $this->auth->attachReferral($user, $data['referral_code'] ?? null);
        }

        $token = $this->auth->issueToken($user, $data);
        $this->codes->consume($row);

        return $this->signedIn($user, $token, true);
    }

    /** Sends the sign-up or reset code again. */
    public function resend(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email'], 'purpose' => ['required', 'in:signup,reset']]);
        $email = $this->emailOf($request);

        if ($data['purpose'] === 'signup') {
            $pending = $this->codes->pending($email, 'signup');
            if (! $pending) {
                throw ValidationException::withMessages(['email' => 'Start the sign-up again.']);
            }

            return response()->json($this->codes->send($email, 'signup', $pending->payload, $pending->payload['name'] ?? null, $request->ip()));
        }

        return $this->forgot($request);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string', 'max:100']] + $this->deviceRules());
        $email = $this->emailOf($request);
        $user = User::query()->where('email', $email)->where('is_staff', false)->first();

        if (! $user || ! $user->password || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['email' => 'Email or password is incorrect.']);
        }
        if (! $user->email_verified_at) {
            throw ValidationException::withMessages(['email' => 'Please confirm your email first.']);
        }

        $this->auth->assertDeviceAllowed($user, $data);

        return $this->signedIn($user, $this->auth->issueToken($user, $data), false);
    }

    /** Always answers the same way, so nobody can check which emails have accounts. */
    public function forgot(Request $request): JsonResponse
    {
        $request->validate(['email' => ['required', 'email']]);
        $email = $this->emailOf($request);
        $user = User::query()->where('email', $email)->whereNotNull('email_verified_at')->where('is_staff', false)->first();

        $out = ['expires_in' => EmailCodeService::TTL_MINUTES * 60, 'resend_in' => EmailCodeService::RESEND_SECONDS, 'masked' => EmailCodeService::mask($email)];
        if ($user) {
            $out = $this->codes->send($email, 'reset', null, $user->name, $request->ip());
        }

        return response()->json($out);
    }

    public function reset(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'code' => ['required', 'digits:6'],
            'password' => ['required', 'string', 'max:100', $this->passwordRule()],
        ] + $this->deviceRules());
        $email = $this->emailOf($request);
        $row = $this->codes->verify($email, 'reset', $data['code']);

        $user = User::query()->where('email', $email)->where('is_staff', false)->firstOrFail();
        $this->auth->assertDeviceAllowed($user, $data);

        $user->forceFill(['password' => $data['password']])->save();
        $this->codes->consume($row);

        return $this->signedIn($user, $this->auth->issueToken($user, $data), false);
    }
}
