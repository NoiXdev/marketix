<?php

namespace Tests\Feature;

use App\Enums\GoalType;
use App\Models\Event;
use App\Models\Goal;
use App\Models\PageView;
use App\Models\Site;
use App\Models\Visit;
use App\Services\AnalyticsAggregator;
use App\Services\GoalAggregator;
use App\Support\Analytics\AnalyticsQuery;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalyticsInsightsTest extends TestCase
{
    use RefreshDatabase;

    private AnalyticsAggregator $agg;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-07 15:30:00'));
        $this->agg = new AnalyticsAggregator;
    }

    private function visit(Site $site, array $attributes = [], int $pageViews = 1): Visit
    {
        $start = $attributes['started_at'] ?? now();
        $visit = Visit::factory()->forSite($site)->create(array_merge([
            'pageview_count' => $pageViews,
            'started_at' => $start,
            'last_activity_at' => $start,
        ], $attributes));

        PageView::factory()->forVisit($visit)->count($pageViews)->create([
            'visitor_hash' => $visit->visitor_hash,
            'created_at' => $start,
            'country_code' => $visit->country_code,
        ]);

        return $visit;
    }

    public function test_timeseries_reports_every_metric_per_day(): void
    {
        $site = Site::factory()->create();
        $today = CarbonImmutable::now()->setTime(10, 0);
        $this->visit($site, ['visitor_hash' => 'a', 'started_at' => $today, 'last_activity_at' => $today->addSeconds(120)], 3);
        $this->visit($site, ['visitor_hash' => 'b', 'started_at' => $today, 'utm_source' => 'newsletter'], 1);

        $series = $this->agg->timeseries($site->id, 7);

        $this->assertCount(7, $series);
        $last = end($series);
        $this->assertSame('2026-10-07', $last['date']);
        $this->assertSame(4, $last['views']);
        $this->assertSame(2, $last['visitors']);
        $this->assertSame(2, $last['sessions']);
        $this->assertSame(50.0, $last['bounce_rate']);
        $this->assertSame(60, $last['avg_duration']);
        $this->assertSame(50.0, $last['campaign_share']);
        $this->assertNull($series[0]['bounce_rate']);
    }

    public function test_today_is_bucketed_by_hour(): void
    {
        $site = Site::factory()->create();
        $this->visit($site, ['started_at' => CarbonImmutable::now()->setTime(9, 15)], 2);

        $series = $this->agg->timeseries($site->id, AnalyticsQuery::lastDays(1));

        $this->assertCount(24, $series);
        $this->assertSame('2026-10-07 09:00', $series[9]['date']);
        $this->assertSame(2, $series[9]['views']);
    }

    public function test_previous_period_is_the_window_before(): void
    {
        $site = Site::factory()->create();
        $this->visit($site, ['started_at' => CarbonImmutable::now()->subDays(2)], 1);
        $this->visit($site, ['started_at' => CarbonImmutable::now()->subDays(9)], 2);
        $this->visit($site, ['started_at' => CarbonImmutable::now()->subDays(20)], 4);

        $query = AnalyticsQuery::lastDays(7);

        $this->assertSame(1, $this->agg->summary($site->id, $query)['page_views']);
        $this->assertSame(2, $this->agg->summary($site->id, $query->previous())['page_views']);
    }

    public function test_previous_today_is_cut_at_the_same_time_of_day(): void
    {
        $site = Site::factory()->create();
        $this->visit($site, ['started_at' => CarbonImmutable::now()->subDay()->setTime(8, 0)], 1);
        $this->visit($site, ['started_at' => CarbonImmutable::now()->subDay()->setTime(20, 0)], 1);

        $previous = AnalyticsQuery::lastDays(1)->previous();

        $this->assertSame(1, $this->agg->summary($site->id, $previous)['page_views']);
    }

    public function test_channels_are_classified_from_first_touch_data(): void
    {
        $site = Site::factory()->create(['domain' => 'www.shop.test']);
        $this->visit($site, ['referer_domain' => null]);
        $this->visit($site, ['referer_domain' => 'www.google.ch']);
        $this->visit($site, ['referer_domain' => 't.co']);
        $this->visit($site, ['referer_domain' => 'www.google.com', 'utm_source' => 'google', 'utm_medium' => 'CPC']);
        $this->visit($site, ['utm_source' => 'Newsletter', 'utm_medium' => 'email']);
        $this->visit($site, ['referer_domain' => 'blog.example.org']);
        $this->visit($site, ['referer_domain' => 'shop.test']);
        $this->visit($site, ['utm_source' => 'partner', 'utm_campaign' => 'launch']);

        $channels = $this->agg->channels($site->id, 30)->pluck('count', 'channel')->map(fn ($c) => (int) $c)->all();

        $this->assertSame([
            'campaign' => 1,
            'direct' => 2,
            'email' => 1,
            'organic_search' => 1,
            'paid' => 1,
            'referral' => 1,
            'social' => 1,
        ], collect($channels)->sortKeys()->all());
    }

    public function test_top_referrers_skip_self_referrals(): void
    {
        $site = Site::factory()->create(['domain' => 'shop.test']);
        $this->visit($site, ['referer_domain' => 'www.shop.test']);
        $this->visit($site, ['referer_domain' => 'news.example.org']);

        $referrers = $this->agg->topReferrers($site->id, 30)->pluck('referer_domain')->all();

        $this->assertSame(['news.example.org'], $referrers);
    }

    public function test_entry_and_exit_pages(): void
    {
        $site = Site::factory()->create();
        $this->visit($site, ['entry_path' => '/landing', 'exit_path' => '/landing'], 1);
        $this->visit($site, ['entry_path' => '/landing', 'exit_path' => '/thanks'], 3);

        $entry = $this->agg->entryPages($site->id, 30)->first();
        $this->assertSame('/landing', $entry->entry_path);
        $this->assertSame(2, (int) $entry->count);
        $this->assertSame(50.0, $entry->bounce_rate);

        $this->assertEqualsCanonicalizing(['/landing', '/thanks'], $this->agg->exitPages($site->id, 30)->pluck('exit_path')->all());
    }

    public function test_live_visitors_only_count_recent_activity(): void
    {
        $site = Site::factory()->create();
        Visit::factory()->forSite($site)->create(['visitor_hash' => 'now', 'last_activity_at' => now()->subMinutes(2)]);
        Visit::factory()->forSite($site)->create(['visitor_hash' => 'old', 'last_activity_at' => now()->subMinutes(20)]);
        Visit::factory()->forSite($site)->create(['visitor_hash' => 'bot', 'last_activity_at' => now(), 'is_bot' => true]);

        $this->assertSame(1, $this->agg->liveVisitors($site->id));
    }

    public function test_heatmap_groups_sessions_by_weekday_and_hour(): void
    {
        $site = Site::factory()->create();
        $tuesday = CarbonImmutable::parse('2026-10-06 14:20:00');
        $this->visit($site, ['started_at' => $tuesday]);
        $this->visit($site, ['started_at' => $tuesday->addMinutes(10)]);

        $grid = $this->agg->weekdayHourHeatmap($site->id, 7);

        $this->assertCount(7, $grid);
        $this->assertCount(24, $grid[0]);
        $this->assertSame(2, $grid[1][14]);
        $this->assertSame(2, array_sum(array_map('array_sum', $grid)));
    }

    public function test_path_filter_scopes_page_views_and_sessions(): void
    {
        $site = Site::factory()->create();
        $visit = Visit::factory()->forSite($site)->create(['visitor_hash' => 'a', 'pageview_count' => 2, 'started_at' => now()]);
        PageView::factory()->forVisit($visit)->create(['visitor_hash' => 'a', 'path' => '/pricing', 'created_at' => now()]);
        PageView::factory()->forVisit($visit)->create(['visitor_hash' => 'a', 'path' => '/', 'created_at' => now()]);
        $this->visit($site, ['visitor_hash' => 'b'], 1);

        $summary = $this->agg->summary($site->id, AnalyticsQuery::lastDays(30, ['path' => '/pricing']));

        $this->assertSame(1, $summary['page_views']);
        $this->assertSame(1, $summary['sessions']);
    }

    public function test_channel_filter_scopes_page_views(): void
    {
        $site = Site::factory()->create();
        $this->visit($site, ['referer_domain' => 'www.google.com'], 2);
        $this->visit($site, ['referer_domain' => null], 3);

        $summary = $this->agg->summary($site->id, AnalyticsQuery::lastDays(30, ['channel' => 'organic_search']));

        $this->assertSame(2, $summary['page_views']);
        $this->assertSame(1, $summary['sessions']);
    }

    public function test_goal_conversions_respect_filters(): void
    {
        $site = Site::factory()->create();
        $goal = Goal::factory()->forSite($site)->create(['type' => GoalType::Event, 'match_value' => 'signup']);
        $de = $this->visit($site, ['country_code' => 'DE']);
        $ch = $this->visit($site, ['country_code' => 'CH']);
        Event::factory()->forVisit($de)->create(['name' => 'signup', 'created_at' => now()]);
        Event::factory()->forVisit($ch)->create(['name' => 'signup', 'created_at' => now()]);

        $goals = new GoalAggregator($this->agg);

        $this->assertSame(2, $goals->conversions($goal, 30)['conversions']);
        $this->assertSame(1, $goals->conversions($goal, AnalyticsQuery::lastDays(30, ['country_code' => 'CH']))['conversions']);
    }

    public function test_unknown_filters_are_ignored(): void
    {
        $query = AnalyticsQuery::lastDays(7, ['path' => '/a', 'evil' => 'x', 'browser' => ['array'], 'os' => '']);

        $this->assertSame(['path' => '/a'], $query->filters);
    }
}
