<?php

namespace App\Notifications\Admin;

use App\Mail\Admin\PasswordResetMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Notification;

/**
 * Password resets use the admin surface until customer auth exists, because Fortify only serves the admin host.
 */
class PasswordReset extends Notification implements ShouldQueueAfterCommit
{
    use BuildsAdminPasswordLinks, Queueable;

    public function __construct(
        #[\SensitiveParameter]
        public string $token,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): PasswordResetMail
    {
        return (new PasswordResetMail(
            $this->adminPasswordLink($notifiable, (string) config('admin.url'), $this->token),
            $this->adminPasswordLinkExpiresInMinutes(),
        ))->to($this->adminPasswordMailRecipient($notifiable));
    }
}
