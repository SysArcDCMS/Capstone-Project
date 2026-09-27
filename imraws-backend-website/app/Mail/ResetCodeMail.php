<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Emailed password-reset code (capstone DFD 1.9).
 *
 * Sent synchronously via Mail::send(). It does not implement ShouldQueue, so
 * the caller must switch to Mail::queue()/Mailable::queue() — and run a queue
 * worker — if a slow SMTP handshake should be kept off the request.
 */
class ResetCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $code,
        public readonly int $expiresInMinutes,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your IMRAWS password reset code');
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.reset-code',
            with: [
                'code'                => $this->code,
                'expiresInMinutes'    => $this->expiresInMinutes,
            ],
        );
    }
}
