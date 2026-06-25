<?php

namespace Tests\Unit;

use App\Enums\RedirectType;
use App\Enums\UrlStatus;
use App\Models\Domain;
use App\Models\Project;
use App\Models\Statistic;
use App\Models\Url;
use App\Models\User;
use App\Services\StatisticsAggregator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StatisticsAggregatorTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: Project, 1: User}
     */
    private function makeProjectAndUser(): array
    {
        $user = User::factory()->create();
        $project = Project::create(['name' => 'Acme']);
        $user->projects()->attach($project);

        return [$project, $user];
    }

    private function makeUrlInProject(Project $project, User $user, string $slug): Url
    {
        $domain = Domain::firstOrCreate(['project_id' => $project->id, 'name' => 'links.test']);

        return Url::create([
            'project_id' => $project->id,
            'domain_id' => $domain->id,
            'user_id' => $user->id,
            'slug' => $slug,
            'url' => 'https://example.com/'.$slug,
            'type' => RedirectType::cases()[0],
            'status' => UrlStatus::ACTIVATED,
            'archived' => false,
        ]);
    }

    private function makeUrl(string $slug = 'promo'): Url
    {
        [$project, $user] = $this->makeProjectAndUser();

        return $this->makeUrlInProject($project, $user, $slug);
    }

    public function test_clicks_by_day_zero_fills_the_window_with_total_and_unique(): void
    {
        $url = $this->makeUrl();

        Statistic::factory()->forUrl($url)->create(['visitor_hash' => hash('sha256', '10.0.0.1'), 'created_at' => now()]);
        Statistic::factory()->forUrl($url)->create(['visitor_hash' => hash('sha256', '10.0.0.1'), 'created_at' => now()]);
        Statistic::factory()->forUrl($url)->create(['visitor_hash' => hash('sha256', '10.0.0.2'), 'created_at' => now()]);

        $aggregator = new StatisticsAggregator;
        $byDay = $aggregator->clicksByDay($url->project_id, $url->id, 7);

        $this->assertCount(7, $byDay);
        $this->assertSame(now()->subDays(6)->format('Y-m-d'), $byDay[0]['date']);
        $this->assertSame(now()->format('Y-m-d'), $byDay[6]['date']);
        $this->assertSame(3, $byDay[6]['clicks']);
        $this->assertSame(2, $byDay[6]['unique']);
        $this->assertSame(0, $byDay[0]['clicks']);
        $this->assertSame(0, $byDay[0]['unique']);
    }

    public function test_breakdown_is_scoped_to_the_url_and_orders_by_count(): void
    {
        [$project, $user] = $this->makeProjectAndUser();
        $url = $this->makeUrlInProject($project, $user, 'a');
        $other = $this->makeUrlInProject($project, $user, 'b');

        Statistic::factory()->forUrl($url)->country('Germany')->create();
        Statistic::factory()->forUrl($url)->country('Germany')->create();
        Statistic::factory()->forUrl($url)->country('France')->create();
        Statistic::factory()->forUrl($other)->country('Spain')->create();

        $rows = (new StatisticsAggregator)->breakdown($url->project_id, $url->id, 'country');

        $this->assertSame('Germany', $rows[0]->country);
        $this->assertSame(2, (int) $rows[0]->count);
        $this->assertSame('France', $rows[1]->country);
        $this->assertCount(2, $rows);
    }

    public function test_recent_clicks_returns_latest_first_and_respects_limit(): void
    {
        $url = $this->makeUrl();

        Statistic::factory()->forUrl($url)->create(['city' => 'Old', 'created_at' => now()->subDay()]);
        Statistic::factory()->forUrl($url)->create(['city' => 'New', 'created_at' => now()]);

        $aggregator = new StatisticsAggregator;
        $rows = $aggregator->recentClicks($url->project_id, $url->id, null, null, 1);

        $this->assertCount(1, $rows);
        $this->assertSame('New', $rows[0]->city);
    }

    public function test_total_and_unique_clicks_respect_url_scope_and_window(): void
    {
        [$project, $user] = $this->makeProjectAndUser();
        $url = $this->makeUrlInProject($project, $user, 'a');
        $other = $this->makeUrlInProject($project, $user, 'b');

        // url: 3 clicks today from 2 distinct visitor_hashes.
        Statistic::factory()->forUrl($url)->create(['visitor_hash' => hash('sha256', '10.0.0.1'), 'created_at' => now()]);
        Statistic::factory()->forUrl($url)->create(['visitor_hash' => hash('sha256', '10.0.0.1'), 'created_at' => now()]);
        Statistic::factory()->forUrl($url)->create(['visitor_hash' => hash('sha256', '10.0.0.2'), 'created_at' => now()]);
        // url: 1 old click (40 days ago, outside a 7-day window).
        Statistic::factory()->forUrl($url)->create(['visitor_hash' => hash('sha256', '10.0.0.9'), 'created_at' => now()->subDays(40)]);
        // other url in same project: must NOT leak into url-scoped totals.
        Statistic::factory()->forUrl($other)->create(['visitor_hash' => hash('sha256', '10.0.0.3'), 'created_at' => now()]);

        $agg = new StatisticsAggregator;

        // All-time, url-scoped: 4 total, 3 unique.
        $this->assertSame(4, $agg->totalClicks($project->id, $url->id));
        $this->assertSame(3, $agg->uniqueClicks($project->id, $url->id));

        // Windowed (last 7 days) excludes the 40-day-old click: 3 total, 2 unique.
        $since = now()->subDays(6)->startOfDay();
        $this->assertSame(3, $agg->totalClicks($project->id, $url->id, $since));
        $this->assertSame(2, $agg->uniqueClicks($project->id, $url->id, $since));

        // Project-scoped (urlId null) all-time includes the other url's click: 5 total.
        $this->assertSame(5, $agg->totalClicks($project->id, null));
    }

    public function test_breakdown_rejects_an_unknown_column(): void
    {
        $url = $this->makeUrl();

        $this->expectException(\InvalidArgumentException::class);
        (new StatisticsAggregator)->breakdown($url->project_id, $url->id, 'ip');
    }
}
