<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_from_the_profile_page(): void
    {
        $this->get(route('app.profile.edit'))
            ->assertRedirect(route('app.auth.show-login'));
    }

    public function test_profile_page_renders_for_authenticated_user(): void
    {
        $user = User::factory()->create(['name' => 'Jane Doe', 'email' => 'jane@example.com']);

        $this->actingAs($user)
            ->get(route('app.profile.edit'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Profile/Edit')
                ->where('user.name', 'Jane Doe')
                ->where('user.email', 'jane@example.com'));
    }
}
