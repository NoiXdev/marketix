<?php

namespace Tests\Feature\Mcp;

use App\Mcp\Servers\MarketixServer;
use App\Mcp\Tools\CreateLinkTool;
use App\Mcp\Tools\CreateQrCodeTool;
use App\Models\Domain;
use App\Models\Project;
use App\Models\Url;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateToolsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Build a project (with a domain) owned by a given user.
     *
     * @return array{user: User, project: Project, domain: Domain}
     */
    private function makeProject(string $projectName, string $role = 'member'): array
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['name' => $projectName]);
        $project->users()->attach($user->id, ['role' => $role, 'active' => true]);

        $domain = Domain::factory()->create([
            'project_id' => $project->id,
            'name' => strtolower($projectName).'.example',
        ]);

        return compact('user', 'project', 'domain');
    }

    // ── create_link ─────────────────────────────────────────────────────────

    public function test_create_link_persists_a_link_owned_by_the_caller(): void
    {
        $mine = $this->makeProject('Mine');

        MarketixServer::actingAs($mine['user'])
            ->tool(CreateLinkTool::class, [
                'project' => $mine['project']->id,
                'domain' => $mine['domain']->id,
                'url' => 'https://example.com',
                'slug' => 'promo',
            ])
            ->assertOk()
            ->assertSee('promo');

        $this->assertDatabaseHas('urls', [
            'project_id' => $mine['project']->id,
            'domain_id' => $mine['domain']->id,
            'slug' => 'promo',
            'user_id' => $mine['user']->id,
        ]);
    }

    public function test_create_link_generates_a_unique_slug_when_omitted(): void
    {
        $mine = $this->makeProject('Mine');

        MarketixServer::actingAs($mine['user'])
            ->tool(CreateLinkTool::class, [
                'project' => $mine['project']->id,
                'domain' => $mine['domain']->id,
                'url' => 'https://example.com',
            ])
            ->assertOk();

        $this->assertDatabaseCount('urls', 1);
    }

    public function test_create_link_resolves_domain_by_name(): void
    {
        $mine = $this->makeProject('Mine');

        MarketixServer::actingAs($mine['user'])
            ->tool(CreateLinkTool::class, [
                'project' => $mine['project']->id,
                'domain' => $mine['domain']->name,
                'url' => 'https://example.com',
                'slug' => 'byname',
            ])
            ->assertOk();

        $this->assertDatabaseHas('urls', [
            'domain_id' => $mine['domain']->id,
            'slug' => 'byname',
        ]);
    }

    public function test_create_link_rejects_an_invalid_url(): void
    {
        $mine = $this->makeProject('Mine');

        MarketixServer::actingAs($mine['user'])
            ->tool(CreateLinkTool::class, [
                'project' => $mine['project']->id,
                'domain' => $mine['domain']->id,
                'url' => 'not-a-url',
                'slug' => 'bad',
            ])
            ->assertHasErrors();

        $this->assertDatabaseCount('urls', 0);
    }

    public function test_create_link_rejects_a_duplicate_slug_on_the_same_domain(): void
    {
        $mine = $this->makeProject('Mine');

        Url::factory()->create([
            'project_id' => $mine['project']->id,
            'domain_id' => $mine['domain']->id,
            'user_id' => $mine['user']->id,
            'slug' => 'taken',
        ]);

        MarketixServer::actingAs($mine['user'])
            ->tool(CreateLinkTool::class, [
                'project' => $mine['project']->id,
                'domain' => $mine['domain']->id,
                'url' => 'https://example.com',
                'slug' => 'taken',
            ])
            ->assertHasErrors();

        $this->assertDatabaseCount('urls', 1);
    }

    public function test_create_link_denies_a_project_the_caller_cannot_access(): void
    {
        $mine = $this->makeProject('Mine');
        $theirs = $this->makeProject('Theirs');

        MarketixServer::actingAs($mine['user'])
            ->tool(CreateLinkTool::class, [
                'project' => $theirs['project']->id,
                'domain' => $theirs['domain']->id,
                'url' => 'https://example.com',
                'slug' => 'nope',
            ])
            ->assertHasErrors(['Project not found or access denied.']);

        $this->assertDatabaseCount('urls', 0);
    }

    // ── create_qr_code ───────────────────────────────────────────────────────

    public function test_create_qr_code_static_persists_a_link_type_qr_with_default_style(): void
    {
        $mine = $this->makeProject('Mine');

        MarketixServer::actingAs($mine['user'])
            ->tool(CreateQrCodeTool::class, [
                'project' => $mine['project']->id,
                'name' => 'My QR',
                'target_url' => 'https://example.com',
            ])
            ->assertOk()
            ->assertSee('My QR');

        $this->assertDatabaseHas('qr_codes', [
            'project_id' => $mine['project']->id,
            'name' => 'My QR',
            'type' => 'link',
            'is_dynamic' => false,
            'url_id' => null,
        ]);

        $qrCode = $mine['project']->qrCodes()->where('name', 'My QR')->first();
        $this->assertNotEmpty($qrCode->style);
        $this->assertSame('#000000', $qrCode->style['foreground']);
    }

    public function test_create_qr_code_dynamic_creates_a_backing_link_and_the_qr_code(): void
    {
        $mine = $this->makeProject('Mine');

        MarketixServer::actingAs($mine['user'])
            ->tool(CreateQrCodeTool::class, [
                'project' => $mine['project']->id,
                'name' => 'Dynamic QR',
                'target_url' => 'https://example.com',
                'dynamic' => true,
                'domain' => $mine['domain']->id,
                'slug' => 'dynamic-qr',
            ])
            ->assertOk()
            ->assertSee('Dynamic QR')
            ->assertSee($mine['domain']->name);

        $this->assertDatabaseHas('urls', [
            'project_id' => $mine['project']->id,
            'domain_id' => $mine['domain']->id,
            'slug' => 'dynamic-qr',
            'url' => 'https://example.com',
            'user_id' => $mine['user']->id,
        ]);

        $this->assertDatabaseHas('qr_codes', [
            'project_id' => $mine['project']->id,
            'name' => 'Dynamic QR',
            'is_dynamic' => true,
        ]);
    }

    public function test_create_qr_code_dynamic_without_domain_returns_an_error(): void
    {
        $mine = $this->makeProject('Mine');

        MarketixServer::actingAs($mine['user'])
            ->tool(CreateQrCodeTool::class, [
                'project' => $mine['project']->id,
                'name' => 'No Domain',
                'target_url' => 'https://example.com',
                'dynamic' => true,
            ])
            ->assertHasErrors();

        $this->assertDatabaseCount('qr_codes', 0);
        $this->assertDatabaseCount('urls', 0);
    }

    public function test_create_qr_code_denies_a_project_the_caller_cannot_access(): void
    {
        $mine = $this->makeProject('Mine');
        $theirs = $this->makeProject('Theirs');

        MarketixServer::actingAs($mine['user'])
            ->tool(CreateQrCodeTool::class, [
                'project' => $theirs['project']->id,
                'name' => 'Nope',
                'target_url' => 'https://example.com',
            ])
            ->assertHasErrors(['Project not found or access denied.']);

        $this->assertDatabaseCount('qr_codes', 0);
    }
}
