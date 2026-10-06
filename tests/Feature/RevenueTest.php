<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\PageView;
use App\Models\Project;
use App\Models\Site;
use App\Models\User;
use App\Models\Visit;
use App\Services\RevenueAggregator;
use App\Support\Analytics\AnalyticsQuery;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class RevenueTest extends TestCase
{
    use RefreshDatabase;

    private Site $site;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-07 15:30:00'));
        $this->site = Site::factory()->create();
    }

    private function purchase(float|string $value, ?string $currency = 'CHF', array $visit = [], ?CarbonImmutable $at = null): Visit
    {
        $at ??= CarbonImmutable::now()->subHour();
        $record = Visit::factory()->forSite($this->site)->create($visit + ['started_at' => $at, 'last_activity_at' => $at]);
        PageView::factory()->forVisit($record)->create(['created_at' => $at]);
        Event::factory()->forVisit($record)->create([
            'name' => 'purchase',
            'props' => array_filter(['value' => $value, 'currency' => $currency], fn ($v) => $v !== null),
            'created_at' => $at,
        ]);

        return $record;
    }

    public function test_summary_totals_and_rates(): void
    {
        $this->purchase(100);
        $this->purchase('49.50', 'chf');
        Visit::factory()->forSite($this->site)->count(2)->create(['started_at' => now()->subHour()]);

        $summary = (new RevenueAggregator)->summary($this->site->id, 7, 'CHF');

        $this->assertSame(149.5, $summary['revenue']);
        $this->assertSame(2, $summary['orders']);
        $this->assertSame(74.75, $summary['average_order_value']);
        $this->assertSame(50.0, $summary['purchase_rate']);
    }

    public function test_main_currency_is_the_one_with_most_revenue(): void
    {
        $this->purchase(30, 'EUR');
        $this->purchase(80, 'CHF');
        $this->purchase(10, null);

        $currencies = (new RevenueAggregator)->currencies($this->site->id, 7);

        $this->assertSame(['CHF', 'EUR', ''], array_column($currencies, 'currency'));
        $this->assertSame(80.0, (new RevenueAggregator)->summary($this->site->id, 7, 'CHF')['revenue']);
    }

    public function test_revenue_timeseries_and_breakdowns(): void
    {
        $this->purchase(100, 'CHF', ['utm_source' => 'newsletter', 'utm_medium' => 'email', 'utm_campaign' => 'autumn', 'entry_path' => '/sale', 'country_code' => 'CH']);
        $this->purchase(40, 'CHF', ['referer_domain' => 'www.google.com', 'entry_path' => '/', 'country_code' => 'DE'], CarbonImmutable::now()->subDays(2));

        $agg = new RevenueAggregator;

        $series = $agg->timeseries($this->site->id, 7, 'CHF');
        $this->assertCount(7, $series);
        $this->assertSame(100.0, end($series)['revenue']);
        $this->assertSame(40.0, $series[4]['revenue']);

        $channels = $agg->breakdown($this->site->id, 7, 'CHF', 'channel');
        $this->assertSame(['email', 'organic_search'], $channels->pluck('value')->all());
        $this->assertSame([100.0, 40.0], $channels->pluck('revenue')->all());

        $this->assertSame(['autumn'], $agg->breakdown($this->site->id, 7, 'CHF', 'utm_campaign')->pluck('value')->all());
        $this->assertSame(['/sale', '/'], $agg->breakdown($this->site->id, 7, 'CHF', 'entry_path')->pluck('value')->all());
        $this->assertSame(['CH', 'DE'], $agg->breakdown($this->site->id, 7, 'CHF', 'country_code')->pluck('value')->all());
    }

    public function test_revenue_respects_filters(): void
    {
        $this->purchase(100, 'CHF', ['country_code' => 'CH']);
        $this->purchase(40, 'CHF', ['country_code' => 'DE']);

        $summary = (new RevenueAggregator)->summary($this->site->id, AnalyticsQuery::lastDays(7, ['country_code' => 'DE']), 'CHF');

        $this->assertSame(40.0, $summary['revenue']);
    }

    public function test_unknown_breakdown_dimension_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new RevenueAggregator)->breakdown($this->site->id, 7, 'CHF', 'visitor_hash');
    }

    public function test_revenue_tab_props(): void
    {
        $user = User::factory()->create();
        $project = Project::find($this->site->project_id);
        $user->projects()->attach($project);
        $this->purchase(25);

        $this->actingAs($user)
            ->get(route('app.project.analytics.show', ['project' => $project->id, 'site' => $this->site->id, 'tab' => 'revenue']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('tab', 'revenue')
                ->where('revenue.currency', 'CHF')
                ->where('revenue.summary.orders', 1)
                ->has('revenue.timeseries', 30)
                ->has('revenue.channels', 1)
                ->has('revenue.previousSummary')
                ->missing('summary')
            );
    }
}
