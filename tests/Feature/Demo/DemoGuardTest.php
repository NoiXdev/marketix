<?php

namespace Tests\Feature\Demo;

use App\Http\Middleware\DemoGuard;
use App\Models\Crawl;
use App\Models\Domain;
use App\Models\Pixel;
use App\Models\Project;
use App\Models\ProjectInvitation;
use App\Models\ScheduledReport;
use App\Models\User;
use Illuminate\Cache\RateLimiter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Passkeys\Passkey;
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

    /**
     * A single walk over the full deny list, exercised with each route's own
     * registered HTTP method (so the two WebAuthn GET endpoints are hit as
     * GETs, not 405s) and, wherever the route requires a bound record, a
     * real one — several deny-list routes sit behind implicit model binding
     * (passkey.*) or manual project resolution that runs AFTER DemoGuard in
     * the middleware stack, so a missing/garbage id would 404 before
     * DemoGuard ever gets a say, which would make this test pass for the
     * wrong reason.
     *
     * The spec calls this test's prior absence out directly: findings 2 and
     * 3 (a second unblocked password-change route, and crawl-quota deletion)
     * were both additions to the app that this walk would have caught the
     * moment they were introduced.
     */
    public function test_every_blocked_route_refuses_a_demo_super_admin(): void
    {
        config(['demo.enabled' => true]);

        $user = $this->demoUser();

        $project = Project::factory()->create();
        $project->users()->attach($user, ['role' => 'admin', 'active' => true]);

        $domain = Domain::factory()->create(['project_id' => $project->id]);
        $pixel = Pixel::factory()->forProject($project)->create();
        $crawl = Crawl::factory()->create(['project_id' => $project->id]);
        $invitation = ProjectInvitation::factory()->create(['project_id' => $project->id]);
        $report = ScheduledReport::factory()->forProject($project, $user)->create();
        $otherUser = User::factory()->create();

        $passkey = new Passkey;
        $passkey->user_id = $user->id;
        $passkey->name = 'Test passkey';
        $passkey->credential_id = Str::random(40);
        $passkey->credential = ['type' => 'public-key'];
        $passkey->save();

        // Route parameters keyed by name; routes that don't need a given key
        // simply ignore it (extra values Laravel can't place become a
        // harmless query string).
        $params = [
            'project' => $project->id,
            'domain' => $domain->id,
            'pixel' => $pixel->id,
            'crawl' => $crawl->id,
            'invitation' => $invitation->id,
            'report' => $report->id,
            'user' => $otherUser->id,
            'token' => 'nonexistent-token-id',
            'passkey' => $passkey->id,
        ];

        foreach (DemoGuard::BLOCKED_ROUTES as $name) {
            $route = app('router')->getRoutes()->getByName($name);
            $this->assertNotNull($route, "Route [{$name}] on the deny list does not exist");

            $methods = array_values(array_diff($route->methods(), ['HEAD']));
            $method = $methods[0];

            session()->forget('error');

            // Laravel's default `throttle:*` signature keys solely on the
            // authenticated user id (see ThrottleRequests::resolveRequestSignature),
            // ignoring both the route and the configured limit — so every
            // throttled route in this walk shares one bucket for this user.
            // Left alone, routes later in BLOCKED_ROUTES (app.auth.forgot,
            // app.auth.reset, both throttle:5,1) would 429 from attempts
            // this loop already made against earlier throttled routes
            // (passkey.* at throttle:6,1, team.invitations.resend at
            // throttle:20,1), which would make this test pass for the wrong
            // reason. Clearing the shared bucket before every iteration
            // isolates each route's own check.
            app(RateLimiter::class)->clear(sha1((string) $user->getAuthIdentifier()));

            $response = $this->actingAs($user)->call($method, route($name, $params));

            $this->assertSame(
                __('demo.blocked'),
                session('error'),
                "Route [{$name}] ({$method}) was not refused by DemoGuard for a demo super-admin (status {$response->getStatusCode()})"
            );
        }
    }
}
