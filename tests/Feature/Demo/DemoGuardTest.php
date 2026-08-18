<?php

namespace Tests\Feature\Demo;

use App\Http\Middleware\DemoGuard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoGuardTest extends TestCase
{
    use RefreshDatabase;

    private function demoUser(): User
    {
        $user = User::factory()->create();
        $user->super_admin = true;
        $user->save();

        return $user;
    }

    public function test_blocked_route_is_denied_in_demo_mode(): void
    {
        config(['demo.enabled' => true]);

        $this->actingAs($this->demoUser())
            ->post(route('app.profile.two-factor.enable'))
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    public function test_blocked_route_is_allowed_when_demo_mode_is_off(): void
    {
        config(['demo.enabled' => false]);

        $this->actingAs($this->demoUser())
            ->post(route('app.profile.two-factor.enable'))
            ->assertSessionMissing('error');
    }

    public function test_page_routes_stay_browsable_in_demo_mode(): void
    {
        config(['demo.enabled' => true]);

        $this->actingAs($this->demoUser())
            ->get(route('app.profile.edit'))
            ->assertOk();
    }

    public function test_responses_carry_a_noindex_header_in_demo_mode(): void
    {
        config(['demo.enabled' => true]);

        $this->actingAs($this->demoUser())
            ->get(route('app.profile.edit'))
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    public function test_every_blocked_route_name_actually_exists(): void
    {
        foreach (DemoGuard::BLOCKED_ROUTES as $name) {
            $this->assertNotNull(
                app('router')->getRoutes()->getByName($name),
                "Route [{$name}] on the deny list does not exist"
            );
        }
    }
}
