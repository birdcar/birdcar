<?php

use App\Authorization\Admin\Role as AdminRole;
use App\Authorization\Mail\Role as MailRole;
use App\Authorization\Publishing\Role as PublishingRole;
use App\Settings\Sections\MailSection;
use App\Settings\Sections\PreferencesSection;
use App\Settings\Sections\ProfileSection;
use App\Settings\Sections\PublishingSection;
use App\Settings\Sections\SecuritySection;

$configuredUrl = env('BIRDCAR_ADMIN_URL', 'https://admin.birdcar.dev');
$url = is_string($configuredUrl) && $configuredUrl !== '' ? $configuredUrl : 'https://admin.birdcar.dev';
$host = parse_url($url, PHP_URL_HOST) ?: 'admin.birdcar.dev';

return [
    'url' => rtrim($url, '/'),
    'host' => $host,
    'bootstrap_roles' => [
        AdminRole::Access->value,
        PublishingRole::Author->value,
        MailRole::Operator->value,
    ],
    'mail' => [
        'mailer' => env('BIRDCAR_ADMIN_MAIL_MAILER', 'log'),
        'sender_domains' => ['admin.birdcar.dev', 'birdcar.dev'],
    ],
    'settings' => [
        /*
         * Settings sections in rail order. Each class implements App\Settings\Contracts\SettingsSection;
         * routes, the rail and the landing page are derived from this list.
         */
        'sections' => [
            MailSection::class,
            PublishingSection::class,
            ProfileSection::class,
            SecuritySection::class,
            PreferencesSection::class,
        ],
    ],
];
