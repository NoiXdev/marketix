<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('storage.driver', 'local');
        $this->migrator->add('storage.s3_key', (string) config('filesystems.disks.s3.key', ''));
        $this->migrator->addEncrypted('storage.s3_secret', (string) config('filesystems.disks.s3.secret', ''));
        $this->migrator->add('storage.s3_region', (string) config('filesystems.disks.s3.region', ''));
        $this->migrator->add('storage.s3_bucket', (string) config('filesystems.disks.s3.bucket', ''));
        $this->migrator->add('storage.s3_endpoint', (string) config('filesystems.disks.s3.endpoint', ''));
        $this->migrator->add('storage.s3_use_path_style', (bool) config('filesystems.disks.s3.use_path_style_endpoint', false));
    }
};
