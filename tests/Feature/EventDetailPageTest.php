<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Project;
use App\Models\Site;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class EventDetailPageTest extends TestCase
{
    use RefreshDatabase;

    private function ctx(): array
    {
        $user = User::factory()->create();
        $project = Project::create(['name' => 'Acme']);
        $user->projects()->attach($project);
        $site = Site::factory()->forProject($project)->create();

        return [$user, $project, $site];
    }

    public function test_it_renders_event_prop_breakdown(): void
    {
        [$user, $project, $site] = $this->ctx();
        $visit = Visit::factory()->forSite($site)->create();
        Event::factory()->forVisit($visit)->create(['name' => 'purchase', 'props' => ['plan' => 'pro', 'value' => 49]]);
        Event::factory()->forVisit($visit)->create(['name' => 'purchase', 'props' => ['plan' => 'free', 'value' => 10]]);

        $this->actingAs($user)
            ->get(route('app.project.analytics.events.show', ['project' => $project->id, 'site' => $site->id]).'?name=purchase&days=30')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Analytics/EventDetail')
                ->where('event', 'purchase')
                ->where('total', 2)
                ->has('keys', 2) // plan + value
            );
    }

    public function test_unknown_name_renders_empty(): void
    {
        [$user, $project, $site] = $this->ctx();

        $this->actingAs($user)
            ->get(route('app.project.analytics.events.show', ['project' => $project->id, 'site' => $site->id]).'?name=nope')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Analytics/EventDetail')
                ->where('total', 0)
                ->has('keys', 0)
            );
    }

    public function test_invalid_days_clamps_to_30(): void
    {
        [$user, $project, $site] = $this->ctx();

        $this->actingAs($user)
            ->get(route('app.project.analytics.events.show', ['project' => $project->id, 'site' => $site->id]).'?name=purchase&days=999')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->where('days', 30));
    }

    public function test_foreign_site_is_not_found(): void
    {
        [$user, $project] = $this->ctx();
        $foreign = Site::factory()->create();

        $this->actingAs($user)
            ->get(route('app.project.analytics.events.show', ['project' => $project->id, 'site' => $foreign->id]).'?name=purchase')
            ->assertNotFound();
    }

    public function test_guest_redirected_to_login(): void
    {
        [, $project, $site] = $this->ctx();
        $this->get(route('app.project.analytics.events.show', ['project' => $project->id, 'site' => $site->id]).'?name=purchase')
            ->assertRedirect(route('app.auth.show-login'));
    }
}
