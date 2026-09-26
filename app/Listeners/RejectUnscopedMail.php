<?php

namespace App\Listeners;

use App\Mail\SurfaceMailable;
use Illuminate\Mail\Events\MessageSending;
use LogicException;

/**
 * Blocks every real send that is not a surface Mailable on its own surface mailer, in every environment.
 */
class RejectUnscopedMail
{
    /**
     * Throws instead of returning false, because a false return from a MessageSending listener cancels the send silently.
     *
     * @throws LogicException when the message is not a surface Mailable, or is sent on a mailer other than its surface's.
     */
    public function handle(MessageSending $event): void
    {
        $mailable = $event->data['__laravel_mailable'] ?? null;
        $mailer = $event->data['mailer'] ?? null;

        if (! is_string($mailable) || ! is_subclass_of($mailable, SurfaceMailable::class)) {
            throw new LogicException("{$this->describe($event->data)} was blocked: all mail must be a surface Mailable returned from a notification's toMail().");
        }

        $expected = $mailable::surfaceMailer();

        if ($mailer !== $expected) {
            $actual = is_string($mailer) ? $mailer : 'unknown';

            throw new LogicException("[{$mailable}] must be sent on mailer [{$expected}], not [{$actual}].");
        }
    }

    /**
     * Names what was sent: the Mailable class, else the notification class, else a raw message.
     *
     * @param  array<array-key, mixed>  $data
     */
    private function describe(array $data): string
    {
        $mailable = $data['__laravel_mailable'] ?? null;

        if (is_string($mailable)) {
            return "Mailable [{$mailable}]";
        }

        $notification = $data['__laravel_notification'] ?? null;

        if (is_string($notification)) {
            return "Notification [{$notification}]";
        }

        return 'A raw message';
    }
}
