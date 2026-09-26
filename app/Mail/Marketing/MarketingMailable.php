<?php

namespace App\Mail\Marketing;

use App\Mail\SurfaceMailable;
use App\Settings\MarketingMailSettings;
use App\Settings\SurfaceMailSettings;

abstract class MarketingMailable extends SurfaceMailable
{
    public static function surfaceMailer(): string
    {
        return self::configuredMailer('marketing.mail.mailer');
    }

    protected function senderSettings(): SurfaceMailSettings
    {
        return app(MarketingMailSettings::class);
    }
}
