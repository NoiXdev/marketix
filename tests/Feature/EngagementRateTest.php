<?php

namespace Tests\Feature;

use App\Enums\GoalType;
use App\Models\Event;
use App\Models\Goal;
use App\Models\Site;
use App\Models\Visit;
use App\Services\AnalyticsAggregator;
use App\Support\Analytics\AnalyticsQuery;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EngagementRateTest extends TestCase
{
    use RefreshDatabase;

    private Site $site;

    private AnalyticsAggregator $agg;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-07 12:00:00'));
        $this->site = Site::factory()->create();
        $this->agg = app(AnalyticsAggregator::class);

        Goal::factory()->create([
            'site_id' => $this->site->id,
            'project_id' => $this->site->project_id,
            'type' => GoalType::Event,
            'match_value' => 'signup',
        ]);

        $this->visit(pageViews: 1, seconds: 0, referer: 'www.google.com');
        $this->visit(pageViews: 2, seconds: 0, referer: 'www.google.com');
        $this->visit(pageViews: 1, seconds: 15);
        $this->visit(pageViews: 1, seconds: 5, event: 'signup');
        $this->visit(pageViews: 1, seconds: 0, event: 'outbound_click');
        $this->visit(pageViews: 1, seconds: 0, event: 'purchase');
        $this->visit(pageViews: 1, seconds: 9);
    }

    private function visit(int $pageViews, int $seconds, ?string $referer = null, ?string $event = null): void
    {
        $start = CarbonImmutable::now()->subDays(2);
        $visit = Visit::factory()->forSite($this->site)->create([
            'started_at' => $start,
            'last_activity_at' => $start->addSeconds($seconds),
            'pageview_count' => $pageViews,
            'referer_domain' => $referer,
        ]);

        if ($event !== null) {
            Event::factory()->forVisit($visit)->create(['name' => $event, 'created_at' => $start]);
        }
    }

    public function test_summary_counts_sessions_that_are_long_deep_or_convert(): void
    {
        $this->assertSame(57.1, $this->agg->summary($this->site->id, 30)['engagement_rate']);
    }

    public function test_engagement_rate_per_channel(): void
    {
        $channels = $this->agg->channels($this->site->id, 30)->keyBy('channel');

        $this->assertSame(50.0, $channels['organic_search']->engagement_rate);
        $this->assertSame(60.0, $channels['direct']->engagement_rate);
        $this->assertObjectNotHasProperty('engaged', $channels['direct']);
    }

    public function test_timeseries_reports_engagement_per_bucket(): void
    {
        $series = collect($this->agg->timeseries($this->site->id, AnalyticsQuery::lastDays(7)->daily()))->keyBy('date');

        $this->assertSame(57.1, $series['2026-10-05']['engagement_rate']);
        $this->assertNull($series['2026-10-06']['engagement_rate']);
    }

    public function test_summary_without_sessions_is_zero(): void
    {
        $empty = Site::factory()->create();

        $this->assertSame(0.0, $this->agg->summary($empty->id, 30)['engagement_rate']);
    }
}
