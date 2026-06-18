<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ForcePasswordChangeTest extends TestCase
{
    use RefreshDatabase;

    public function test_change_page_renders_for_authenticated_user(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('app.password.change.show'))
            ->assertOk();
    }

    public function test_updating_password_clears_the_flag(): void
    {
        $user = User::factory()->create(['password' => Hash::make('old-password')]);
        $user->force_password_change = true;
        $user->save();

        $this->actingAs($user)
            ->put(route('app.password.change.update'), [
                'current_password' => 'old-password',
                'password' => 'new-password-123',
                'password_confirmation' => 'new-password-123',
            ])
            ->assertRedirect('/');

        $user->refresh();
        $this->assertFalse($user->force_password_change);
        $this->assertTrue(Hash::check('new-password-123', $user->password));
    }

    public function test_wrong_current_password_is_rejected(): void
    {
        $user = User::factory()->create(['password' => Hash::make('old-password')]);
        $user->force_password_change = true;
        $user->save();

        $this->actingAs($user)
            ->put(route('app.password.change.update'), [
                'current_password' => 'wrong',
                'password' => 'new-password-123',
                'password_confirmation' => 'new-password-123',
            ])
            ->assertSessionHasErrors('current_password');

        $this->assertTrue($user->fresh()->force_password_change);
    }
}
