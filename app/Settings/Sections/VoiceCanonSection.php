<?php

namespace App\Settings\Sections;

use App\Authorization\Publishing\Permission as PublishingPermission;
use App\Settings\Contracts\SettingsSection;
use App\Settings\SettingsGroup;
use BackedEnum;

final class VoiceCanonSection implements SettingsSection
{
    public function key(): string
    {
        return 'voice-canon';
    }

    public function group(): SettingsGroup
    {
        return SettingsGroup::Application;
    }

    public function label(): string
    {
        return 'Voice canon';
    }

    public function description(): string
    {
        return 'Edit the voice rules and rubrics every agent task receives. A save applies to work that starts afterward; work already started keeps its version.';
    }

    public function icon(): string
    {
        return 'book-open';
    }

    public function permission(): BackedEnum
    {
        return PublishingPermission::ConfigureAgents;
    }

    public function component(): string
    {
        return 'admin.settings.voice-canon';
    }
}
