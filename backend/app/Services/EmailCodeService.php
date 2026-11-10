<?php

namespace App\Services;

use App\Mail\VerificationCodeMail;
use App\Models\EmailCode;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * 6-digit email codes for confirming new accounts and resetting passwords.
 */
class EmailCodeService
{
    public const TTL_MINUTES = 10;

    public const RESEND_SECONDS = 60;

    public const MAX_ATTEMPTS = 5;

    public static function normalize(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    public static function mask(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');

        return mb_substr($local, 0, 2).str_repeat('•', max(1, mb_strlen($local) - 2)).'@'.$domain;
    }

    /**
     * @param  array<string, mixed>|null  $payload  stored encrypted until the code is confirmed
     * @return array{expires_in:int, resend_in:int, masked:string, debug_code?:string}
     */
    public function send(string $email, string $purpose, ?array $payload = null, ?string $name = null, ?string $ip = null): array
    {
        $last = EmailCode::query()->where('email', $email)->where('purpose', $purpose)->latest('id')->first();
        if ($last && $last->created_at->diffInSeconds(now()) < self::RESEND_SECONDS) {
            $wait = self::RESEND_SECONDS - (int) $last->created_at->diffInSeconds(now());
            throw ValidationException::withMessages(['email' => "Please wait {$wait} seconds before requesting a new code."]);
        }

        $review = $this->reviewCode($email);
        $code = $review ?? (string) random_int(100000, 999999);

        if (! $review) {
            try {
                Mail::to($email)->send(new VerificationCodeMail($code, $purpose, self::TTL_MINUTES, $name));
            } catch (Throwable $e) {
                Log::error('Verification email failed', ['email' => $email, 'error' => $e->getMessage()]);
                throw ValidationException::withMessages(['email' => 'We couldn’t send the email right now. Please try again in a minute.']);
            }
        }

        EmailCode::query()->where('email', $email)->where('purpose', $purpose)->whereNull('consumed_at')->update(['consumed_at' => now()]);
        EmailCode::query()->create([
            'email' => $email,
            'purpose' => $purpose,
            'code_hash' => Hash::make($code),
            'payload' => $payload,
            'ip' => $ip,
            'expires_at' => now()->addMinutes(self::TTL_MINUTES),
        ]);

        $out = ['expires_in' => self::TTL_MINUTES * 60, 'resend_in' => self::RESEND_SECONDS, 'masked' => self::mask($email)];
        // Local development without a mail server: show the code in the app.
        if (config('app.debug') && in_array(config('mail.default'), ['log', 'array'], true)) {
            $out['debug_code'] = $code;
        }

        return $out;
    }

    /** Checks the code without consuming it; call consume() once the action succeeds. */
    public function verify(string $email, string $purpose, string $code): EmailCode
    {
        $row = EmailCode::query()
            ->where('email', $email)
            ->where('purpose', $purpose)
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->latest('id')
            ->first();

        if (! $row) {
            throw ValidationException::withMessages(['code' => 'This code has expired. Request a new one.']);
        }
        if ($row->attempts >= self::MAX_ATTEMPTS) {
            $row->update(['consumed_at' => now()]);
            throw ValidationException::withMessages(['code' => 'Too many wrong attempts. Request a new code.']);
        }
        if (! Hash::check($code, $row->code_hash)) {
            $row->increment('attempts');
            throw ValidationException::withMessages(['code' => 'The code is not correct.']);
        }

        return $row;
    }

    /** The newest pending code, used to resend a sign-up without asking for the details again. */
    public function pending(string $email, string $purpose): ?EmailCode
    {
        return EmailCode::query()->where('email', $email)->where('purpose', $purpose)->whereNull('consumed_at')->latest('id')->first();
    }

    public function consume(EmailCode $row): void
    {
        $row->update(['consumed_at' => now()]);
    }

    /** App-store reviewers get a fixed code for one configured address. */
    private function reviewCode(string $email): ?string
    {
        $address = config('services.otp.review_email');
        $code = config('services.otp.review_code');

        return $address && $code && $email === self::normalize($address) ? (string) $code : null;
    }
}
