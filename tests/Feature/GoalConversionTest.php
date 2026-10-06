<?php

namespace Tests\Feature;

use App\Enums\GoalType;
use App\Models\Event;
use App\Models\Goal;
use App\Models\PageView;
use App\Models\Site;
use App\Models\Visit;
use App\Services\GoalAggregator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GoalConversionTest extends TestCase
{
    use RefreshDatabase;

    private GoalAggregator $agg;

    protected function setUp(): void
    {
        parent::setUp();
        $this->agg = new GoalAggregator;
    }

    public function test_top_events_counts_and_unique_visitors_exclude_bots(): void
    {
        $site = Site::factory()->create();
        $v = Visit::factory()->forSite($site)->create();
        Event::factory()->forVisit($v)->create(['name' => 'signup', 'visitor_hash' => 'a']);
        Event::factory()->forVisit($v)->create(['name' => 'signup', 'visitor_hash' => 'a']);
        Event::factory()->forVisit($v)->create(['name' => 'signup', 'visitor_hash' => 'b']);
        Event::factory()->forSite($site)->create(['name' => 'signup', 'visitor_hash' => 'bot', 'is_bot' => true]);

        $rows = $this->agg->topEvents($site->id, 30);
        $this->assertSame('signup', $rows->first()->name);
        $this->assertSame(3, (int) $rows->first()->count);
        $this->assertSame(2, (int) $rows->first()->visitors);
    }

    public function test_event_goal_conversion_rate_is_session_based(): void
    {
        $site = Site::factory()->create();
        // 4 sessions total; 2 of them fire the goal event
        $converting = Visit::factory()->forSite($site)->count(2)->create();
        Visit::factory()->forSite($site)->count(2)->create();
        foreach ($converting as $v) {
            Event::factory()->forVisit($v)->create(['name' => 'signup', 'visitor_hash' => $v->visitor_hash]);
        }

        $goal = Goal::factory()->forSite($site)->create(['type' => GoalType::Event, 'match_value' => 'signup']);
        $result = $this->agg->conversions($goal, 30);

        $this->assertSame(2, $result['conversions']);
        $this->assertSame(50.0, $result['rate']); // 2 of 4 sessions
    }

    public function test_event_goal_conditions_require_matching_properties(): void
    {
        $site = Site::factory()->create();
        $props = [
            ['plan' => 'pro', 'seats' => 3],
            ['plan' => 'pro', 'seats' => 1],
            ['plan' => 'free', 'seats' => 3],
            null,
        ];
        foreach ($props as $p) {
            $v = Visit::factory()->forSite($site)->create();
            Event::factory()->forVisit($v)->create(['name' => 'signup', 'visitor_hash' => $v->visitor_hash, 'props' => $p]);
        }

        $pro = Goal::factory()->forSite($site)->create(['conditions' => [['property' => 'plan', 'value' => 'pro']]]);
        // Numbers in the props are compared as text, and all conditions must hold
        $proTeam = Goal::factory()->forSite($site)->create(['conditions' => [['property' => 'plan', 'value' => 'pro'], ['property' => 'seats', 'value' => '3']]]);
        $any = Goal::factory()->forSite($site)->create(['conditions' => null]);

        $this->assertSame(2, $this->agg->conversions($pro, 30)['conversions']);
        $this->assertSame(1, $this->agg->conversions($proTeam, 30)['conversions']);
        $this->assertSame(4, $this->agg->conversions($any, 30)['conversions']);
    }

    public function test_goal_value_is_conversions_times_value_per_conversion(): void
    {
        $site = Site::factory()->create();
        foreach (Visit::factory()->forSite($site)->count(3)->create() as $v) {
            Event::factory()->forVisit($v)->create(['name' => 'signup', 'visitor_hash' => $v->visitor_hash]);
        }

        $withValue = Goal::factory()->forSite($site)->create(['value' => 12.5, 'currency' => 'CHF']);
        $withoutValue = Goal::factory()->forSite($site)->create();

        $this->assertSame(['value' => 37.5, 'currency' => 'CHF'], array_intersect_key($this->agg->conversions($withValue, 30), ['value' => 0, 'currency' => 0]));
        $this->assertNull($this->agg->conversions($withoutValue, 30)['value']);
    }

    public function test_pageview_goal_prefix_matching(): void
    {
        $site = Site::factory()->create();
        $v1 = Visit::factory()->forSite($site)->create();
        PageView::factory()->forVisit($v1)->create(['path' => '/blog/post-1']);
        $v2 = Visit::factory()->forSite($site)->create();
        PageView::factory()->forVisit($v2)->create(['path' => '/blogx']); // must NOT match /blog/*

        $goal = Goal::factory()->forSite($site)->create(['type' => GoalType::Pageview, 'match_value' => '/blog/*']);
        $this->assertSame(1, $this->agg->conversions($goal, 30)['conversions']);
    }

    public function test_pageview_goal_exact_matching(): void
    {
        $site = Site::factory()->create();
        $v = Visit::factory()->forSite($site)->create();
        PageView::factory()->forVisit($v)->create(['path' => '/danke']);
        $goal = Goal::factory()->forSite($site)->create(['type' => GoalType::Pageview, 'match_value' => '/danke']);
        $this->assertSame(1, $this->agg->conversions($goal, 30)['conversions']);
    }

    public function test_conversions_by_campaign_groups_first_touch_source(): void
    {
        $site = Site::factory()->create();
        $g = Visit::factory()->forSite($site)->create(['utm_source' => 'google']);
        $n = Visit::factory()->forSite($site)->create(['utm_source' => 'newsletter']);
        Event::factory()->forVisit($g)->create(['name' => 'signup']);
        Event::factory()->forVisit($n)->create(['name' => 'signup']);

        $goal = Goal::factory()->forSite($site)->create(['type' => GoalType::Event, 'match_value' => 'signup']);
        $rows = $this->agg->conversionsByCampaign($goal, 30);
        $this->assertSame(2, $rows->count());
    }

    public function test_rate_is_zero_when_no_sessions(): void
    {
        $site = Site::factory()->create();
        $goal = Goal::factory()->forSite($site)->create(['type' => GoalType::Event, 'match_value' => 'signup']);
        $this->assertSame(0.0, $this->agg->conversions($goal, 30)['rate']);
    }
}
