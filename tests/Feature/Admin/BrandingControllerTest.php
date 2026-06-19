<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Settings\BrandingSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class BrandingControllerTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        $u = User::factory()->create();
        $u->super_admin = true;
        $u->save();

        return $u;
    }

    public function test_non_super_admin_is_forbidden(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('app.admin.branding.edit'))
            ->assertForbidden();
    }

    public function test_edit_page_renders_with_resolved_urls(): void
    {
        Storage::fake();
        $settings = app(BrandingSettings::class);
        $settings->app_name = 'Acme Links';
        $settings->favicon_path = 'branding/favicon.png';
        $settings->save();

        $this->actingAs($this->superAdmin())
            ->get(route('app.admin.branding.edit'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Branding/Edit')
                ->where('app_name', 'Acme Links')
                ->where('logo_light_url', null)
                ->where('favicon_url', fn ($url) => str_contains($url ?? '', 'branding/favicon.png')));
    }

    public function test_update_persists_app_name(): void
    {
        $this->actingAs($this->superAdmin())
            ->post(route('app.admin.branding.update'), ['app_name' => 'Acme Links'])
            ->assertRedirect(route('app.admin.branding.edit'));

        $this->assertSame('Acme Links', app(BrandingSettings::class)->appName());
    }

    public function test_uploading_logo_stores_file_and_saves_path(): void
    {
        Storage::fake();

        $this->actingAs($this->superAdmin())
            ->post(route('app.admin.branding.update'), [
                'app_name' => 'Acme',
                'logo_light' => UploadedFile::fake()->image('logo.png', 200, 60),
            ])
            ->assertRedirect();

        $path = app(BrandingSettings::class)->logo_light_path;
        $this->assertNotNull($path);
        Storage::disk()->assertExists($path);
    }

    public function test_remove_flag_clears_path_and_deletes_file(): void
    {
        Storage::fake();
        Storage::disk()->put('branding/old.png', 'x');
        $settings = app(BrandingSettings::class);
        $settings->logo_light_path = 'branding/old.png';
        $settings->save();

        $this->actingAs($this->superAdmin())
            ->post(route('app.admin.branding.update'), [
                'app_name' => 'Acme',
                'remove_logo_light' => '1',
            ])
            ->assertRedirect();

        $this->assertNull(app(BrandingSettings::class)->logo_light_path);
        Storage::disk()->assertMissing('branding/old.png');
    }

    public function test_invalid_logo_upload_is_rejected(): void
    {
        $this->actingAs($this->superAdmin())
            ->post(route('app.admin.branding.update'), [
                'app_name' => 'Acme',
                'logo_light' => UploadedFile::fake()->create('not-an-image.pdf', 10, 'application/pdf'),
            ])
            ->assertSessionHasErrors('logo_light');
    }
}
