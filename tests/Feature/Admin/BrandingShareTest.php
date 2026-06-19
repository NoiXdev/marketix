<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Settings\BrandingSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class BrandingShareTest extends TestCase
{
    use RefreshDatabase;

    public function test_branding_is_shared_with_every_page(): void
    {
        $settings = app(BrandingSettings::class);
        $settings->app_name = 'Acme Links';
        $settings->save();

        $user = User::factory()->create();
        $user->super_admin = true;
        $user->save();

        $this->actingAs($user)
            ->get(route('app.admin.branding.edit'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('branding.appName', 'Acme Links')
                ->where('branding.logoLight', null)
                ->where('branding.logoDark', null)
                ->where('branding.favicon', null));
    }
}
