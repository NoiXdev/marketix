<?php

namespace Tests\Feature;

use App\Models\Site;
use App\Models\Visit;
use App\Services\AnalyticsAggregator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalyticsCampaignAggregatorTest extends TestCase
{
    use RefreshDatabase;

    private AnalyticsAggregator $agg;

    protected function setUp(): void
    {
        parent::setUp();
        $this->agg = new AnalyticsAggregator();
    }

    public function test_utm_breakdown_counts_sessions_and_visitors_excluding_bots(): void
    {
        $site = Site::factory()->create();

        Visit::factory()->forSite($site)->create(['visitor_hash' => 'a', 'utm_source' => 'google']);
        Visit::factory()->forSite($site)->create(['visitor_hash' => 'a', 'utm_source' => 'google']); // same visitor
        Visit::factory()->forSite($site)->create(['visitor_hash' => 'b', 'utm_source' => 'google']);
        Visit::factory()->forSite($site)->create(['visitor_hash' => 'c', 'utm_source' => 'newsletter']);
        Visit::factory()->forSite($site)->create(['visitor_hash' => 'bot', 'utm_source' => 'google', 'is_bot' => true]);

        $rows = $this->agg->utmBreakdown($site->id, 'utm_source', 30);

        $this->assertSame('google', $rows->first()->value);
        $this->assertSame(3, (int) $rows->first()->sessions);
        $this->assertSame(2, (int) $rows->first()->visitors); // a, b (bot excluded)
    }

    public function test_utm_breakdown_rejects_unknown_column(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->agg->utmBreakdown(Site::factory()->create()->id, 'utm_evil', 30);
    }

    public function test_utm_source_medium_combines_values(): void
    {
        $site = Site::factory()->create();
        Visit::factory()->forSite($site)->count(2)->create(['utm_source' => 'google', 'utm_medium' => 'cpc']);
        Visit::factory()->forSite($site)->create(['utm_source' => 'google', 'utm_medium' => null]);

        $rows = $this->agg->utmSourceMedium($site->id, 30);

        $this->assertSame('google / cpc', $rows->first()->value);
        $this->assertSame(2, (int) $rows->first()->sessions);
        $this->assertTrue($rows->contains('value', 'google / (none)'));
    }

    public function test_campaign_share(): void
    {
        $site = Site::factory()->create();
        Visit::factory()->forSite($site)->create(['utm_source' => 'google']);
        Visit::factory()->forSite($site)->create(['utm_campaign' => 'x']);
        Visit::factory()->forSite($site)->create(); // organic, no utm

        $share = $this->agg->campaignShare($site->id, 30);

        $this->assertSame(3, $share['total']);
        $this->assertSame(2, $share['from_campaigns']);
        $this->assertSame(66.7, $share['percent']);
    }

    public function test_campaign_share_zero_total_is_zero_percent(): void
    {
        $share = $this->agg->campaignShare(Site::factory()->create()->id, 30);
        $this->assertSame(0, $share['total']);
        $this->assertSame(0.0, $share['percent']);
    }
}
