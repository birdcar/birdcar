<?php

namespace App\Settings;

class AdminMailSettings extends SurfaceMailSettings
{
    public static function group(): string
    {
        return 'admin_mail';
    }

    /**
     * @return list<string>
     */
    public static function allowedSenderDomains(): array
    {
        return self::configuredSenderDomains('admin.mail.sender_domains');
    }
}
