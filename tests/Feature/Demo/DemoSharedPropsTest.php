<?php

namespace Tests\Feature\Demo;

use App\Http\Middleware\DemoGuard;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class DemoSharedPropsTest extends TestCase
{
    use RefreshDatabase;

    private function actor(): User
    {
        $user = User::factory()->create();
        $project = Project::create(['name' => 'Demo', 'locked' => false]);
        $project->users()->attach($user, ['role' => 'admin', 'active' => true]);

        return $user;
    }

    public function test_demo_prop_is_absent_when_demo_mode_is_off(): void
    {
        config(['demo.enabled' => false]);

        $this->actingAs($this->actor())
            ->get(route('app.profile.edit'))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('demo', null));
    }

    public function test_demo_prop_carries_the_blocked_route_list(): void
    {
        config(['demo.enabled' => true, 'demo.reset_at' => '04:00']);

        $this->actingAs($this->actor())
            ->get(route('app.profile.edit'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('demo.enabled', true)
                ->where('demo.resetAt', '04:00')
                ->where('demo.blockedRoutes', DemoGuard::BLOCKED_ROUTES)
            );
    }
}
