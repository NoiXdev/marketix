<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Settings\StorageSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class StorageControllerTest extends TestCase
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
            ->get(route('app.admin.storage.edit'))
            ->assertForbidden();
    }

    public function test_edit_renders_without_exposing_secret(): void
    {
        $settings = app(StorageSettings::class);
        $settings->s3_secret = 'secret-value';
        $settings->save();

        $this->actingAs($this->superAdmin())
            ->get(route('app.admin.storage.edit'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Storage/Edit')
                ->where('has_s3_secret', true)
                ->missing('settings.s3_secret'));
    }

    public function test_update_persists_and_preserves_blank_secret(): void
    {
        $settings = app(StorageSettings::class);
        $settings->s3_secret = 'original-secret';
        $settings->save();

        $this->actingAs($this->superAdmin())
            ->put(route('app.admin.storage.update'), [
                'driver' => 's3',
                's3_key' => 'AKIA',
                's3_secret' => '', // blank → keep existing
                's3_region' => 'eu-central-1',
                's3_bucket' => 'bucket',
                's3_endpoint' => '',
                's3_use_path_style' => true,
            ])
            ->assertRedirect(route('app.admin.storage.edit'));

        $fresh = app(StorageSettings::class);
        $this->assertSame('s3', $fresh->driver);
        $this->assertSame('bucket', $fresh->s3_bucket);
        $this->assertSame('original-secret', $fresh->s3_secret);
    }

    public function test_update_replaces_secret_when_provided(): void
    {
        $settings = app(StorageSettings::class);
        $settings->s3_secret = 'original-secret';
        $settings->save();

        $this->actingAs($this->superAdmin())
            ->put(route('app.admin.storage.update'), [
                'driver' => 's3',
                's3_key' => 'AKIA',
                's3_secret' => 'rotated-secret',
                's3_region' => 'eu-central-1',
                's3_bucket' => 'bucket',
            ]);

        $this->assertSame('rotated-secret', app(StorageSettings::class)->s3_secret);
    }

    public function test_s3_driver_requires_bucket_region_and_key(): void
    {
        $this->actingAs($this->superAdmin())
            ->put(route('app.admin.storage.update'), [
                'driver' => 's3',
            ])
            ->assertSessionHasErrors(['s3_key', 's3_region', 's3_bucket']);
    }

    public function test_local_driver_needs_no_s3_fields(): void
    {
        $this->actingAs($this->superAdmin())
            ->put(route('app.admin.storage.update'), [
                'driver' => 'local',
            ])
            ->assertRedirect(route('app.admin.storage.edit'));

        $this->assertSame('local', app(StorageSettings::class)->driver);
    }

    public function test_test_connection_succeeds(): void
    {
        Storage::fake();

        $this->actingAs($this->superAdmin())
            ->post(route('app.admin.storage.test'), ['driver' => 'local'])
            ->assertRedirect()
            ->assertSessionHas('success');
    }

    public function test_test_connection_reports_failure(): void
    {
        Storage::shouldReceive('disk')->andThrow(new \RuntimeException('boom'));

        $this->actingAs($this->superAdmin())
            ->post(route('app.admin.storage.test'), ['driver' => 'local'])
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    public function test_test_connection_with_incomplete_s3_returns_error_flash_not_422(): void
    {
        $this->actingAs($this->superAdmin())
            ->post(route('app.admin.storage.test'), ['driver' => 's3'])
            ->assertRedirect()
            ->assertSessionHas('error')
            ->assertSessionMissing('errors');
    }

    public function test_switching_to_local_preserves_stored_s3_config(): void
    {
        $settings = app(StorageSettings::class);
        $settings->driver = 's3';
        $settings->s3_key = 'AKIA123';
        $settings->s3_region = 'eu-central-1';
        $settings->s3_bucket = 'my-bucket';
        $settings->s3_secret = 'my-secret';
        $settings->save();

        $this->actingAs($this->superAdmin())
            ->put(route('app.admin.storage.update'), ['driver' => 'local'])
            ->assertRedirect(route('app.admin.storage.edit'));

        $fresh = app(StorageSettings::class);
        $this->assertSame('local', $fresh->driver);
        $this->assertSame('AKIA123', $fresh->s3_key);
        $this->assertSame('eu-central-1', $fresh->s3_region);
        $this->assertSame('my-bucket', $fresh->s3_bucket);
        $this->assertSame('my-secret', $fresh->s3_secret);
    }
}
