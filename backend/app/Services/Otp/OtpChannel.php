<?php

namespace App\Services\Otp;

interface OtpChannel
{
    /** Short name stored with each code, e.g. "telegram". */
    public function name(): string;

    /**
     * Delivers $code to $phone (E.164). Returns the provider's request id, if any.
     *
     * @throws OtpDeliveryException when the code cannot be delivered (e.g. no Telegram account)
     */
    public function send(string $phone, string $code, int $ttlSeconds): ?string;

    /** Tells the provider which code the user typed (conversion stats). Never throws. */
    public function reportVerified(?string $requestId, string $code): void;
}
