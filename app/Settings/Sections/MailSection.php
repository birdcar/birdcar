<?php

namespace App\Settings\Sections;

use App\Authorization\Mail\Permission as MailPermission;
use App\Settings\Contracts\SettingsSection;
use App\Settings\SettingsGroup;
use BackedEnum;

final class MailSection implements SettingsSection
{
    public function key(): string
    {
        return 'mail';
    }

    public function group(): SettingsGroup
    {
        return SettingsGroup::Application;
    }

    public function label(): string
    {
        return 'Mail';
    }

    public function description(): string
    {
        return 'Choose who each kind of mail comes from. Changes apply to the next email sent.';
    }

    public function icon(): string
    {
        return 'envelope';
    }

    public function permission(): BackedEnum
    {
        return MailPermission::ConfigureSenders;
    }

    public function component(): string
    {
        return 'admin.settings.mail';
    }
}
