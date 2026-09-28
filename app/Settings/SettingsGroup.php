<?php

namespace App\Settings;

enum SettingsGroup: string
{
    case Application = 'application';
    case Account = 'account';

    public function label(): string
    {
        return match ($this) {
            self::Application => 'Application',
            self::Account => 'Your account',
        };
    }
}
