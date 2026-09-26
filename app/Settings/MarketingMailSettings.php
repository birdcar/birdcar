<?php

namespace App\Settings;

class MarketingMailSettings extends SurfaceMailSettings
{
    public static function group(): string
    {
        return 'marketing_mail';
    }

    /**
     * @return list<string>
     */
    public static function allowedSenderDomains(): array
    {
        return self::configuredSenderDomains('marketing.mail.sender_domains');
    }
}
