<?php

namespace Tests\Feature\Admin;

use App\Settings\BrandingSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BrandingHeadTest extends TestCase
{
    use RefreshDatabase;

    public function test_custom_app_name_drives_config_and_head_title(): void
    {
        $settings = app(BrandingSettings::class);
        $settings->app_name = 'Acme Links';
        $settings->save();

        // Re-apply now that the value is saved (provider runs at boot, before save).
        (new \App\Providers\BrandingServiceProvider($this->app))->bootForTesting();

        $this->assertSame('Acme Links', config('app.name'));

        $this->get(route('app.auth.show-login'))
            ->assertOk()
            ->assertSee('Acme Links', false)            // <title> / meta app-name
            ->assertSee('name="app-name"', false);
    }
}
