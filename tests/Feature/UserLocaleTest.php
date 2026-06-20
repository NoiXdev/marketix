<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserLocaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_locale_is_fillable_and_persists(): void
    {
        $user = User::factory()->create(['locale' => 'de']);
        $this->assertSame('de', $user->fresh()->locale);
    }

    public function test_preferred_locale_falls_back_to_default(): void
    {
        $user = User::factory()->create(['locale' => null]);
        $this->assertSame('en', $user->preferredLocale());
    }

    public function test_preferred_locale_uses_stored_value(): void
    {
        $user = User::factory()->create(['locale' => 'fr']);
        $this->assertSame('fr', $user->preferredLocale());
    }
}
