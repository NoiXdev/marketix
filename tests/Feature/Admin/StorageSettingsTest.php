<?php

namespace Tests\Feature\Admin;

use App\Providers\StorageSettingsServiceProvider;
use App\Settings\StorageSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StorageSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_settings_are_seeded_from_config(): void
    {
        $settings = app(StorageSettings::class);

        $this->assertContains($settings->driver, ['local', 's3']);
        $this->assertIsBool($settings->s3_use_path_style);
        $this->assertIsString($settings->s3_bucket);
    }

    public function test_secret_is_encrypted_at_rest(): void
    {
        $settings = app(StorageSettings::class);
        $settings->s3_secret = 'super-secret-value';
        $settings->save();

        $raw = DB::table('settings')
            ->where('group', 'storage')
            ->where('name', 's3_secret')
            ->value('payload');

        $this->assertStringNotContainsString('super-secret-value', (string) $raw);
    }

    public function test_s3_driver_overrides_filesystem_config(): void
    {
        $settings = app(StorageSettings::class);
        $settings->driver = 's3';
        $settings->s3_key = 'KEY';
        $settings->s3_secret = 'SECRET';
        $settings->s3_region = 'eu-central-1';
        $settings->s3_bucket = 'my-bucket';
        $settings->s3_endpoint = 'https://r2.example.com';
        $settings->s3_use_path_style = true;
        $settings->save();

        (new StorageSettingsServiceProvider($this->app))->apply();

        $this->assertSame('s3', config('filesystems.default'));
        $this->assertSame('my-bucket', config('filesystems.disks.s3.bucket'));
        $this->assertSame('https://r2.example.com', config('filesystems.disks.s3.endpoint'));
        $this->assertTrue(config('filesystems.disks.s3.use_path_style_endpoint'));
    }

    public function test_local_driver_leaves_default_unchanged(): void
    {
        config(['filesystems.default' => 'local']);

        $settings = app(StorageSettings::class);
        $settings->driver = 'local';
        $settings->save();

        (new StorageSettingsServiceProvider($this->app))->apply();

        $this->assertSame('local', config('filesystems.default'));
    }
}
