<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('operator.agency_name', null);
        $this->migrator->add('operator.agency_address', null);
        $this->migrator->add('operator.hosting_provider', null);
        $this->migrator->add('operator.hosting_location', null);
    }
};
