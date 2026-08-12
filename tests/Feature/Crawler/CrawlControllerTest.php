<?php

namespace Tests\Feature\Crawler;

use App\Jobs\RunCrawlJob;
use App\Models\Crawl;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CrawlControllerTest extends TestCase
{
    use RefreshDatabase;

    private function member(): array
    {
        $user = User::factory()->create();
        $project = Project::factory()->create();
        $project->users()->attach($user, ['role' => 'member', 'active' => true]);

        return [$user, $project];
    }

    public function test_store_creates_crawl_and_dispatches_job(): void
    {
        Queue::fake();
        [$user, $project] = $this->member();

        $this->actingAs($user)
            ->post(route('app.project.crawls.store', ['project' => $project->id]), [
                'start_url' => 'https://example.com',
                'mode' => 'full_site',
                'render_js' => false,
                'respect_robots' => true,
                'include_subdomains' => false,
                'delay_ms' => 0,
            ])
            ->assertRedirect();

        $crawl = Crawl::first();
        $this->assertNotNull($crawl);
        $this->assertSame($project->id, $crawl->project_id);
        Queue::assertPushed(RunCrawlJob::class);
    }

    public function test_store_rejects_private_url(): void
    {
        Queue::fake();
        [$user, $project] = $this->member();

        $this->actingAs($user)
            ->post(route('app.project.crawls.store', ['project' => $project->id]), [
                'start_url' => 'http://127.0.0.1',
                'mode' => 'full_site',
                'delay_ms' => 0,
            ])
            ->assertSessionHasErrors('start_url');

        Queue::assertNotPushed(RunCrawlJob::class);
    }

    public function test_cannot_view_another_projects_crawl(): void
    {
        [$user, $project] = $this->member();
        $other = Project::factory()->create();
        $crawl = Crawl::factory()->for($other)->create();

        $this->actingAs($user)
            ->get(route('app.project.crawls.show', ['project' => $project->id, 'crawl' => $crawl->id]))
            ->assertNotFound();
    }
}
