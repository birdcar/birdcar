<?php

namespace Tests\Fixtures\Mail;

use App\Mail\SurfaceMailable;
use Illuminate\Notifications\Notification;
use LogicException;

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
        if (! method_exists($notifiable, 'routeNotificationFor')) {
            throw new LogicException('Notifiable ['.$notifiable::class.'] cannot route mail notifications.');
        }

        return $this->mailable->to($notifiable->routeNotificationFor('mail', $this));
    }
}
