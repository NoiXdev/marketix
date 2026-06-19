<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\Project;
use App\Models\User;
use App\Services\DomainStatusChecker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DomainCheckEndpointTest extends TestCase
{
    use RefreshDatabase;

    private function fakeChecker(): void
    {
        $this->app->instance(DomainStatusChecker::class, new class extends DomainStatusChecker
        {
            public function __construct() {}

            public function check(Domain $domain): array
            {
                return [
                    'dns_ok' => true,
                    'reachable_ok' => true,
                    'ssl_ok' => true,
                    'check_details' => [],
                ];
            }
        });
    }

    public function test_check_endpoint_runs_checker_and_persists(): void
    {
        $this->fakeChecker();

        $user = User::factory()->create();
        $project = Project::factory()->create();
        $project->users()->attach($user, ['role' => 'member', 'active' => true]);
        $domain = Domain::factory()->create(['project_id' => $project->id]);

        $this->actingAs($user)
            ->post(route('app.project.domains.check', ['project' => $project->id, 'domain' => $domain->id]))
            ->assertRedirect(route('app.project.domains.index', ['project' => $project->id]));

        $domain->refresh();
        $this->assertTrue($domain->dns_ok);
        $this->assertNotNull($domain->last_checked_at);
    }

    public function test_cannot_check_another_projects_domain(): void
    {
        $this->fakeChecker();

        $user = User::factory()->create();
        $project = Project::factory()->create();
        $project->users()->attach($user, ['role' => 'member', 'active' => true]);

        $otherProject = Project::factory()->create();
        $otherDomain = Domain::factory()->create(['project_id' => $otherProject->id]);

        $this->actingAs($user)
            ->post(route('app.project.domains.check', ['project' => $project->id, 'domain' => $otherDomain->id]))
            ->assertNotFound();
    }
}
