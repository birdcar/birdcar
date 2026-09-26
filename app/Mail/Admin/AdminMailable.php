<?php

namespace App\Mail\Admin;

use App\Mail\SurfaceMailable;
use App\Settings\AdminMailSettings;
use App\Settings\SurfaceMailSettings;

abstract class AdminMailable extends SurfaceMailable
{
    public static function surfaceMailer(): string
    {
        return self::configuredMailer('admin.mail.mailer');
    }

    protected function senderSettings(): SurfaceMailSettings
    {
        return app(AdminMailSettings::class);
    }
}
