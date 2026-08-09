<?php

namespace Tests\Feature;

use App\Models\PageView;
use App\Models\Site;
use App\Models\Visit;
use App\Services\AnalyticsAggregator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalyticsAggregatorTest extends TestCase
{
    use RefreshDatabase;

    private AnalyticsAggregator $agg;

    protected function setUp(): void
    {
        parent::setUp();
        $this->agg = new AnalyticsAggregator;
    }

    public function test_totals_and_unique_visitors_exclude_bots(): void
    {
        $site = Site::factory()->create();
        $visit = Visit::factory()->forSite($site)->create(['visitor_hash' => 'v1']);

        PageView::factory()->forVisit($visit)->create(['visitor_hash' => 'v1', 'path' => '/a', 'is_bot' => false]);
        PageView::factory()->forVisit($visit)->create(['visitor_hash' => 'v1', 'path' => '/b', 'is_bot' => false]);
        PageView::factory()->forSite($site)->create(['visitor_hash' => 'bot', 'is_bot' => true]);

        $this->assertSame(2, $this->agg->totalPageViews($site->id, 30));
        $this->assertSame(1, $this->agg->uniqueVisitors($site->id, 30));
    }

    public function test_top_paths_ranks_by_views(): void
    {
        $site = Site::factory()->create();
        $visit = Visit::factory()->forSite($site)->create();
        PageView::factory()->forVisit($visit)->count(3)->create(['path' => '/popular']);
        PageView::factory()->forVisit($visit)->create(['path' => '/rare']);

        $top = $this->agg->topPaths($site->id, 30);
        $this->assertSame('/popular', $top->first()->path);
        $this->assertSame(3, (int) $top->first()->count);
    }

    public function test_bounce_rate_counts_single_page_visits(): void
    {
        $site = Site::factory()->create();
        Visit::factory()->forSite($site)->create(['pageview_count' => 1, 'is_bot' => false]);
        Visit::factory()->forSite($site)->create(['pageview_count' => 5, 'is_bot' => false]);

        // 1 of 2 visits bounced => 50.0
        $this->assertSame(50.0, $this->agg->bounceRate($site->id, 30));
    }

    public function test_page_views_by_day_is_zero_filled(): void
    {
        $site = Site::factory()->create();
        $result = $this->agg->pageViewsByDay($site->id, 7);
        $this->assertCount(7, $result);
        $this->assertArrayHasKey('views', $result[0]);
        $this->assertArrayHasKey('visitors', $result[0]);
    }

    public function test_breakdown_rejects_unknown_column(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->agg->breakdown(Site::factory()->create()->id, 'evil', 30);
    }
}
