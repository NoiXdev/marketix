<?php

namespace Tests\Feature;

use App\Enums\TrackingMode;
use App\Jobs\RecordEventJob;
use App\Jobs\RecordPageViewJob;
use App\Models\PageView;
use App\Models\Project;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class TrackedHostnamesTest extends TestCase
{
    use RefreshDatabase;

    private function hit(Site $site, array $payload = [], array $server = [])
    {
        return $this->call('POST', route('app.analytics.event'), [], [], [], ['CONTENT_TYPE' => 'text/plain', ...$server], json_encode([
            'site' => $site->tracking_id,
            'path' => '/',
            ...$payload,
        ]));
    }

    public function test_hits_from_the_domain_its_subdomains_and_additional_hostnames_are_accepted(): void
    {
        Queue::fake([RecordPageViewJob::class]);
        $site = Site::factory()->create(['domain' => 'https://www.example.com/', 'additional_hostnames' => 'example.de', 'tracking_mode' => TrackingMode::Cookieless]);

        foreach (['https://example.com', 'https://www.example.com', 'https://shop.example.com', 'https://example.de', 'https://blog.example.de'] as $origin) {
            $this->hit($site, [], ['HTTP_ORIGIN' => $origin])->assertStatus(202);
        }

        Queue::assertPushed(RecordPageViewJob::class, 5);
    }

    public function test_hits_from_other_origins_are_dropped(): void
    {
        Queue::fake([RecordPageViewJob::class, RecordEventJob::class]);
        $site = Site::factory()->create(['domain' => 'example.com']);

        $this->hit($site, ['hostname' => 'example.com'], ['HTTP_ORIGIN' => 'https://copycat.test'])->assertNoContent();
        $this->hit($site, [], ['HTTP_ORIGIN' => 'https://notexample.com'])->assertNoContent();
        $this->hit($site, [], ['HTTP_ORIGIN' => 'null'])->assertNoContent();
        $this->hit($site, ['type' => 'event', 'name' => 'signup'], ['HTTP_ORIGIN' => 'http://localhost:3000'])->assertNoContent();

        Queue::assertNothingPushed();
    }

    public function test_the_payload_hostname_is_checked_when_there_is_no_origin(): void
    {
        Queue::fake([RecordPageViewJob::class]);
        $site = Site::factory()->create(['domain' => 'example.com']);

        $this->hit($site, ['hostname' => 'staging.other.com'])->assertNoContent();
        $this->hit($site, ['hostname' => 'example.com'])->assertStatus(202);

        Queue::assertPushed(RecordPageViewJob::class, 1);
    }

    public function test_additional_hostnames_are_normalized_when_saving_a_site(): void
    {
        $user = User::factory()->create();
        $project = Project::create(['name' => 'Acme']);
        $user->projects()->attach($project);
        $site = Site::factory()->forProject($project)->create();

        $payload = [
            'name' => $site->name, 'domain' => $site->domain, 'tracking_mode' => 'cookieless', 'consent_mode' => 'immediate',
            'additional_hostnames' => " Example.DE,\nshop.example.org  example.de ",
        ];

        $this->actingAs($user)
            ->put(route('app.project.sites.update', ['project' => $project->id, 'site' => $site->id]), $payload)
            ->assertSessionHasNoErrors();

        $this->assertSame('example.de, shop.example.org', $site->fresh()->additional_hostnames);

        $this->actingAs($user)
            ->put(route('app.project.sites.update', ['project' => $project->id, 'site' => $site->id]), [...$payload, 'additional_hostnames' => 'https://example.de/path'])
            ->assertSessionHasErrors('additional_hostnames');
    }

    public function test_the_migration_keeps_hostnames_a_site_was_already_tracked_on(): void
    {
        $site = Site::factory()->create(['domain' => 'example.com']);
        foreach (['example.com', 'shop.example.com', 'example.de', 'localhost', '127.0.0.1', 'dev.local'] as $hostname) {
            PageView::factory()->forSite($site)->create(['hostname' => $hostname, 'created_at' => now()->subDay()]);
        }
        PageView::factory()->forSite($site)->create(['hostname' => 'old.example.org', 'created_at' => now()->subDays(60)]);

        $migration = require database_path('migrations/2026_10_12_000003_add_additional_hostnames_to_sites_table.php');
        $migration->down();
        $migration->up();

        $this->assertSame('example.de', $site->fresh()->additional_hostnames);
    }
}
