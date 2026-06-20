<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Translations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShareTranslationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_for_locale_merges_over_english(): void
    {
        // 'fr' has no 'min' english-only key drift: missing keys fall back to en.
        $fr = Translations::forLocale('fr');
        $this->assertArrayHasKey('validation', $fr);
        $this->assertSame(
            'Le champ :attribute est obligatoire.',
            $fr['validation']['required']
        );
    }

    public function test_inertia_shares_locale_and_translations(): void
    {
        $user = User::factory()->create(['locale' => 'de']);

        $this->actingAs($user)
            ->get(route('app.profile.edit'))
            ->assertInertia(fn ($page) => $page
                ->where('locale', 'de')
                ->has('translations.validation')
                ->has('availableLocales', 4)
            );
    }
}
