<?php

namespace Tests\Feature\Admin;

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
}
