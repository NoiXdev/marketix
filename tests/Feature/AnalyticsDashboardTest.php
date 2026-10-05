<?php

namespace Tests\Feature;

use App\Enums\GoalType;
use App\Models\Event;
use App\Models\Goal;
use App\Models\PageView;
use App\Models\Project;
use App\Models\Site;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class AnalyticsDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_renders_site_analytics_with_metrics(): void
    {
        $user = User::factory()->create();
        $project = Project::create(['name' => 'Acme']);
        $user->projects()->attach($project);
        $site = Site::factory()->forProject($project)->create();

        $visit = Visit::factory()->forSite($site)->create(['visitor_hash' => 'v1', 'pageview_count' => 2]);
        PageView::factory()->forVisit($visit)->create(['visitor_hash' => 'v1', 'path' => '/home']);
        PageView::factory()->forVisit($visit)->create(['visitor_hash' => 'v1', 'path' => '/pricing']);
        PageView::factory()->forSite($site)->create(['visitor_hash' => 'bot', 'is_bot' => true]);

        $this->actingAs($user)
            ->get(route('app.project.analytics.show', ['project' => $project->id, 'site' => $site->id]).'?days=7')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Analytics/Index')
                ->where('days', 7)
                ->where('summary.page_views', 2)
                ->where('summary.visitors', 1)
                ->where('previousSummary.page_views', 0)
                ->has('timeseries', 7)
                ->has('topPaths')
                ->has('entryPages')
                ->has('exitPages')
                ->has('topReferrers')
                ->has('channels')
                ->has('countries')
                ->has('languages')
                ->has('hourlyActivity', 1)
                ->has('liveVisitors')
            );
    }

    public function test_today_range_uses_hourly_buckets(): void
    {
        $user = User::factory()->create();
        $project = Project::create(['name' => 'Acme']);
        $user->projects()->attach($project);
        $site = Site::factory()->forProject($project)->create();

        $this->actingAs($user)
            ->get(route('app.project.analytics.show', ['project' => $project->id, 'site' => $site->id]).'?days=1')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('days', 1)
                ->has('timeseries', 24)
            );
    }

    public function test_filters_scope_the_dashboard_and_unknown_keys_are_dropped(): void
    {
        $user = User::factory()->create();
        $project = Project::create(['name' => 'Acme']);
        $user->projects()->attach($project);
        $site = Site::factory()->forProject($project)->create();

        $de = Visit::factory()->forSite($site)->create(['visitor_hash' => 'de', 'country_code' => 'DE']);
        PageView::factory()->forVisit($de)->create(['visitor_hash' => 'de', 'country_code' => 'DE']);
        $ch = Visit::factory()->forSite($site)->create(['visitor_hash' => 'ch', 'country_code' => 'CH']);
        PageView::factory()->forVisit($ch)->count(2)->create(['visitor_hash' => 'ch', 'country_code' => 'CH']);

        $url = route('app.project.analytics.show', ['project' => $project->id, 'site' => $site->id]);

        $this->actingAs($user)
            ->get($url.'?days=30&filters[country_code]=CH&filters[evil]=x')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('filters', ['country_code' => 'CH'])
                ->where('summary.page_views', 2)
                ->where('summary.sessions', 1)
            );
    }

    public function test_dashboard_exposes_campaign_props(): void
    {
        $user = User::factory()->create();
        $project = Project::create(['name' => 'Acme']);
        $user->projects()->attach($project);
        $site = Site::factory()->forProject($project)->create();

        Visit::factory()->forSite($site)->create(['visitor_hash' => 'v1', 'utm_source' => 'google', 'utm_medium' => 'cpc']);
        Visit::factory()->forSite($site)->create(['visitor_hash' => 'v2']); // organic

        $this->actingAs($user)
            ->get(route('app.project.analytics.show', ['project' => $project->id, 'site' => $site->id]).'?days=30')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Analytics/Index')
                ->where('summary.sessions', 2)
                ->where('summary.campaign_share', fn ($share) => (float) $share === 50.0)
                ->has('utmSources')
                ->has('utmMediums')
                ->has('utmCampaigns')
                ->has('utmSourceMediums')
                ->has('utmTerms')
                ->has('utmContents')
            );
    }

    public function test_dashboard_exposes_events_and_goals(): void
    {
        $user = User::factory()->create();
        $project = Project::create(['name' => 'Acme']);
        $user->projects()->attach($project);
        $site = Site::factory()->forProject($project)->create();

        $visit = Visit::factory()->forSite($site)->create(['utm_source' => 'google']);
        Event::factory()->forVisit($visit)->create(['name' => 'signup']);
        Goal::factory()->forSite($site)->create(['type' => GoalType::Event, 'match_value' => 'signup', 'name' => 'Signup']);

        $this->actingAs($user)
            ->get(route('app.project.analytics.show', ['project' => $project->id, 'site' => $site->id]).'?days=30')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Analytics/Index')
                ->has('topEvents', 1)
                ->where('topEvents.0.name', 'signup')
                ->has('goals', 1)
                ->where('goals.0.conversions', 1)
                ->where('goals.0.name', 'Signup')
                ->has('goals.0.byCampaign')
            );
    }

    public function test_foreign_project_site_is_not_found(): void
    {
        $user = User::factory()->create();
        $project = Project::create(['name' => 'Acme']);
        $user->projects()->attach($project);
        $foreign = Site::factory()->create();

        $this->actingAs($user)
            ->get(route('app.project.analytics.show', ['project' => $project->id, 'site' => $foreign->id]))
            ->assertNotFound();
    }
}
