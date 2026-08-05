<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Site;
use App\Services\EventPropertyAggregator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventPropertyAggregatorTest extends TestCase
{
    use RefreshDatabase;

    private EventPropertyAggregator $agg;

    protected function setUp(): void
    {
        parent::setUp();
        $this->agg = new EventPropertyAggregator();
    }

    private function event(Site $site, array $props, bool $bot = false): void
    {
        Event::factory()->forSite($site)->create(['name' => 'purchase', 'props' => $props, 'is_bot' => $bot]);
    }

    public function test_categorical_distribution_per_key(): void
    {
        $site = Site::factory()->create();
        $this->event($site, ['plan' => 'pro']);
        $this->event($site, ['plan' => 'pro']);
        $this->event($site, ['plan' => 'pro']);
        $this->event($site, ['plan' => 'free']);

        $result = $this->agg->forEvent($site->id, 'purchase', 30);

        $this->assertSame(4, $result['total']);
        $planKey = collect($result['keys'])->firstWhere('key', 'plan');
        $this->assertSame([['value' => 'pro', 'count' => 3], ['value' => 'free', 'count' => 1]], $planKey['values']);
        $this->assertNull($planKey['numeric']);
    }

    public function test_numeric_summary_for_numeric_props(): void
    {
        $site = Site::factory()->create();
        $this->event($site, ['value' => 49]);
        $this->event($site, ['value' => 49]);
        $this->event($site, ['value' => 10]);

        $result = $this->agg->forEvent($site->id, 'purchase', 30);
        $valueKey = collect($result['keys'])->firstWhere('key', 'value');

        $this->assertSame(['count' => 3, 'sum' => 108.0, 'avg' => 36.0], $valueKey['numeric']);
        // categorical values are still present
        $this->assertSame(3, collect($valueKey['values'])->sum('count'));
    }

    public function test_key_present_on_some_events_only(): void
    {
        $site = Site::factory()->create();
        $this->event($site, ['plan' => 'pro', 'coupon' => 'X']);
        $this->event($site, ['plan' => 'free']); // no coupon

        $result = $this->agg->forEvent($site->id, 'purchase', 30);
        $coupon = collect($result['keys'])->firstWhere('key', 'coupon');
        $this->assertSame([['value' => 'X', 'count' => 1]], $coupon['values']);
    }

    public function test_non_scalar_prop_values_are_skipped(): void
    {
        $site = Site::factory()->create();
        $this->event($site, ['items' => [1, 2, 3], 'plan' => 'pro']);

        $result = $this->agg->forEvent($site->id, 'purchase', 30);
        $this->assertNull(collect($result['keys'])->firstWhere('key', 'items'));
        $this->assertNotNull(collect($result['keys'])->firstWhere('key', 'plan'));
    }

    public function test_bots_excluded(): void
    {
        $site = Site::factory()->create();
        $this->event($site, ['plan' => 'pro']);
        $this->event($site, ['plan' => 'bot'], bot: true);

        $result = $this->agg->forEvent($site->id, 'purchase', 30);
        $this->assertSame(1, $result['total']);
        $planKey = collect($result['keys'])->firstWhere('key', 'plan');
        $this->assertSame([['value' => 'pro', 'count' => 1]], $planKey['values']);
    }

    public function test_top_values_capped_at_ten(): void
    {
        $site = Site::factory()->create();
        for ($i = 0; $i < 12; $i++) {
            // give each value a distinct frequency so ordering is deterministic
            for ($j = 0; $j <= $i; $j++) {
                $this->event($site, ['variant' => 'v'.$i]);
            }
        }

        $result = $this->agg->forEvent($site->id, 'purchase', 30);
        $variant = collect($result['keys'])->firstWhere('key', 'variant');
        $this->assertCount(10, $variant['values']);
        $this->assertSame('v11', $variant['values'][0]['value']); // highest count first
    }
}
