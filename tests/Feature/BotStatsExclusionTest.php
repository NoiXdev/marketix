<?php

namespace Tests\Feature;

use App\Enums\RedirectType;
use App\Enums\UrlStatus;
use App\Models\Domain;
use App\Models\Project;
use App\Models\Statistic;
use App\Models\Url;
use App\Models\User;
use App\Services\StatisticsAggregator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BotStatsExclusionTest extends TestCase
{
    use RefreshDatabase;

    public function test_aggregations_count_only_human_clicks(): void
    {
        $project = Project::create(['name' => 'Acme']);

        Statistic::factory()->count(3)->forProject($project)
            ->state(['country' => 'Germany', 'country_code' => 'DE'])->create();
        Statistic::factory()->count(2)->forProject($project)->bot()
            ->state(['country' => 'Germany', 'country_code' => 'DE'])->create();

        $agg = app(StatisticsAggregator::class);

        $this->assertSame(3, $agg->totalClicks($project->id, null));
        $this->assertSame(3, $agg->uniqueClicks($project->id, null));
        $this->assertSame(3, (int) $agg->breakdown($project->id, null, 'country')->firstWhere('country', 'Germany')->count);
        $this->assertSame(3, (int) $agg->breakdownByCountryCode($project->id, null)->first()->count);
        $this->assertCount(3, $agg->recentClicks($project->id, null));
    }

    public function test_statistics_page_top_links_excludes_bots(): void
    {
        // Auth + membership: reuse the helper pattern from ClicksByCountryPropTest.
        $user = User::factory()->create();
        $project = Project::create(['name' => 'Acme']);
        $user->projects()->attach($project);

        $domain = Domain::create(['project_id' => $project->id, 'name' => 'links.test']);
        $link = Url::create([
            'project_id' => $project->id,
            'domain_id' => $domain->id,
            'user_id' => $user->id,
            'slug' => 'promo',
            'url' => 'https://example.com',
            'type' => RedirectType::cases()[0],
            'status' => UrlStatus::ACTIVATED,
            'archived' => false,
        ]);

        Statistic::factory()->count(2)->forUrl($link)->create();
        Statistic::factory()->count(1)->forUrl($link)->bot()->create();

        $this->actingAs($user)
            ->get(route('app.project.statistics', ['project' => $project->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Statistics/Index')
                ->has('topLinks', 1, fn (Assert $row) => $row
                    ->where('clicks', 2)
                    ->etc()
                )
            );
    }
}
