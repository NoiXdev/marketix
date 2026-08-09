<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\Project;
use App\Models\Statistic;
use App\Models\Url;
use App\Models\User;
use App\Services\StatisticsAggregator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTopLinksTest extends TestCase
{
    use RefreshDatabase;

    public function test_top_links_ranks_by_period_clicks_excluding_bots_and_old_rows(): void
    {
        // Url::create() relies on UrlObserver to set the required user_id from
        // auth()->id() when not given; pass it explicitly so the FK is satisfied
        // without mutating global auth state.
        $creator = User::factory()->create();

        $project = Project::create(['name' => 'Acme']);
        $domain = Domain::firstOrCreate(['project_id' => $project->id, 'name' => 'links.test']);
        $a = Url::create(['project_id' => $project->id, 'domain_id' => $domain->id, 'user_id' => $creator->id, 'slug' => 'aaa', 'url' => 'https://a.example', 'type' => 0, 'status' => 1, 'archived' => false]);
        $b = Url::create(['project_id' => $project->id, 'domain_id' => $domain->id, 'user_id' => $creator->id, 'slug' => 'bbb', 'url' => 'https://b.example', 'type' => 0, 'status' => 1, 'archived' => false]);

        // in-period: a=3, b=1; plus a bot (excluded) and an out-of-window row (excluded)
        Statistic::factory()->forUrl($a)->count(3)->create(['created_at' => now()->subDay()]);
        Statistic::factory()->forUrl($b)->create(['created_at' => now()->subDay()]);
        Statistic::factory()->forUrl($a)->bot()->create(['created_at' => now()->subDay()]);
        Statistic::factory()->forUrl($a)->create(['created_at' => now()->subDays(60)]);

        $rows = (new StatisticsAggregator)->topLinks($project->id, now()->subDays(29)->startOfDay(), now(), 5);

        $this->assertSame('aaa', $rows->first()->slug);
        $this->assertSame(3, (int) $rows->first()->clicks);
        $this->assertSame('links.test', $rows->first()->domain_name);
        $this->assertSame(2, $rows->count());
    }
}
