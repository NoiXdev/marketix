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

    public function test_dashboard_renders_with_overview_props(): void
    {
        [$user, $project] = $this->ctx();

        $this->actingAs($user)
            ->get(route('app.project.dashboard', ['project' => $project->id]).'?days=30')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->component('Dashboard')
                ->where('days', 30)
                ->where('kpis.clicks.value', 4)
                ->where('kpis.activeLinks.value', 1)
                ->has('kpis.uniqueVisitors')
                ->has('kpis.avgPerLink')
                ->has('clicksByDay', 30)
                ->has('topLinks', 1)
                ->has('topCountries')
                ->has('recentActivity')
            );
    }

    public function test_invalid_days_clamps_to_30(): void
    {
        [$user, $project] = $this->ctx();
        $this->actingAs($user)
            ->get(route('app.project.dashboard', ['project' => $project->id]).'?days=999')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p->where('days', 30));
    }

    public function test_guest_redirected_to_login(): void
    {
        [, $project] = $this->ctx();
        $this->get(route('app.project.dashboard', ['project' => $project->id]))
            ->assertRedirect(route('app.auth.show-login'));
    }
}
