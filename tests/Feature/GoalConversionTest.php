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
