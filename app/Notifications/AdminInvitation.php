<?php

namespace App\Notifications;

use App\Mail\Admin\InvitationMail;
use App\Notifications\Admin\BuildsAdminPasswordLinks;
use Illuminate\Notifications\Notification;

class AdminInvitation extends Notification
{
    use BuildsAdminPasswordLinks;

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

    public function toMail(object $notifiable): InvitationMail
    {
        return (new InvitationMail(
            $this->adminPasswordLink($notifiable, $this->adminUrl, $this->token),
            $this->adminPasswordLinkExpiresInMinutes(),
        ))->to($this->adminPasswordMailRecipient($notifiable));
    }
}
