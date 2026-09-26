<?php

namespace Tests\Fixtures\Mail;

use App\Mail\Marketing\MarketingMailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class ExampleMarketingMail extends MarketingMailable
{
    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Example marketing mail');
    }

    public function content(): Content
    {
        return new Content(htmlString: '<p>Example marketing mail.</p>');
    }
}
