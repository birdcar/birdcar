<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('publishing_agents.paused', true);
        $this->migrator->add('publishing_agents.model_overrides', []);
    }

    public function down(): void
    {
        $this->migrator->deleteIfExists('publishing_agents.paused');
        $this->migrator->deleteIfExists('publishing_agents.model_overrides');
    }
};
