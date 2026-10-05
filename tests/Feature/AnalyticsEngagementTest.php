<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\PageView;
use App\Models\Site;
use App\Models\Visit;
use App\Services\AnalyticsAggregator;
use App\Services\GoalAggregator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class AnalyticsEngagementTest extends TestCase
{
    use RefreshDatabase;

    private function hit(Site $site, array $payload): TestResponse
    {
        return $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.7'])
            ->withHeaders(['User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_0) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Safari/605.1.15'])
            ->postJson(route('app.analytics.event'), ['site' => $site->tracking_id] + $payload);
    }

    public function test_config_exposes_enhanced_measurement_settings(): void
    {
        $site = Site::factory()->create([
            'track_outbound_links' => false,
            'track_file_downloads' => true,
            'site_search_params' => 'q,search',
        ]);

        $this->getJson(route('app.analytics.config', ['trackingId' => $site->tracking_id]))
            ->assertOk()
            ->assertJson([
                'outbound_links' => false,
                'file_downloads' => true,
                'search_params' => ['q', 'search'],
            ]);
    }

    public function test_engagement_pings_accumulate_time_and_keep_max_scroll(): void
    {
        $site = Site::factory()->create();
        $this->hit($site, ['path' => '/pricing', 'referrer' => null])->assertStatus(202);

        $this->travel(40)->seconds();
        $this->hit($site, ['type' => 'engagement', 'path' => '/pricing', 'engaged_ms' => 12500, 'scroll' => 80])->assertStatus(202);
        $this->hit($site, ['type' => 'engagement', 'path' => '/pricing', 'engaged_ms' => 3000, 'scroll' => 40])->assertStatus(202);

        $pageView = PageView::sole();
        $this->assertSame(15, $pageView->engaged_seconds);
        $this->assertSame(80, $pageView->scroll_depth);

        $visit = Visit::sole();
        $this->assertSame(1, $visit->pageview_count);
        $this->assertSame(40, (int) $visit->started_at->diffInSeconds($visit->last_activity_at));
    }

    public function test_engagement_without_an_open_visit_is_ignored(): void
    {
        $site = Site::factory()->create();

        $this->hit($site, ['type' => 'engagement', 'path' => '/pricing', 'engaged_ms' => 5000, 'scroll' => 50])->assertStatus(202);

        $this->assertSame(0, Visit::count());
        $this->assertSame(0, PageView::count());
    }

    public function test_engagement_after_the_session_window_does_not_extend_the_visit(): void
    {
        $site = Site::factory()->create();
        $this->hit($site, ['path' => '/pricing'])->assertStatus(202);

        $this->travel(45)->minutes();
        $this->hit($site, ['type' => 'engagement', 'path' => '/pricing', 'engaged_ms' => 5000, 'scroll' => 50])->assertStatus(202);

        $this->assertNull(PageView::sole()->engaged_seconds);
    }

    public function test_engagement_values_are_validated(): void
    {
        $site = Site::factory()->create();

        $this->hit($site, ['type' => 'engagement', 'path' => '/', 'engaged_ms' => -1])->assertStatus(422);
        $this->hit($site, ['type' => 'engagement', 'path' => '/', 'scroll' => 101])->assertStatus(422);
    }

    public function test_top_paths_report_average_engagement_and_scroll(): void
    {
        $site = Site::factory()->create();
        $visit = Visit::factory()->forSite($site)->create();
        PageView::factory()->forVisit($visit)->create(['path' => '/a', 'engaged_seconds' => 30, 'scroll_depth' => 40]);
        PageView::factory()->forVisit($visit)->create(['path' => '/a', 'engaged_seconds' => 60, 'scroll_depth' => 90]);
        PageView::factory()->forVisit($visit)->create(['path' => '/a', 'engaged_seconds' => null, 'scroll_depth' => null]);
        PageView::factory()->forVisit($visit)->create(['path' => '/b']);

        $rows = (new AnalyticsAggregator)->topPaths($site->id, 30)->keyBy('path');

        $this->assertSame(45, $rows['/a']->avg_engaged);
        $this->assertSame(65, $rows['/a']->avg_scroll);
        $this->assertNull($rows['/b']->avg_engaged);
    }

    public function test_event_breakdown_groups_by_property_or_path(): void
    {
        $site = Site::factory()->create();
        $visit = Visit::factory()->forSite($site)->create();
        Event::factory()->forVisit($visit)->count(2)->create(['name' => 'outbound_click', 'props' => ['url' => 'github.com/acme']]);
        Event::factory()->forVisit($visit)->create(['name' => 'outbound_click', 'props' => ['url' => 'x.com/acme']]);
        Event::factory()->forVisit($visit)->create(['name' => 'outbound_click', 'props' => null]);
        Event::factory()->forVisit($visit)->count(3)->create(['name' => 'not_found', 'path' => '/old']);

        $goals = new GoalAggregator;

        $outbound = $goals->eventBreakdown($site->id, 30, 'outbound_click', 'url');
        $this->assertSame(['github.com/acme', 'x.com/acme'], $outbound->pluck('value')->all());
        $this->assertSame(2, (int) $outbound->first()->count);

        $notFound = $goals->eventBreakdown($site->id, 30, 'not_found');
        $this->assertSame('/old', $notFound->first()->value);
        $this->assertSame(3, (int) $notFound->first()->count);
    }

    public function test_event_breakdown_rejects_unsafe_property_names(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new GoalAggregator)->eventBreakdown(Site::factory()->create()->id, 30, 'outbound_click', "url') OR 1=1 --");
    }
}
