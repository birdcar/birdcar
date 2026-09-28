<?php

namespace App\Settings\Sections;

use App\Authorization\Publishing\Permission as PublishingPermission;
use App\Settings\Contracts\SettingsSection;
use App\Settings\SettingsGroup;
use BackedEnum;

final class PublishingSection implements SettingsSection
{
    public function key(): string
    {
        return 'publishing';
    }

    public function group(): SettingsGroup
    {
        return SettingsGroup::Application;
    }

    public function label(): string
    {
        return 'Publishing';
    }

    public function description(): string
    {
        return 'Pause agent requests or pin a model for one task. Changes apply to work that hasn\'t started yet.';
    }

    public function icon(): string
    {
        return 'document-text';
    }

    public function permission(): BackedEnum
    {
        return PublishingPermission::ConfigureAgents;
    }

    public function component(): string
    {
        return 'admin.settings.publishing';
    }
}
