<?php

namespace Tests\Feature\Admin;

use App\Settings\BrandingSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BrandingSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_app_name_defaults_to_marketix_and_is_overridable(): void
    {
        $settings = app(BrandingSettings::class);
        $this->assertSame('Marketix', $settings->appName());

        $settings->app_name = 'Acme Links';
        $settings->save();

        $this->assertSame('Acme Links', app(BrandingSettings::class)->appName());
    }

    public function test_url_accessors_are_null_until_paths_set_then_resolve_via_default_disk(): void
    {
        Storage::fake();

        $settings = app(BrandingSettings::class);
        $this->assertNull($settings->faviconUrl());
        $this->assertNull($settings->emailLogoUrl());

        $settings->favicon_path = 'branding/favicon.png';
        $settings->logo_email_path = 'branding/email.png';
        $settings->save();

        $fresh = app(BrandingSettings::class);
        $this->assertStringContainsString('branding/favicon.png', $fresh->faviconUrl());
        $this->assertStringContainsString('branding/email.png', $fresh->emailLogoUrl());
    }
}
