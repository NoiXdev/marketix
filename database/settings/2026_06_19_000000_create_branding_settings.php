<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('branding.app_name', null);
        $this->migrator->add('branding.logo_light_path', null);
        $this->migrator->add('branding.logo_dark_path', null);
        $this->migrator->add('branding.logo_email_path', null);
        $this->migrator->add('branding.favicon_path', null);
    }
};
