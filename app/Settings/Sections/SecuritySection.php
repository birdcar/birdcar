<?php

namespace App\Settings\Sections;

use App\Settings\Contracts\SettingsSection;
use App\Settings\SettingsGroup;
use BackedEnum;

final class SecuritySection implements SettingsSection
{
    public function key(): string
    {
        return 'security';
    }

    public function group(): SettingsGroup
    {
        return SettingsGroup::Account;
    }

    public function label(): string
    {
        return 'Security';
    }

    public function description(): string
    {
        return 'Your password, two-factor authentication and passkeys.';
    }

    public function icon(): string
    {
        return 'lock-closed';
    }

    public function permission(): ?BackedEnum
    {
        return null;
    }

    public function component(): string
    {
        return 'admin.settings.account.security';
    }
}
