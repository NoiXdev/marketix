<?php

namespace Tests\Feature\Mcp;

use App\Mcp\Servers\MarketixServer;
use App\Mcp\Tools\GetLinkStatsTool;
use App\Mcp\Tools\ListDomainsTool;
use App\Mcp\Tools\ListLinksTool;
use App\Mcp\Tools\ListProjectsTool;
use App\Mcp\Tools\ListQrCodesTool;
use App\Models\Domain;
use App\Models\Project;
use App\Models\QrCode;
use App\Models\Statistic;
use App\Models\Url;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReadToolsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Build a project (with a domain, a url, and a qr code) owned by a given
     * user. FK gotcha: user_id/project_id must be set explicitly on the Url
     * and QrCode fixtures since their factories/creators don't infer them.
     *
     * @return array{user: User, project: Project, domain: Domain, url: Url, qrCode: QrCode}
     */
    private function makeProjectWithData(string $projectName, string $role = 'admin'): array
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['name' => $projectName]);
        $project->users()->attach($user->id, ['role' => $role, 'active' => true]);

        $domain = Domain::factory()->create([
            'project_id' => $project->id,
            'name' => strtolower($projectName).'.example',
        ]);

        $url = Url::factory()->create([
            'project_id' => $project->id,
            'domain_id' => $domain->id,
            'user_id' => $user->id,
            'slug' => strtolower($projectName).'-slug',
        ]);

        $qrCode = QrCode::create([
            'project_id' => $project->id,
            'url_id' => $url->id,
            'name' => $projectName.' QR',
            'type' => 'link',
            'is_dynamic' => true,
            'content' => ['url' => $url->url],
            'style' => [],
        ]);

        return compact('user', 'project', 'domain', 'url', 'qrCode');
    }

    public function test_list_projects_only_returns_the_callers_projects(): void
    {
        $mine = $this->makeProjectWithData('Mine');
        $theirs = $this->makeProjectWithData('Theirs');

        MarketixServer::actingAs($mine['user'])
            ->tool(ListProjectsTool::class, [])
            ->assertOk()
            ->assertSee('Mine')
            ->assertDontSee('Theirs');
    }

    public function test_list_links_returns_links_for_an_accessible_project(): void
    {
        $mine = $this->makeProjectWithData('Mine');

        MarketixServer::actingAs($mine['user'])
            ->tool(ListLinksTool::class, ['project' => $mine['project']->id])
            ->assertOk()
            ->assertSee($mine['url']->slug)
            ->assertSee($mine['domain']->name);
    }

    public function test_list_links_denies_access_to_a_project_the_caller_does_not_belong_to(): void
    {
        $mine = $this->makeProjectWithData('Mine');
        $theirs = $this->makeProjectWithData('Theirs');

        MarketixServer::actingAs($mine['user'])
            ->tool(ListLinksTool::class, ['project' => $theirs['project']->id])
            ->assertHasErrors(['Project not found or access denied.']);
    }

    public function test_list_domains_returns_domains_for_an_accessible_project(): void
    {
        $mine = $this->makeProjectWithData('Mine');

        MarketixServer::actingAs($mine['user'])
            ->tool(ListDomainsTool::class, ['project' => $mine['project']->id])
            ->assertOk()
            ->assertSee($mine['domain']->name);
    }

    public function test_list_domains_denies_access_to_a_project_the_caller_does_not_belong_to(): void
    {
        $mine = $this->makeProjectWithData('Mine');
        $theirs = $this->makeProjectWithData('Theirs');

        MarketixServer::actingAs($mine['user'])
            ->tool(ListDomainsTool::class, ['project' => $theirs['project']->id])
            ->assertHasErrors(['Project not found or access denied.']);
    }

    public function test_list_qr_codes_returns_qr_codes_for_an_accessible_project(): void
    {
        $mine = $this->makeProjectWithData('Mine');

        MarketixServer::actingAs($mine['user'])
            ->tool(ListQrCodesTool::class, ['project' => $mine['project']->id])
            ->assertOk()
            ->assertSee($mine['qrCode']->name);
    }

    public function test_list_qr_codes_denies_access_to_a_project_the_caller_does_not_belong_to(): void
    {
        $mine = $this->makeProjectWithData('Mine');
        $theirs = $this->makeProjectWithData('Theirs');

        MarketixServer::actingAs($mine['user'])
            ->tool(ListQrCodesTool::class, ['project' => $theirs['project']->id])
            ->assertHasErrors(['Project not found or access denied.']);
    }

    public function test_get_link_stats_returns_aggregated_stats_for_an_accessible_link(): void
    {
        $mine = $this->makeProjectWithData('Mine');

        Statistic::factory()->forUrl($mine['url'])->create([
            'country' => 'Germany',
            'browser' => 'Chrome',
            'os' => 'Windows',
            'domain' => 'referrer.example',
            'is_bot' => false,
        ]);
        Statistic::factory()->forUrl($mine['url'])->create([
            'country' => 'Germany',
            'browser' => 'Chrome',
            'os' => 'Windows',
            'domain' => 'referrer.example',
            'is_bot' => false,
        ]);

        MarketixServer::actingAs($mine['user'])
            ->tool(GetLinkStatsTool::class, ['link_id' => $mine['url']->id])
            ->assertOk()
            ->assertSee('Germany')
            ->assertSee('Chrome')
            ->assertSee('Windows')
            ->assertSee('referrer.example');
    }

    public function test_get_link_stats_denies_access_to_a_link_in_another_project(): void
    {
        $mine = $this->makeProjectWithData('Mine');
        $theirs = $this->makeProjectWithData('Theirs');

        MarketixServer::actingAs($mine['user'])
            ->tool(GetLinkStatsTool::class, ['link_id' => $theirs['url']->id])
            ->assertHasErrors(['Link not found or access denied.']);
    }
}
