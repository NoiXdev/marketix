<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SetLocaleMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Route::middleware('web')->get('/__locale-probe', fn () => app()->getLocale());
    }

    public function test_defaults_to_english(): void
    {
        $this->get('/__locale-probe')->assertSee('en');
    }

    public function test_uses_accept_language_when_supported(): void
    {
        $this->get('/__locale-probe', ['Accept-Language' => 'de-DE,de;q=0.9'])->assertSee('de');
    }

    public function test_ignores_unsupported_accept_language(): void
    {
        $this->get('/__locale-probe', ['Accept-Language' => 'es-ES'])->assertSee('en');
    }

    public function test_cookie_beats_accept_language(): void
    {
        $this->withCookie('locale', 'nl')
            ->get('/__locale-probe', ['Accept-Language' => 'de'])
            ->assertSee('nl');
    }

    public function test_authenticated_user_locale_wins(): void
    {
        $user = User::factory()->create(['locale' => 'fr']);
        $this->actingAs($user)
            ->withCookie('locale', 'nl')
            ->get('/__locale-probe', ['Accept-Language' => 'de'])
            ->assertSee('fr');
    }
}
