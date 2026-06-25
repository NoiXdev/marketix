<?php

namespace Tests\Feature;

use App\Enums\RedirectType;
use App\Enums\UrlStatus;
use App\Jobs\RecordClickStatisticJob;
use App\Models\Domain;
use App\Models\Project;
use App\Models\Url;
use App\Models\User;
use App\Services\GeoIpService;
use App\Support\VisitorHash;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RedirectAnonymizationTest extends TestCase
{
    use RefreshDatabase;

    private function fakeGeo(): void
    {
        $this->app->instance(GeoIpService::class, new class extends GeoIpService
        {
            public function __construct() {}

            public function lookup(string $ip): array
            {
                return ['country' => 'Germany', 'city' => 'Berlin', 'country_code' => 'DE', 'subdivision_code' => 'BE'];
            }
        });
    }

    private function makeUrl(): Url
    {
        $user = User::factory()->create();
        $project = Project::create(['name' => 'Acme']);
        $domain = Domain::create(['project_id' => $project->id, 'name' => 'links.test']);

        return Url::create([
            'project_id' => $project->id,
            'domain_id' => $domain->id,
            'user_id' => $user->id,
            'slug' => 'promo',
            'url' => 'https://example.com/default',
            'type' => RedirectType::cases()[0],
            'status' => UrlStatus::ACTIVATED,
            'archived' => false,
        ]);
    }

    public function test_redirect_stores_hash_not_ip(): void
    {
        $this->fakeGeo();
        $url = $this->makeUrl();

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9', 'HTTP_USER_AGENT' => ''])
            ->get('http://links.test/promo')
            ->assertRedirect('https://example.com/default');

        // The stored value is the hash of the IP, never the IP itself.
        $expected = VisitorHash::for('203.0.113.9', '');
        $this->assertDatabaseHas('statistics', [
            'url_id' => $url->id,
            'visitor_hash' => $expected,
            'country' => 'Germany',
        ]);
    }

    public function test_raw_ip_never_enters_queue_payload(): void
    {
        Queue::fake();
        $this->fakeGeo();
        $this->makeUrl();

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9', 'HTTP_USER_AGENT' => ''])
            ->get('http://links.test/promo')
            ->assertRedirect('https://example.com/default');

        Queue::assertPushed(RecordClickStatisticJob::class, function ($job) {
            return ! str_contains(serialize($job), '203.0.113.9');
        });
    }
}
