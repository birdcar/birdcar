<?php

namespace App\Mail\Admin;

use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class PasswordResetMail extends AdminMailable
{
    public function __construct(
        #[\SensitiveParameter]
        public readonly string $resetUrl,
        public readonly int $expiresInMinutes,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Reset your Birdcar Admin password',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.admin.password-reset',
        );
    }
}
