<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\PageView;
use App\Models\Project;
use App\Models\Site;
use App\Models\User;
use App\Models\Visit;
use App\Services\RealtimeAggregator;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class RealtimeTest extends TestCase
{
    use RefreshDatabase;

    private Site $site;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-07 15:30:20'));
        $this->site = Site::factory()->create();
    }

    private function hit(int $minutesAgo, array $attributes = []): PageView
    {
        $at = CarbonImmutable::now()->subMinutes($minutesAgo);
        $visit = Visit::factory()->forSite($this->site)->create([
            'visitor_hash' => $attributes['visitor_hash'] ?? 'v-'.$minutesAgo,
            'started_at' => $at,
            'last_activity_at' => $at,
            'country_code' => $attributes['country_code'] ?? 'CH',
            'device' => 'Mobile',
            'referer_domain' => $attributes['referer_domain'] ?? null,
            'is_bot' => $attributes['is_bot'] ?? false,
        ]);

        return PageView::factory()->forVisit($visit)->create([
            'visitor_hash' => $visit->visitor_hash,
            'path' => $attributes['path'] ?? '/',
            'country_code' => $visit->country_code,
            'device' => 'Mobile',
            'is_bot' => $visit->is_bot,
            'created_at' => $at,
        ]);
    }

    public function test_snapshot_covers_the_last_thirty_minutes(): void
    {
        $this->hit(0, ['path' => '/pricing']);
        $this->hit(2, ['path' => '/pricing', 'referer_domain' => 'www.google.com']);
        $this->hit(12, ['path' => '/blog', 'country_code' => 'DE']);
        $this->hit(45, ['path' => '/old']);
        $this->hit(1, ['path' => '/bot', 'is_bot' => true]);

        $snapshot = (new RealtimeAggregator)->snapshot($this->site->id);

        $this->assertSame(2, $snapshot['active_now']);
        $this->assertSame(3, $snapshot['visitors']);
        $this->assertSame(3, $snapshot['page_views']);
        $this->assertCount(RealtimeAggregator::WINDOW_MINUTES, $snapshot['minutes']);
        $this->assertSame(['minutes_ago' => 0, 'views' => 1, 'visitors' => 1], end($snapshot['minutes']));
        $this->assertSame(1, $snapshot['minutes'][17]['views']);
        $this->assertSame(['value' => '/pricing', 'count' => 2], $snapshot['pages'][0]);
        $this->assertSame(['CH', 'DE'], array_column($snapshot['countries'], 'value'));
        $this->assertEqualsCanonicalizing(['direct', 'organic_search'], array_column($snapshot['channels'], 'value'));
    }

    public function test_feed_mixes_page_views_and_events_newest_first(): void
    {
        $view = $this->hit(3, ['path' => '/cart']);
        Event::factory()->forVisit($view->visit)->create(['name' => 'purchase', 'path' => '/cart', 'created_at' => now()->subMinute()]);

        $feed = (new RealtimeAggregator)->snapshot($this->site->id)['feed'];

        $this->assertSame(['event', 'pageview'], array_column($feed, 'type'));
        $this->assertSame('purchase', $feed[0]['label']);
        $this->assertSame('CH', $feed[0]['country_code']);
        $this->assertSame(60, $feed[0]['seconds_ago']);
        $this->assertSame(180, $feed[1]['seconds_ago']);
    }

    public function test_realtime_tab_props(): void
    {
        $user = User::factory()->create();
        $project = Project::find($this->site->project_id);
        $user->projects()->attach($project);
        $this->hit(1);

        $this->actingAs($user)
            ->get(route('app.project.analytics.show', ['project' => $project->id, 'site' => $this->site->id, 'tab' => 'realtime']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('tab', 'realtime')
                ->where('realtime.page_views', 1)
                ->has('realtime.minutes', RealtimeAggregator::WINDOW_MINUTES)
                ->has('realtime.feed', 1)
                ->missing('summary')
            );
    }
}
