<?php

namespace Tests\Feature\Mcp;

use App\Mcp\Servers\MarketixServer;
use App\Mcp\Tools\GetSiteBreakdownTool;
use App\Mcp\Tools\GetSiteStatsTool;
use App\Mcp\Tools\ListSitesTool;
use App\Models\PageView;
use App\Models\Project;
use App\Models\Site;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalyticsToolsTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{user: User, project: Project, site: Site} */
    private function makeSite(string $name): array
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['name' => $name]);
        $project->users()->attach($user->id, ['role' => 'member', 'active' => true]);
        $site = Site::factory()->create(['project_id' => $project->id, 'name' => "{$name} Shop", 'domain' => strtolower($name).'.example']);

        return compact('user', 'project', 'site');
    }

    private function track(Site $site, array $visit, array $pageViews): void
    {
        $model = Visit::factory()->forSite($site)->create($visit);
        foreach ($pageViews as $attributes) {
            PageView::factory()->forVisit($model)->create(['visitor_hash' => $model->visitor_hash, ...$attributes]);
        }
    }

    public function test_list_sites_returns_the_projects_sites_only(): void
    {
        $mine = $this->makeSite('Mine');
        $theirs = $this->makeSite('Theirs');

        MarketixServer::actingAs($mine['user'])
            ->tool(ListSitesTool::class, ['project' => $mine['project']->id])
            ->assertOk()
            ->assertSee('mine.example')
            ->assertDontSee('theirs.example');

        MarketixServer::actingAs($mine['user'])
            ->tool(ListSitesTool::class, ['project' => $theirs['project']->id])
            ->assertHasErrors(['Project not found or access denied.']);
    }

    public function test_site_stats_report_totals_top_pages_and_visitor_types(): void
    {
        $mine = $this->makeSite('Mine');
        $this->track($mine['site'], ['visitor_hash' => 'a', 'pageview_count' => 2, 'is_returning' => false], [
            ['path' => '/pricing', 'title' => 'Pricing', 'country_code' => 'CH'],
            ['path' => '/', 'title' => 'Home', 'country_code' => 'CH'],
        ]);
        $this->track($mine['site'], ['visitor_hash' => 'b', 'pageview_count' => 1, 'is_returning' => true], [
            ['path' => '/pricing', 'title' => 'Pricing', 'country_code' => 'DE'],
        ]);

        MarketixServer::actingAs($mine['user'])
            ->tool(GetSiteStatsTool::class, ['site' => 'mine.example', 'range' => '7d'])
            ->assertOk()
            ->assertSee('"page_views": 3')
            ->assertSee('"visitors": 2')
            ->assertSee('"sessions": 2')
            ->assertSee('"bounce_rate": 50')
            ->assertSee('"title": "Pricing"')
            ->assertSee('"type": "returning"')
            ->assertSee('"range": "7d"');
    }

    public function test_site_stats_apply_filters(): void
    {
        $mine = $this->makeSite('Mine');
        $this->track($mine['site'], ['visitor_hash' => 'a'], [['path' => '/', 'country_code' => 'CH']]);
        $this->track($mine['site'], ['visitor_hash' => 'b'], [['path' => '/', 'country_code' => 'DE'], ['path' => '/x', 'country_code' => 'DE']]);

        MarketixServer::actingAs($mine['user'])
            ->tool(GetSiteStatsTool::class, ['site' => $mine['site']->id, 'filters' => ['country_code' => 'DE']])
            ->assertOk()
            ->assertSee('"page_views": 2')
            ->assertSee('"country_code": "DE"');
    }

    public function test_site_tools_deny_sites_in_other_projects(): void
    {
        $mine = $this->makeSite('Mine');
        $theirs = $this->makeSite('Theirs');

        MarketixServer::actingAs($mine['user'])
            ->tool(GetSiteStatsTool::class, ['site' => $theirs['site']->id])
            ->assertHasErrors(['Site not found, ambiguous or access denied. Pass the site id from list_sites.']);

        MarketixServer::actingAs($mine['user'])
            ->tool(GetSiteBreakdownTool::class, ['site' => 'theirs.example', 'dimension' => 'pages'])
            ->assertHasErrors(['Site not found, ambiguous or access denied. Pass the site id from list_sites.']);
    }

    public function test_site_breakdown_ranks_a_dimension(): void
    {
        $mine = $this->makeSite('Mine');
        $this->track($mine['site'], ['visitor_hash' => 'a'], [
            ['path' => '/', 'city' => 'Zürich', 'region' => 'Zurich', 'country_code' => 'CH'],
            ['path' => '/a', 'city' => 'Zürich', 'region' => 'Zurich', 'country_code' => 'CH'],
            ['path' => '/b', 'city' => 'Bern', 'region' => 'Bern', 'country_code' => 'CH'],
        ]);

        MarketixServer::actingAs($mine['user'])
            ->tool(GetSiteBreakdownTool::class, ['site' => 'Mine Shop', 'dimension' => 'cities', 'limit' => 1])
            ->assertOk()
            ->assertSee('"dimension": "cities"')
            ->assertSee('"city": "Zürich"')
            ->assertDontSee('"city": "Bern"');
    }

    public function test_site_breakdown_rejects_unknown_dimensions(): void
    {
        $mine = $this->makeSite('Mine');

        MarketixServer::actingAs($mine['user'])
            ->tool(GetSiteBreakdownTool::class, ['site' => $mine['site']->id, 'dimension' => 'passwords'])
            ->assertHasErrors();
    }
}
