<?php

namespace App\Settings\Sections;

use App\Settings\Contracts\SettingsSection;
use App\Settings\SettingsGroup;
use BackedEnum;

final class ProfileSection implements SettingsSection
{
    public function key(): string
    {
        return 'profile';
    }

    public function group(): SettingsGroup
    {
        return SettingsGroup::Account;
    }

    public function label(): string
    {
        return 'Profile';
    }

    public function description(): string
    {
        return 'Your name and the email address you sign in with.';
    }

    public function icon(): string
    {
        return 'user';
    }

    public function permission(): ?BackedEnum
    {
        return null;
    }

    public function component(): string
    {
        return 'admin.settings.account.profile';
    }
}
