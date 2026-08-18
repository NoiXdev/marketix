<?php

namespace Tests\Feature\Demo;

use App\Enums\CrawlMode;
use App\Models\Crawl;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DemoCrawlQuotaTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();

        config([
            'demo.enabled' => true,
            'demo.crawl.max_pages' => 25,
            'demo.crawl.max_per_host' => 5,
            'demo.crawl.max_concurrent' => 1,
        ]);

        $this->user = User::factory()->create();
        $this->project = Project::create(['name' => 'Demo', 'locked' => false]);
        $this->project->users()->attach($this->user, ['role' => 'admin', 'active' => true]);
    }

    /** @param array<string, mixed> $overrides */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'start_url' => 'https://example.com',
            'mode' => CrawlMode::FullSite->value,
            'delay_ms' => 0,
        ], $overrides);
    }

    public function test_max_pages_is_clamped_not_rejected(): void
    {
        $this->actingAs($this->user)
            ->post(
                route('app.project.crawls.store', ['project' => $this->project->id]),
                $this->payload(['max_pages' => 5000])
            )
            ->assertSessionHasNoErrors();

        // latest('id') rather than latest(): created_at has second precision,
        // so rows created within the same test can share a timestamp. The
        // ULID primary key is monotonically sortable and actually
        // distinguishes them.
        $this->assertSame(25, Crawl::query()->latest('id')->first()->max_pages);
    }

    public function test_sixth_crawl_of_the_same_host_is_rejected(): void
    {
        Crawl::factory()->count(5)->create([
            'project_id' => $this->project->id,
            'start_url' => 'https://example.com/x',
            'status' => 'completed',
        ]);

        $this->actingAs($this->user)
            ->post(route('app.project.crawls.store', ['project' => $this->project->id]), $this->payload())
            ->assertSessionHasErrors('start_url');
    }

    public function test_a_second_concurrent_crawl_is_rejected(): void
    {
        Crawl::factory()->create([
            'project_id' => $this->project->id,
            'start_url' => 'https://other.example.org',
            'status' => 'running',
        ]);

        $this->actingAs($this->user)
            ->post(route('app.project.crawls.store', ['project' => $this->project->id]), $this->payload())
            ->assertSessionHasErrors('start_url');
    }

    public function test_no_quota_when_demo_mode_is_off(): void
    {
        config(['demo.enabled' => false]);

        Crawl::factory()->count(5)->create([
            'project_id' => $this->project->id,
            'start_url' => 'https://example.com/x',
            'status' => 'completed',
        ]);

        $this->actingAs($this->user)
            ->post(
                route('app.project.crawls.store', ['project' => $this->project->id]),
                $this->payload(['max_pages' => 5000])
            )
            ->assertSessionHasNoErrors();

        $this->assertSame(5000, Crawl::query()->latest('id')->first()->max_pages);
    }
}
