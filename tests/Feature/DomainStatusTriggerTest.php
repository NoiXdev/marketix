<?php

namespace Tests\Feature;

use App\Jobs\CheckDomainStatusJob;
use App\Models\Domain;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DomainStatusTriggerTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_domain_dispatches_a_status_check(): void
    {
        Queue::fake();

        $project = Project::factory()->create();
        $domain = Domain::factory()->create(['project_id' => $project->id]);

        Queue::assertPushed(CheckDomainStatusJob::class, fn (CheckDomainStatusJob $job) => $job->domain->is($domain));
    }
}
