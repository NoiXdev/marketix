<?php

namespace Tests\Feature\Demo;

use App\Enums\RedirectType;
use App\Enums\UrlStatus;
use App\Models\Domain;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoLinkTargetTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Project $project;

    private Domain $domain;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'demo.enabled' => true,
            'demo.allowed_target_hosts' => ['example.com'],
        ]);

        $this->user = User::factory()->create();
        $this->project = Project::create(['name' => 'Demo', 'locked' => false]);
        $this->project->users()->attach($this->user, ['role' => 'admin', 'active' => true]);
        $this->domain = Domain::create([
            'project_id' => $this->project->id,
            'name' => 'links.example.com',
        ]);
    }

    /** @param array<string, mixed> $overrides */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'domain_id' => $this->domain->id,
            'slug' => 'promo',
            'url' => 'https://example.com/promo',
            'type' => RedirectType::cases()[0]->value,
            'status' => UrlStatus::ACTIVATED->value,
        ], $overrides);
    }

    public function test_allowed_target_is_accepted(): void
    {
        $this->actingAs($this->user)
            ->post(route('app.project.links.store', ['project' => $this->project->id]), $this->payload())
            ->assertSessionHasNoErrors();
    }

    public function test_foreign_target_is_rejected(): void
    {
        $this->actingAs($this->user)
            ->post(
                route('app.project.links.store', ['project' => $this->project->id]),
                $this->payload(['url' => 'https://evil-phishing.test/login'])
            )
            ->assertSessionHasErrors('url');
    }

    public function test_foreign_geo_targeting_destination_is_rejected(): void
    {
        $this->actingAs($this->user)
            ->post(
                route('app.project.links.store', ['project' => $this->project->id]),
                $this->payload(['targeting_geo' => [
                    ['country' => 'DE', 'url' => 'https://evil-phishing.test/de'],
                ]])
            )
            ->assertSessionHasErrors('targeting_geo.0.url');
    }

    public function test_no_restriction_when_demo_mode_is_off(): void
    {
        config(['demo.enabled' => false]);

        $this->actingAs($this->user)
            ->post(
                route('app.project.links.store', ['project' => $this->project->id]),
                $this->payload(['url' => 'https://anywhere.test/page'])
            )
            ->assertSessionHasNoErrors();
    }
}
