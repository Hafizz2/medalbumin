<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class VerificationCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $code,
        public readonly string $purpose,
        public readonly int $minutes,
        public readonly ?string $name = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->purpose === 'reset'
            ? "{$this->code} is your MedAlbumin password reset code"
            : "{$this->code} is your MedAlbumin verification code");
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.verification-code');
    }
}
