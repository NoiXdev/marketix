<?php

namespace Tests\Feature\Admin;

use App\Settings\MailSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MailSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_settings_are_seeded_from_config(): void
    {
        $settings = app(MailSettings::class);

        $this->assertSame(config('mail.from.address'), $settings->from_address);
        $this->assertSame(config('mail.default'), $settings->default_mailer);
    }

    public function test_secret_properties_are_encrypted_at_rest(): void
    {
        $settings = app(MailSettings::class);
        $settings->postal_key = 'super-secret-key';
        $settings->save();

        $raw = DB::table('settings')
            ->where('group', 'mail')
            ->where('name', 'postal_key')
            ->value('payload');

        $this->assertStringNotContainsString('super-secret-key', (string) $raw);
    }
}
