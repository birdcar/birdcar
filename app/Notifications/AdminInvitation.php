<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Lang;

class AdminInvitation extends Notification
{
    public function __construct(
        #[\SensitiveParameter]
        public string $token,
        private readonly string $adminUrl,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Set up your Birdcar Admin access')
            ->greeting('You have been invited to Birdcar Admin')
            ->line('An operator invited this account to the root Admin role bundle, including Admin access and publishing author capabilities.')
            ->line('Use the secure setup link below to set a password for this account.')
            ->action('Set Admin password', $this->resetUrl($notifiable))
            ->line(Lang::get('This password setup link will expire in :count minutes.', ['count' => $this->expiresInMinutes()]))
            ->line('If this account already has a password, it remains usable until you change it. Existing two-factor authentication and other access are not removed by this invitation.');
    }

    private function resetUrl(object $notifiable): string
    {
        if (! method_exists($notifiable, 'getEmailForPasswordReset')) {
            throw new \InvalidArgumentException('Admin invitations require a password-reset-capable notifiable.');
        }

        return rtrim($this->adminUrl, '/').route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false);
    }

    private function expiresInMinutes(): int
    {
        $broker = config('fortify.passwords');
        $expires = is_string($broker) ? config("auth.passwords.{$broker}.expire") : null;

        return is_numeric($expires) ? (int) $expires : 60;
    }
}
