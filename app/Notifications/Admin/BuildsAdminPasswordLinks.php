<?php

namespace App\Notifications\Admin;

use InvalidArgumentException;

/**
 * Admin password links are built from the admin origin plus a relative route, never url(): a queue worker has no request host.
 */
trait BuildsAdminPasswordLinks
{
    /**
     * @throws InvalidArgumentException when the notifiable cannot receive a password link.
     */
    protected function adminPasswordLink(object $notifiable, string $adminUrl, #[\SensitiveParameter] string $token): string
    {
        if (! method_exists($notifiable, 'getEmailForPasswordReset')) {
            throw new InvalidArgumentException('Admin password links require a password-reset-capable notifiable.');
        }

        return rtrim($adminUrl, '/').route('password.reset', [
            'token' => $token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false);
    }

    protected function adminPasswordLinkExpiresInMinutes(): int
    {
        $broker = config('fortify.passwords');
        $expires = is_string($broker) ? config("auth.passwords.{$broker}.expire") : null;

        return is_numeric($expires) ? (int) $expires : 60;
    }

    /**
     * @throws InvalidArgumentException when the notifiable has no mail route.
     */
    protected function adminPasswordMailRecipient(object $notifiable): mixed
    {
        if (! method_exists($notifiable, 'routeNotificationFor')) {
            throw new InvalidArgumentException('Admin password links require a mail-routable notifiable.');
        }

        return $notifiable->routeNotificationFor('mail', $this);
    }
}
