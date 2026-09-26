<?php

namespace Tests\Fixtures\Mail;

use App\Mail\SurfaceMailable;
use Illuminate\Notifications\Notification;

class ExampleSurfaceNotification extends Notification
{
    public function __construct(private readonly SurfaceMailable $mailable) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): SurfaceMailable
    {
        return $this->mailable->to($notifiable->routeNotificationFor('mail', $this));
    }
}
