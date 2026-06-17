<?php

namespace Tests\Feature;

use App\Enums\RedirectType;
use App\Enums\UrlStatus;
use App\Jobs\RegenerateTraefikConfigJob;
use App\Models\Domain;
use App\Models\Project;
use App\Models\Statistic;
use App\Models\Url;
use App\Models\User;
use App\Services\GeoIpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class GeoStatisticsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // DomainObserver dispatches this on creation; it writes a Traefik
        // config file to disk, which we don't want in tests. The stat job is
        // left alone so it still runs synchronously (QUEUE_CONNECTION=sync).
        Queue::fake([RegenerateTraefikConfigJob::class]);
    }

    /**
     * Force the GeoIP lookup to resolve to a fixed location regardless of the
     * request IP — the bundled MaxMind DB can't resolve 127.0.0.1.
     *
     * @param  array<string, string|null>  $geo
     */
    private function fakeGeo(array $geo): void
    {
        $this->app->instance(GeoIpService::class, new class($geo) extends GeoIpService
        {
            public function __construct(private array $geo) {}

            public function lookup(string $ip): array
            {
                return $this->geo;
            }
        });
    }

    /**
     * Build a redirectable short link on its own host.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function makeUrl(array $attributes = []): Url
    {
        $user = User::factory()->create();
        $project = Project::create(['name' => 'Acme']);
        $domain = Domain::create(['project_id' => $project->id, 'name' => 'links.test']);

        return Url::create(array_merge([
            'project_id' => $project->id,
            'domain_id' => $domain->id,
            'user_id' => $user->id,
            'slug' => 'promo',
            'url' => 'https://example.com/default',
            'type' => RedirectType::cases()[0],
            'status' => UrlStatus::ACTIVATED,
            'archived' => false,
        ], $attributes));
    }

    public function test_visitor_country_is_recorded_on_redirect(): void
    {
        $this->fakeGeo([
            'country' => 'Germany',
            'city' => 'Berlin',
            'country_code' => 'DE',
            'subdivision_code' => 'BE',
        ]);

        $url = $this->makeUrl();

        $this->get('http://links.test/promo')
            ->assertRedirect('https://example.com/default');

        $this->assertDatabaseHas('statistics', [
            'url_id' => $url->id,
            'project_id' => $url->project_id,
            'country' => 'Germany',
            'city' => 'Berlin',
        ]);
    }

    public function test_geo_targeting_redirects_to_country_specific_url(): void
    {
        $this->fakeGeo([
            'country' => 'Germany',
            'city' => 'Berlin',
            'country_code' => 'DE',
            'subdivision_code' => 'BE',
        ]);

        $this->makeUrl([
            'targeting_geo' => [
                ['country' => 'DE', 'state' => '', 'url' => 'https://example.com/de'],
            ],
        ]);

        $this->get('http://links.test/promo')
            ->assertRedirect('https://example.com/de');
    }

    public function test_unmatched_country_falls_through_to_default_url(): void
    {
        $this->fakeGeo([
            'country' => 'United States',
            'city' => 'New York',
            'country_code' => 'US',
            'subdivision_code' => 'NY',
        ]);

        $this->makeUrl([
            'targeting_geo' => [
                ['country' => 'DE', 'state' => '', 'url' => 'https://example.com/de'],
            ],
        ]);

        $this->get('http://links.test/promo')
            ->assertRedirect('https://example.com/default');

        $this->assertDatabaseHas('statistics', [
            'country' => 'United States',
        ]);
    }

    public function test_statistic_factory_seeds_country_breakdown(): void
    {
        $url = $this->makeUrl();

        foreach (['Germany', 'United States', 'France'] as $country) {
            Statistic::factory()->forUrl($url)->country($country)->create();
        }

        $this->assertSame(3, Statistic::count());
        $this->assertSame(
            ['France', 'Germany', 'United States'],
            Statistic::query()->orderBy('country')->pluck('country')->all(),
        );
    }
}
