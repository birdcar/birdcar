<?php

namespace Tests\Fixtures\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ExampleUnscopedNotification extends Notification
{
    public function __construct(private readonly MailMessage|Mailable $message) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage|Mailable
    {
        return $this->message;
    }
}
