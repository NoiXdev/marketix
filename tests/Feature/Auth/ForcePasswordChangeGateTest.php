<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ForcePasswordChangeGateTest extends TestCase
{
    use RefreshDatabase;

    private function flaggedUser(): User
    {
        $u = User::factory()->create();
        $u->force_password_change = true;
        $u->save();

        return $u;
    }

    public function test_flagged_user_is_redirected_to_change_page(): void
    {
        $this->actingAs($this->flaggedUser())
            ->get(route('app.profile.edit'))
            ->assertRedirect(route('app.password.change.show'));
    }

    public function test_flagged_user_can_reach_the_change_page_itself(): void
    {
        $this->actingAs($this->flaggedUser())
            ->get(route('app.password.change.show'))
            ->assertOk();
    }

    public function test_flagged_user_can_logout(): void
    {
        $this->actingAs($this->flaggedUser())
            ->post(route('app.auth.logout'))
            ->assertRedirect();
        $this->assertGuest();
    }

    public function test_unflagged_user_is_not_redirected(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('app.profile.edit'))
            ->assertOk();
    }
}
