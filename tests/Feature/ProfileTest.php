<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
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

    public function test_user_can_change_password_with_correct_current_password(): void
    {
        $user = User::factory()->create(); // factory password is 'password'

        $response = $this->actingAs($user)->put(route('app.profile.update'), [
            'current_password' => 'password',
            'password' => 'new-secret-password',
            'password_confirmation' => 'new-secret-password',
        ]);

        $response->assertRedirect(route('app.profile.edit'));
        $response->assertSessionHas('success');
        $this->assertTrue(Hash::check('new-secret-password', $user->refresh()->password));
    }

    public function test_password_change_fails_with_incorrect_current_password(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put(route('app.profile.update'), [
            'current_password' => 'wrong-password',
            'password' => 'new-secret-password',
            'password_confirmation' => 'new-secret-password',
        ])->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check('password', $user->refresh()->password));
    }

    public function test_password_change_fails_when_confirmation_does_not_match(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put(route('app.profile.update'), [
            'current_password' => 'password',
            'password' => 'new-secret-password',
            'password_confirmation' => 'different-password',
        ])->assertSessionHasErrors('password');
    }

    public function test_password_change_fails_when_new_password_too_short(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put(route('app.profile.update'), [
            'current_password' => 'password',
            'password' => 'short',
            'password_confirmation' => 'short',
        ])->assertSessionHasErrors('password');
    }
}
