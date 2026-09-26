<?php

namespace App\Mail\Admin;

use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class InvitationMail extends AdminMailable
{
    public function __construct(
        #[\SensitiveParameter]
        public readonly string $setupUrl,
        public readonly int $expiresInMinutes,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Set up your Birdcar Admin access',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.admin.invitation',
        );
    }
}
