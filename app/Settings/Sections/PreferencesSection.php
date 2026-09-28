<?php

namespace App\Settings\Sections;

use App\Settings\Contracts\SettingsSection;
use App\Settings\SettingsGroup;
use BackedEnum;

final class PreferencesSection implements SettingsSection
{
    public function key(): string
    {
        return 'preferences';
    }

    public function group(): SettingsGroup
    {
        return SettingsGroup::Account;
    }

    public function label(): string
    {
        return 'Preferences';
    }

    public function description(): string
    {
        return 'How Admin looks on this device, and the timezone it uses for you.';
    }

    public function icon(): string
    {
        return 'adjustments-horizontal';
    }

    public function permission(): ?BackedEnum
    {
        return null;
    }

    public function component(): string
    {
        return 'admin.settings.account.preferences';
    }
}
