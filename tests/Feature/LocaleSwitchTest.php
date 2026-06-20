<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocaleSwitchTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_switch_sets_cookie(): void
    {
        $this->from('/auth/login')
            ->post(route('app.locale.update'), ['locale' => 'nl'])
            ->assertRedirect('/auth/login')
            ->assertCookie('locale', 'nl');
    }

    public function test_authenticated_switch_persists_to_db(): void
    {
        $user = User::factory()->create(['locale' => null]);

        $this->actingAs($user)
            ->from('/')
            ->post(route('app.locale.update'), ['locale' => 'fr'])
            ->assertCookie('locale', 'fr');

        $this->assertSame('fr', $user->fresh()->locale);
    }

    public function test_rejects_unsupported_locale(): void
    {
        $this->post(route('app.locale.update'), ['locale' => 'es'])
            ->assertSessionHasErrors('locale');
    }
}
