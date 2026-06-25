<?php

namespace Tests\Feature;

use App\Enums\RedirectType;
use App\Enums\UrlStatus;
use App\Models\Domain;
use App\Models\Project;
use App\Models\Statistic;
use App\Models\Url;
use App\Models\User;
use App\Reports\ReportDataService;
use App\Reports\ReportDateRange;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ReportDataServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        CarbonImmutable::setTestNow('2026-06-18 12:00:00');
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    private function createUrl(Project $project, string $slug = 'go'): Url
    {
        $user = User::factory()->create();
        $domain = Domain::create([
            'project_id' => $project->id,
            'name' => 'acme.test',
        ]);

        return Url::create([
            'project_id' => $project->id,
            'domain_id' => $domain->id,
            'user_id' => $user->id,
            'slug' => $slug,
            'url' => 'https://example.test',
            'type' => RedirectType::cases()[0],
            'status' => UrlStatus::ACTIVATED,
            'archived' => false,
            'targeting_geo' => [],
            'targeting_device' => [],
            'targeting_language' => [],
            'targeting_ab' => [],
        ]);
    }

    public function test_for_project_assembles_kpis_series_and_top_links(): void
    {
        $project = Project::factory()->create(['name' => 'Acme']);
        $url = $this->createUrl($project, 'go');

        Statistic::factory()->forUrl($url)->create(['created_at' => now(), 'visitor_hash' => hash('sha256', '1.1.1.1'), 'country' => 'Germany', 'browser' => 'Chrome']);
        Statistic::factory()->forUrl($url)->create(['created_at' => now(), 'visitor_hash' => hash('sha256', '2.2.2.2'), 'country' => 'Germany', 'browser' => 'Firefox']);

        $data = app(ReportDataService::class)->forProject($project, ReportDateRange::preset(30));

        $this->assertSame('project', $data->scope);
        $this->assertStringContainsString('Acme', $data->title);
        $this->assertSame('Last 30 days', $data->rangeLabel);
        $this->assertSame(2, $data->totalClicks);
        $this->assertSame(2, $data->uniqueClicks);
        $this->assertCount(30, $data->timeSeries);
        $this->assertSame(['label' => 'Germany', 'count' => 2], $data->breakdowns['country'][0]);
        $this->assertSame('go', $data->topLinks[0]['slug']);
        $this->assertSame(2, $data->topLinks[0]['clicks']);
    }

    public function test_for_url_includes_recent_clicks_and_scopes_to_url(): void
    {
        $project = Project::factory()->create();
        $url = $this->createUrl($project, 'one');

        // Create a second URL in the same project via a different domain
        $user = User::factory()->create();
        $domain2 = Domain::create([
            'project_id' => $project->id,
            'name' => 'other.test',
        ]);
        $other = Url::create([
            'project_id' => $project->id,
            'domain_id' => $domain2->id,
            'user_id' => $user->id,
            'slug' => 'two',
            'url' => 'https://other.test',
            'type' => RedirectType::cases()[0],
            'status' => UrlStatus::ACTIVATED,
            'archived' => false,
            'targeting_geo' => [],
            'targeting_device' => [],
            'targeting_language' => [],
            'targeting_ab' => [],
        ]);

        Statistic::factory()->forUrl($url)->create(['created_at' => now(), 'visitor_hash' => hash('sha256', '1.1.1.1')]);
        Statistic::factory()->forUrl($other)->create(['created_at' => now(), 'visitor_hash' => hash('sha256', '3.3.3.3')]);

        $data = app(ReportDataService::class)->forUrl($url, ReportDateRange::preset(7));

        $this->assertSame('link', $data->scope);
        $this->assertStringContainsString('one', $data->title);
        $this->assertSame(1, $data->totalClicks);
        $this->assertCount(1, $data->recentClicks);
    }
}
