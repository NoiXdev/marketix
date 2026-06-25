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
use Tests\TestCase;

class UniqueClicksAggregationTest extends TestCase
{
    use RefreshDatabase;

    public function test_unique_clicks_counts_distinct_visitor_hashes(): void
    {
        $user = User::factory()->create();
        $project = Project::create(['name' => 'Acme']);
        $domain = Domain::create(['project_id' => $project->id, 'name' => 'links.test']);
        $url = Url::create([
            'project_id' => $project->id,
            'domain_id' => $domain->id,
            'user_id' => $user->id,
            'slug' => 'promo',
            'url' => 'https://example.com/default',
            'type' => RedirectType::cases()[0],
            'status' => UrlStatus::ACTIVATED,
            'archived' => false,
        ]);

        // 3 rows, 2 distinct visitors.
        Statistic::factory()->forUrl($url)->create(['visitor_hash' => 'visitor-a']);
        Statistic::factory()->forUrl($url)->create(['visitor_hash' => 'visitor-a']);
        Statistic::factory()->forUrl($url)->create(['visitor_hash' => 'visitor-b']);

        $unique = app(StatisticsAggregator::class)->uniqueClicks($project->id, $url->id);

        $this->assertSame(2, $unique);
    }
}
