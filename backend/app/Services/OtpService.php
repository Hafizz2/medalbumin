<?php

namespace App\Services;

use App\Models\OtpCode;
use App\Services\Otp\OtpChannel;
use App\Services\Otp\OtpDeliveryException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * One-time login codes, delivered through Telegram (Telegram Gateway).
 */
class OtpService
{
    public const TTL_MINUTES = 5;

    public const RESEND_SECONDS = 60;

    public const MAX_ATTEMPTS = 5;

    public function __construct(private readonly OtpChannel $channel) {}

    /** Normalises Ethiopian numbers to +2519XXXXXXXX / +2517XXXXXXXX. */
    public static function normalizePhone(string $raw): ?string
    {
        $digits = preg_replace('/\D+/', '', $raw);
        if (str_starts_with($digits, '251')) {
            $digits = substr($digits, 3);
        }
        $digits = ltrim($digits, '0');

        return preg_match('/^[79]\d{8}$/', $digits) ? '+251'.$digits : null;
    }

    /**
     * @return array{channel:string, expires_in:int, resend_in:int, debug_code?:string}
     */
    public function send(string $phone, ?string $ip = null): array
    {
        $last = OtpCode::query()->where('phone', $phone)->latest('id')->first();
        if ($last && $last->created_at->diffInSeconds(now()) < self::RESEND_SECONDS) {
            $wait = self::RESEND_SECONDS - (int) $last->created_at->diffInSeconds(now());
            throw ValidationException::withMessages(['phone' => "Please wait {$wait} seconds before requesting a new code."]);
        }

        $review = $this->fixedTestCode($phone);
        $code = $review ?? (string) random_int(100000, 999999);

        // Reviewer accounts never trigger a real message.
        $requestId = null;
        if (! $review) {
            try {
                $requestId = $this->channel->send($phone, $code, self::TTL_MINUTES * 60);
            } catch (OtpDeliveryException $e) {
                throw ValidationException::withMessages(['phone' => $e->getMessage()]);
            }
        }

        OtpCode::query()->where('phone', $phone)->whereNull('consumed_at')->update(['consumed_at' => now()]);
        OtpCode::query()->create([
            'phone' => $phone,
            'channel' => $review ? 'review' : $this->channel->name(),
            'provider_request_id' => $requestId,
            'code_hash' => Hash::make($code),
            'ip' => $ip,
            'expires_at' => now()->addMinutes(self::TTL_MINUTES),
        ]);

        $out = ['channel' => $this->channel->name(), 'expires_in' => self::TTL_MINUTES * 60, 'resend_in' => self::RESEND_SECONDS];
        if (config('app.debug') && $this->channel->name() === 'log') {
            $out['debug_code'] = $code;
        }

        return $out;
    }

    /** Checks the code without consuming it; call consume() once sign-in succeeds. */
    public function verify(string $phone, string $code): OtpCode
    {
        $otp = OtpCode::query()
            ->where('phone', $phone)
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->latest('id')
            ->first();

        if (! $otp) {
            throw ValidationException::withMessages(['code' => 'This code has expired. Request a new one.']);
        }
        if ($otp->attempts >= self::MAX_ATTEMPTS) {
            $otp->update(['consumed_at' => now()]);
            throw ValidationException::withMessages(['code' => 'Too many wrong attempts. Request a new code.']);
        }
        if (! Hash::check($code, $otp->code_hash)) {
            $otp->increment('attempts');
            throw ValidationException::withMessages(['code' => 'The code is not correct.']);
        }

        return $otp;
    }

    public function consume(OtpCode $otp, ?string $code = null): void
    {
        $otp->update(['consumed_at' => now()]);
        if ($code !== null) {
            $this->channel->reportVerified($otp->provider_request_id, $code);
        }
    }

    /**
     * App-store reviewers need a working login without a real Telegram account.
     * One review phone number + fixed code can be configured in .env.
     */
    private function fixedTestCode(string $phone): ?string
    {
        $reviewPhone = config('services.otp.review_phone');
        $reviewCode = config('services.otp.review_code');

        return $reviewPhone && $reviewCode && $phone === $reviewPhone ? (string) $reviewCode : null;
    }
}
