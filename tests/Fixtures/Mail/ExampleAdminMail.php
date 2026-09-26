<?php

namespace Tests\Fixtures\Mail;

use App\Mail\Admin\AdminMailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class ExampleAdminMail extends AdminMailable
{
    public function __construct(private readonly ?Address $envelopeFrom = null) {}

    public function envelope(): Envelope
    {
        return new Envelope(from: $this->envelopeFrom, subject: 'Example admin mail');
    }

    public function content(): Content
    {
        return new Content(htmlString: '<p>Example admin mail.</p>');
    }
}
