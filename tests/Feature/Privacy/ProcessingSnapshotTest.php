<?php

namespace Tests\Feature\Privacy;

use App\Enums\ConsentMode;
use App\Enums\PixelProvider;
use App\Enums\TrackingMode;
use App\Models\Domain;
use App\Models\Pixel;
use App\Models\Project;
use App\Models\Site;
use App\Models\Url;
use App\Services\GeoIpService;
use App\Support\Privacy\ProcessingSnapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProcessingSnapshotTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_lists_each_site_with_its_modes(): void
    {
        $project = Project::factory()->create();

        $site = Site::factory()->forProject($project)->create([
            'domain' => 'shop.example.com',
            'tracking_mode' => TrackingMode::Hybrid,
            'consent_mode' => ConsentMode::ThirdPartySignal,
            'respect_dnt' => true,
            'track_outbound_links' => true,
            'track_file_downloads' => false,
            'track_contact_links' => false,
            'site_search_params' => 'q, search',
            'retention_days' => 60,
        ]);

        $sites = ProcessingSnapshot::for($project)->sites();

        $this->assertCount(1, $sites);
        $reported = $sites[0];

        $this->assertSame($site->domain, $reported->domain);
        $this->assertSame(TrackingMode::Hybrid, $reported->trackingMode);
        $this->assertSame(ConsentMode::ThirdPartySignal, $reported->consentMode);
        $this->assertTrue($reported->respectsDnt);
        $this->assertTrue($reported->tracksOutboundLinks);
        $this->assertFalse($reported->tracksFileDownloads);
        $this->assertFalse($reported->tracksContactLinks);
        $this->assertTrue($reported->tracksSiteSearch);
        $this->assertSame(60, $reported->retentionDays);
    }

    public function test_a_soft_deleted_site_is_not_reported(): void
    {
        $project = Project::factory()->create();

        $site = Site::factory()->forProject($project)->create();
        $site->delete();

        $sites = ProcessingSnapshot::for($project)->sites();

        $this->assertCount(0, $sites);
    }

    public function test_a_soft_deleted_link_is_not_counted(): void
    {
        $project = Project::factory()->create();
        $domain = Domain::factory()->create(['project_id' => $project->id]);

        Url::factory()->create(['project_id' => $project->id, 'domain_id' => $domain->id]);
        $deleted = Url::factory()->create(['project_id' => $project->id, 'domain_id' => $domain->id]);
        $deleted->delete();

        $linkCount = ProcessingSnapshot::for($project)->linkCount();

        $this->assertSame(1, $linkCount);
    }

    public function test_sites_keep_their_own_retention_when_they_differ(): void
    {
        $project = Project::factory()->create();

        Site::factory()->forProject($project)->create(['retention_days' => 90]);
        Site::factory()->forProject($project)->create(['retention_days' => null]);

        $retentionDays = collect(ProcessingSnapshot::for($project)->sites())
            ->map(fn ($site) => $site->retentionDays)
            ->sort()
            ->values()
            ->all();

        $expectedDefault = config('analytics.retention_months') * 30;

        $this->assertSame([min(90, $expectedDefault), max(90, $expectedDefault)], $retentionDays);
        $this->assertContains(90, $retentionDays);
        $this->assertContains($expectedDefault, $retentionDays);
    }

    public function test_a_pixel_attached_to_no_link_is_not_reported_as_active(): void
    {
        $project = Project::factory()->create();
        $domain = Domain::factory()->create(['project_id' => $project->id]);
        $url = Url::factory()->create(['project_id' => $project->id, 'domain_id' => $domain->id]);

        // Orphan pixel: attached to no link at all.
        Pixel::factory()->create(['project_id' => $project->id, 'provider' => PixelProvider::Facebook]);

        // Active pixel: attached to a real link.
        $activePixel = Pixel::factory()->create(['project_id' => $project->id, 'provider' => PixelProvider::GoogleAds]);
        $activePixel->urls()->attach($url);

        $snapshot = ProcessingSnapshot::for($project);

        $this->assertSame([PixelProvider::GoogleAds], $snapshot->pixelProviders());
        $this->assertSame(1, $snapshot->pixelLinkCount());
    }

    public function test_cookie_mode_with_immediate_consent_is_flagged(): void
    {
        $project = Project::factory()->create();

        Site::factory()->forProject($project)->create([
            'tracking_mode' => TrackingMode::Cookie,
            'consent_mode' => ConsentMode::Immediate,
        ]);

        $snapshot = ProcessingSnapshot::for($project);

        $this->assertTrue($snapshot->usesCookies());
        $this->assertTrue($snapshot->hasConsentlessCookies());
    }

    public function test_a_project_with_no_sites_reports_an_empty_site_list(): void
    {
        $project = Project::factory()->create();

        $snapshot = ProcessingSnapshot::for($project);

        $this->assertSame([], $snapshot->sites());
        $this->assertFalse($snapshot->usesCookies());
        $this->assertFalse($snapshot->hasConsentlessCookies());
    }

    public function test_to_array_exposes_every_site_and_the_pixel_providers(): void
    {
        $this->app->instance(GeoIpService::class, new class extends GeoIpService
        {
            public function __construct() {}

            public function isAvailable(): bool
            {
                return true;
            }
        });

        $project = Project::factory()->create();
        $domain = Domain::factory()->create(['project_id' => $project->id]);
        $url = Url::factory()->create(['project_id' => $project->id, 'domain_id' => $domain->id]);

        Site::factory()->forProject($project)->create([
            'domain' => 'shop.example.com',
            'tracking_mode' => TrackingMode::Cookie,
            'consent_mode' => ConsentMode::Immediate,
            'respect_dnt' => false,
            'track_outbound_links' => true,
            'track_file_downloads' => true,
            'site_search_params' => '',
            'retention_days' => null,
        ]);

        $pixel = Pixel::factory()->create(['project_id' => $project->id, 'provider' => PixelProvider::GoogleAnalytics]);
        $pixel->urls()->attach($url);

        $array = ProcessingSnapshot::for($project)->toArray();

        $this->assertSame([
            'sites' => [
                [
                    'domain' => 'shop.example.com',
                    'tracking_mode' => 'cookie',
                    'consent_mode' => 'immediate',
                    'respects_dnt' => false,
                    'tracks_outbound_links' => true,
                    'tracks_file_downloads' => true,
                    'tracks_contact_links' => true,
                    'tracks_site_search' => false,
                    'retention_days' => config('analytics.retention_months') * 30,
                ],
            ],
            'link_count' => 1,
            'pixel_providers' => ['google_analytics'],
            'pixel_link_count' => 1,
            'click_retention_days' => config('statistics.retention_months') * 30,
            'geolocation_available' => true,
            'uses_cookies' => true,
            'has_consentless_cookies' => true,
        ], $array);
    }
}
