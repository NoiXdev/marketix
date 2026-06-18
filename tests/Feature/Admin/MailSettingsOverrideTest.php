<?php

namespace Tests\Feature\Admin;

use App\Providers\MailSettingsServiceProvider;
use App\Settings\MailSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MailSettingsOverrideTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_mailer_from_settings_overrides_config(): void
    {
        $settings = app(MailSettings::class);
        $settings->default_mailer = 'smtp';
        $settings->smtp_host = 'mail.example.test';
        $settings->save();

        // Re-run the provider boot now that settings exist.
        (new MailSettingsServiceProvider($this->app))->boot();

        $this->assertSame('smtp', config('mail.default'));
        $this->assertSame('mail.example.test', config('mail.mailers.smtp.host'));
    }
}
