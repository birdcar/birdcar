<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('admin_mail.from_name', 'Birdcar');
        $this->migrator->add('admin_mail.from_address', 'noreply@admin.birdcar.dev');
        $this->migrator->add('admin_mail.reply_to', null);
        $this->migrator->add('marketing_mail.from_name', 'Birdcar');
        $this->migrator->add('marketing_mail.from_address', 'hello@birdcar.dev');
        $this->migrator->add('marketing_mail.reply_to', null);
    }

    public function down(): void
    {
        $this->migrator->deleteIfExists('admin_mail.from_name');
        $this->migrator->deleteIfExists('admin_mail.from_address');
        $this->migrator->deleteIfExists('admin_mail.reply_to');
        $this->migrator->deleteIfExists('marketing_mail.from_name');
        $this->migrator->deleteIfExists('marketing_mail.from_address');
        $this->migrator->deleteIfExists('marketing_mail.reply_to');
    }
};
