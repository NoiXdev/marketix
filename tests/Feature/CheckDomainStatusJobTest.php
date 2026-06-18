<?php

namespace Tests\Feature;

use App\Jobs\CheckDomainStatusJob;
use App\Models\Domain;
use App\Models\Project;
use App\Services\DomainStatusChecker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckDomainStatusJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_persists_checker_results(): void
    {
        $this->app->instance(DomainStatusChecker::class, new class extends DomainStatusChecker {
            public function __construct() {}

            public function check(Domain $domain): array
            {
                return [
                    'dns_ok' => true,
                    'reachable_ok' => false,
                    'ssl_ok' => true,
                    'check_details' => ['dns' => ['domain_ips' => ['1.2.3.4']]],
                ];
            }
        });

        $project = Project::factory()->create();
        $domain = Domain::factory()->create(['project_id' => $project->id]);

        (new CheckDomainStatusJob($domain))->handle(app(DomainStatusChecker::class));

        $domain->refresh();
        $this->assertTrue($domain->dns_ok);
        $this->assertFalse($domain->reachable_ok);
        $this->assertTrue($domain->ssl_ok);
        $this->assertNotNull($domain->last_checked_at);
        $this->assertSame(['1.2.3.4'], $domain->check_details['dns']['domain_ips']);
    }
}
