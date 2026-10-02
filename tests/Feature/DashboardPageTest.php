<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\Project;
use App\Models\Statistic;
use App\Models\Url;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class DashboardPageTest extends TestCase
{
    use RefreshDatabase;

    private function ctx(): array
    {
        $user = User::factory()->create();
        $project = Project::create(['name' => 'Acme']);
        $user->projects()->attach($project);
        $domain = Domain::firstOrCreate(['project_id' => $project->id, 'name' => 'links.test']);
        // Url::create() relies on UrlObserver to set the required user_id from
        // auth()->id() when not given; pass it explicitly so the FK is satisfied
        // without authenticating (this fixture must stay usable by the guest test).
        $url = Url::create(['project_id' => $project->id, 'domain_id' => $domain->id, 'user_id' => $user->id, 'slug' => 'promo', 'url' => 'https://x.example', 'type' => 0, 'status' => 1, 'archived' => false]);
        Statistic::factory()->forUrl($url)->count(4)->country('Germany')->countryCode('DE')->create(['created_at' => now()->subDay()]);

        return [$user, $project];
    }

    public function test_dashboard_renders_default_dashboard_with_widgets(): void
    {
        [$user, $project] = $this->ctx();

        $this->actingAs($user)
            ->get(route('app.project.dashboard', ['project' => $project->id]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->component('Dashboard')
                ->has('dashboards', 1)
                ->where('dashboards.0.is_default', true)
                ->has('active.id')
                ->where('active.name', 'Overview')
                ->has('active.widgets')
            );
    }

    public function test_dashboard_query_param_selects_requested_dashboard(): void
    {
        [$user, $project] = $this->ctx();

        // The first visit seeds the default dashboard; add a second one to switch to.
        $this->actingAs($user)->get(route('app.project.dashboard', ['project' => $project->id]));
        $second = $user->dashboards()->create([
            'project_id' => $project->id,
            'name' => 'Second',
            'is_default' => false,
            'position' => 1,
            'widgets' => [],
        ]);

        $this->actingAs($user)
            ->get(route('app.project.dashboard', ['project' => $project->id]).'?dashboard='.$second->id)
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->component('Dashboard')
                ->has('dashboards', 2)
                ->where('active.id', $second->id)
                ->where('active.name', 'Second')
                ->where('active.widgets', [])
            );
    }

    public function test_guest_redirected_to_login(): void
    {
        [, $project] = $this->ctx();
        $this->get(route('app.project.dashboard', ['project' => $project->id]))
            ->assertRedirect(route('app.auth.show-login'));
    }
}
