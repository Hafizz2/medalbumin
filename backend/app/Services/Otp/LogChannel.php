<?php

namespace App\Services\Otp;

use Illuminate\Support\Facades\Log;

/** Local development: the code is written to storage/logs instead of being sent. */
class LogChannel implements OtpChannel
{
    public function name(): string
    {
        return 'log';
    }

    public function send(string $phone, string $code, int $ttlSeconds): ?string
    {
        Log::info("[Login code for {$phone}] {$code}");

        return null;
    }

    public function reportVerified(?string $requestId, string $code): void {}
}
