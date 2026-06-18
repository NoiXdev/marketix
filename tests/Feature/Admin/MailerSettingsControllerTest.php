<?php

namespace Tests\Feature\Admin;

use App\Mail\TestMail;
use App\Models\User;
use App\Settings\MailSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class MailerSettingsControllerTest extends TestCase
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
            ->get(route('app.admin.mailer.edit'))
            ->assertForbidden();
    }

    public function test_edit_page_renders_without_exposing_secrets(): void
    {
        $settings = app(MailSettings::class);
        $settings->postal_key = 'secret-key';
        $settings->save();

        $this->actingAs($this->superAdmin())
            ->get(route('app.admin.mailer.edit'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Mailer/Edit')
                ->where('has_postal_key', true)
                ->missing('settings.postal_key')
                ->missing('settings.smtp_password'));
    }

    public function test_update_persists_and_preserves_blank_secret(): void
    {
        $settings = app(MailSettings::class);
        $settings->postal_key = 'original-key';
        $settings->save();

        $this->actingAs($this->superAdmin())
            ->put(route('app.admin.mailer.update'), [
                'default_mailer' => 'postal',
                'from_address' => 'new@example.com',
                'from_name' => 'New Name',
                'postal_url' => 'https://postal.example.com',
                'postal_key' => '', // blank → keep existing
                'smtp_host' => '',
                'smtp_port' => 587,
                'smtp_username' => '',
                'smtp_password' => '',
                'smtp_scheme' => '',
            ])
            ->assertRedirect(route('app.admin.mailer.edit'));

        $fresh = app(MailSettings::class);
        $this->assertSame('new@example.com', $fresh->from_address);
        $this->assertSame('original-key', $fresh->postal_key);
    }

    public function test_update_replaces_secret_when_provided(): void
    {
        $settings = app(MailSettings::class);
        $settings->postal_key = 'original-key';
        $settings->save();

        $this->actingAs($this->superAdmin())
            ->put(route('app.admin.mailer.update'), [
                'default_mailer' => 'postal',
                'from_address' => 'a@example.com',
                'from_name' => 'A',
                'postal_url' => 'https://postal.example.com',
                'postal_key' => 'rotated-key',
                'smtp_port' => 587,
            ]);

        $this->assertSame('rotated-key', app(MailSettings::class)->postal_key);
    }

    public function test_update_validates_default_mailer(): void
    {
        $this->actingAs($this->superAdmin())
            ->put(route('app.admin.mailer.update'), [
                'default_mailer' => 'bogus',
                'from_address' => 'a@example.com',
                'from_name' => 'A',
                'smtp_port' => 587,
            ])
            ->assertSessionHasErrors('default_mailer');
    }

    public function test_test_email_is_sent(): void
    {
        Mail::fake();

        $this->actingAs($this->superAdmin())
            ->post(route('app.admin.mailer.test'), ['test_email' => 'dest@example.com'])
            ->assertRedirect();

        Mail::assertSent(TestMail::class, fn ($mail) => $mail->hasTo('dest@example.com'));
    }
}
