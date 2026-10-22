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
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class AnalyticsDashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Project $project;

    private Site $site;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->project = Project::create(['name' => 'Acme']);
        $this->user->projects()->attach($this->project);
        $this->site = Site::factory()->forProject($this->project)->create();
    }

    private function dashboard(string $query = ''): TestResponse
    {
        return $this->actingAs($this->user)
            ->get(route('app.project.analytics.show', ['project' => $this->project->id, 'site' => $this->site->id]).$query);
    }

    public function test_overview_tab_is_the_default_and_only_loads_its_own_data(): void
    {
        $visit = Visit::factory()->forSite($this->site)->create(['visitor_hash' => 'v1', 'pageview_count' => 2]);
        PageView::factory()->forVisit($visit)->create(['visitor_hash' => 'v1', 'path' => '/home']);
        PageView::factory()->forVisit($visit)->create(['visitor_hash' => 'v1', 'path' => '/pricing']);
        PageView::factory()->forSite($this->site)->create(['visitor_hash' => 'bot', 'is_bot' => true]);

        $this->dashboard('?days=7')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Analytics/Index')
                ->where('tab', 'overview')
                ->where('period.range', '7d')
                ->where('period.days', 7)
                ->where('period.interval', 'day')
                ->where('period.compare', 'previous')
                ->where('summary.page_views', 2)
                ->where('summary.visitors', 1)
                ->where('previousSummary.page_views', 0)
                ->has('timeseries', 7)
                ->has('previousTimeseries', 7)
                ->has('topPaths', 2)
                ->has('channels')
                ->has('countries')
                ->has('devices')
                ->has('liveVisitors')
                ->where('site.search_enabled', false)
                ->missing('utmSources')
                ->missing('hourlyActivity')
                ->missing('goals')
                ->missing('clicksByCountry')
            );
    }

    public function test_acquisition_tab(): void
    {
        Visit::factory()->forSite($this->site)->create(['visitor_hash' => 'v1', 'utm_source' => 'google', 'utm_medium' => 'cpc']);
        Visit::factory()->forSite($this->site)->create(['visitor_hash' => 'v2']);

        $this->dashboard('?tab=acquisition')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('tab', 'acquisition')
                ->has('channels', 2)
                ->has('topReferrers')
                ->missing('utmSources')
                ->missing('utmMediums')
                ->has('utmCampaigns')
                ->has('utmSourceMediums', 1)
                ->where('utmSourceMediums.0.source', 'google')
                ->where('utmSourceMediums.0.medium', 'cpc')
                ->has('utmTerms')
                ->has('utmContents')
                ->missing('summary')
                ->missing('timeseries')
            );
    }

    public function test_behavior_tab(): void
    {
        $visit = Visit::factory()->forSite($this->site)->create();
        Event::factory()->forVisit($visit)->create(['name' => 'signup']);

        $this->dashboard('?tab=behavior')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('tab', 'behavior')
                ->has('topPaths')
                ->has('entryPages')
                ->has('exitPages')
                ->has('hostnames')
                ->has('topEvents', 1)
                ->where('topEvents.0.name', 'signup')
                ->has('interactions.outbound')
                ->has('interactions.downloads')
                ->has('interactions.searches')
                ->has('interactions.notFound')
                ->has('hourlyActivity', 1)
                ->missing('summary')
            );
    }

    public function test_audience_tab(): void
    {
        Visit::factory()->forSite($this->site)->create();

        $this->dashboard('?tab=audience')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('tab', 'audience')
                ->has('clicksByCountry')
                ->has('countries')
                ->has('regions')
                ->has('cities')
                ->has('languages')
                ->has('devices')
                ->has('browsers')
                ->has('operatingSystems')
                ->has('visitorTypes', 2)
                ->where('site.tracking_mode', $this->site->tracking_mode->value)
                ->missing('summary')
            );
    }

    public function test_conversions_tab(): void
    {
        $visit = Visit::factory()->forSite($this->site)->create(['utm_source' => 'google']);
        Event::factory()->forVisit($visit)->create(['name' => 'signup']);
        Goal::factory()->forSite($this->site)->create(['type' => GoalType::Event, 'match_value' => 'signup', 'name' => 'Signup']);

        $this->dashboard('?tab=conversions')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('tab', 'conversions')
                ->has('goals', 1)
                ->where('goals.0.conversions', 1)
                ->where('goals.0.name', 'Signup')
                ->has('goals.0.byChannel', 1)
                ->where('goals.0.byChannel.0.channel', 'campaign')
                ->where('goals.0.byChannel.0.conversions', 1)
                ->missing('summary')
            );
    }

    public function test_site_without_visits_exposes_the_setup_snippet_and_only_the_overview(): void
    {
        $this->dashboard('?tab=acquisition')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('site.has_data', false)
                ->where('site.snippet', $this->site->trackingSnippet())
                ->where('tab', 'overview')
                ->has('summary')
                ->missing('utmSources')
            );

        Visit::factory()->forSite($this->site)->create();

        $this->dashboard()
            ->assertInertia(fn (AssertableInertia $page) => $page->where('site.has_data', true));
    }

    public function test_unknown_tab_falls_back_to_overview(): void
    {
        $this->dashboard('?tab=secret')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->where('tab', 'overview')->has('summary'));
    }

    public function test_today_range_uses_hourly_buckets(): void
    {
        $this->dashboard('?days=1')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('period.range', 'today')
                ->where('period.interval', 'hour')
                ->has('timeseries', 24)
            );
    }

    public function test_custom_range_with_weekly_interval_and_year_comparison(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 7)->setTime(12, 0));

        $this->dashboard('?range=custom&from=2026-09-01&to=2026-09-30&interval=week&compare=year')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('period.range', 'custom')
                ->where('period.from', '2026-09-01')
                ->where('period.to', '2026-09-30')
                ->where('period.interval', 'week')
                ->where('period.intervals', ['day', 'week'])
                ->where('period.compare_from', '2025-09-01')
                ->has('timeseries', 5)
                ->where('timeseries.0.date', '2026-08-31')
                ->has('previousSummary')
                ->has('previousTimeseries', 5)
                ->where('previousTimeseries.0.date', '2025-09-01')
            );
    }

    public function test_comparison_can_be_disabled(): void
    {
        $this->dashboard('?range=12m&compare=none')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('period.compare', 'none')
                ->where('period.compare_from', null)
                ->where('previousSummary', null)
                ->where('previousTimeseries', null)
            );
    }

    public function test_filters_scope_the_dashboard_and_unknown_keys_are_dropped(): void
    {
        $de = Visit::factory()->forSite($this->site)->create(['visitor_hash' => 'de', 'country_code' => 'DE']);
        PageView::factory()->forVisit($de)->create(['visitor_hash' => 'de', 'country_code' => 'DE']);
        $ch = Visit::factory()->forSite($this->site)->create(['visitor_hash' => 'ch', 'country_code' => 'CH', 'utm_source' => 'google']);
        PageView::factory()->forVisit($ch)->count(2)->create(['visitor_hash' => 'ch', 'country_code' => 'CH']);

        $this->dashboard('?days=30&filters[country_code]=CH&filters[evil]=x')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('filters', ['country_code' => 'CH'])
                ->where('summary.page_views', 2)
                ->where('summary.sessions', 1)
                ->where('summary.campaign_share', fn ($share) => (float) $share === 100.0)
            );
    }

    public function test_rolling_ranges_compare_with_the_same_weekdays_and_calendar_ranges_do_not(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 22)->setTime(12, 0));

        $this->dashboard('?range=30d')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('period.from', '2026-09-23')
                ->where('period.compare_from', '2026-08-19')
                ->where('period.compare_to', '2026-09-17')
            );

        $this->dashboard('?range=month')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('period.compare_from', '2026-09-09')
                ->where('period.compare_to', '2026-09-30')
            );
    }

    public function test_comparison_is_flagged_incomplete_when_tracking_started_later(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 22)->setTime(12, 0));
        Visit::factory()->forSite($this->site)->create(['started_at' => now()->setDate(2026, 9, 1)]);

        $this->dashboard('?range=30d')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('period.compare_complete', false)
                ->where('period.tracking_since', '2026-09-01')
            );

        Visit::factory()->forSite($this->site)->create(['started_at' => now()->setDate(2026, 8, 1)]);

        $this->dashboard('?range=30d')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('period.compare_complete', true));
    }

    public function test_running_period_names_its_unfinished_bucket(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 22)->setTime(14, 30));

        $this->dashboard('?range=today')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('period.current_bucket', '2026-10-22 14:00'));
        $this->dashboard('?range=30d')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('period.current_bucket', '2026-10-22'));
        $this->dashboard('?range=yesterday')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('period.current_bucket', null));
    }

    public function test_revenue_tab_includes_the_comparison_series(): void
    {
        $visit = Visit::factory()->forSite($this->site)->create();
        Event::factory()->forVisit($visit)->create(['name' => 'purchase', 'props' => ['value' => 20, 'currency' => 'CHF']]);

        $this->dashboard('?tab=revenue&range=7d')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('revenue.timeseries', 7)
                ->has('revenue.previousTimeseries', 7)
            );
    }

    public function test_breakdown_endpoint_returns_the_full_list(): void
    {
        foreach (['/a', '/b', '/c'] as $path) {
            $visit = Visit::factory()->forSite($this->site)->create();
            PageView::factory()->forVisit($visit)->create(['path' => $path]);
        }

        $this->actingAs($this->user)
            ->getJson(route('app.project.analytics.breakdown', ['project' => $this->project->id, 'site' => $this->site->id, 'dimension' => 'pages']))
            ->assertOk()
            ->assertJsonCount(3, 'rows')
            ->assertJsonPath('limit', 1000);
    }

    public function test_breakdown_endpoint_rejects_unknown_dimensions_and_foreign_sites(): void
    {
        $url = route('app.project.analytics.breakdown', ['project' => $this->project->id, 'site' => $this->site->id, 'dimension' => 'pages']);

        $this->actingAs($this->user)->getJson(str_replace('/pages', '/secret', $url))->assertNotFound();

        $foreign = Site::factory()->create();
        $this->actingAs($this->user)
            ->getJson(route('app.project.analytics.breakdown', ['project' => $this->project->id, 'site' => $foreign->id, 'dimension' => 'pages']))
            ->assertNotFound();
    }

    public function test_foreign_project_site_is_not_found(): void
    {
        $foreign = Site::factory()->create();

        $this->actingAs($this->user)
            ->get(route('app.project.analytics.show', ['project' => $this->project->id, 'site' => $foreign->id]))
            ->assertNotFound();
    }
}
