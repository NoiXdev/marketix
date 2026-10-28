<?php

namespace App\Support\Privacy;

use App\Enums\ConsentMode;
use App\Enums\PixelProvider;
use App\Enums\TrackingMode;
use App\Models\Project;
use App\Models\Site;
use App\Services\GeoIpService;

/**
 * What a Project actually does: which sites it tracks and how, which pixels
 * actually fire on at least one link, and how long data is retained. Pure
 * read layer over the existing models — no formatting, no translation, no
 * HTTP. Later tasks turn this into a privacy notice.
 */
final class ProcessingSnapshot
{
    /**
     * @param  list<SiteProcessing>  $sites
     * @param  list<PixelProvider>  $pixelProviders
     */
    private function __construct(
        private readonly array $sites,
        private readonly int $linkCount,
        private readonly array $pixelProviders,
        private readonly int $pixelLinkCount,
        private readonly bool $geolocationAvailable,
        private readonly bool $dataCenterDetectionAvailable,
    ) {}

    public static function for(Project $project): self
    {
        $sites = $project->sites()
            ->get()
            ->map(fn (Site $site) => new SiteProcessing(
                domain: $site->domain,
                trackingMode: $site->tracking_mode,
                consentMode: $site->consent_mode,
                respectsDnt: $site->respect_dnt,
                tracksOutboundLinks: $site->track_outbound_links,
                tracksFileDownloads: $site->track_file_downloads,
                tracksContactLinks: $site->track_contact_links,
                tracksSiteSearch: $site->searchParams() !== [],
                retentionDays: $site->retention_days ?? ((int) config('analytics.retention_months') * 30),
            ))
            ->values()
            ->all();

        // Only pixels actually attached to at least one (non-trashed) link
        // are "active processing" — an unattached pixel fires on nothing.
        $pixelProviders = $project->pixels()
            ->whereHas('urls')
            ->get()
            ->pluck('provider')
            ->unique()
            ->values()
            ->all();

        return new self(
            sites: $sites,
            linkCount: $project->urls()->count(),
            pixelProviders: $pixelProviders,
            pixelLinkCount: $project->urls()->whereHas('pixels')->count(),
            geolocationAvailable: app(GeoIpService::class)->isAvailable(),
            dataCenterDetectionAvailable: app(GeoIpService::class)->detectsDataCenters(),
        );
    }

    /** @return list<SiteProcessing> */
    public function sites(): array
    {
        return $this->sites;
    }

    public function linkCount(): int
    {
        return $this->linkCount;
    }

    /** @return list<PixelProvider> */
    public function pixelProviders(): array
    {
        return $this->pixelProviders;
    }

    public function pixelLinkCount(): int
    {
        return $this->pixelLinkCount;
    }

    public function clickRetentionDays(): int
    {
        return (int) config('statistics.retention_months') * 30;
    }

    public function geolocationAvailable(): bool
    {
        return $this->geolocationAvailable;
    }

    public function dataCenterDetectionAvailable(): bool
    {
        return $this->dataCenterDetectionAvailable;
    }

    /** True when any site is in cookie or hybrid tracking mode. */
    public function usesCookies(): bool
    {
        foreach ($this->sites as $site) {
            if (in_array($site->trackingMode, [TrackingMode::Cookie, TrackingMode::Hybrid], true)) {
                return true;
            }
        }

        return false;
    }

    /** True when any site sets cookies without first obtaining consent. */
    public function hasConsentlessCookies(): bool
    {
        foreach ($this->sites as $site) {
            if ($site->trackingMode === TrackingMode::Cookie && $site->consentMode === ConsentMode::Immediate) {
                return true;
            }
        }

        return false;
    }

    /**
     * Shape handed to the frontend as the `snapshot` prop (Task 8). Flat and
     * JSON-serialisable: enums are exposed as their string values.
     */
    public function toArray(): array
    {
        return [
            'sites' => array_map(fn (SiteProcessing $site) => [
                'domain' => $site->domain,
                'tracking_mode' => $site->trackingMode->value,
                'consent_mode' => $site->consentMode->value,
                'respects_dnt' => $site->respectsDnt,
                'tracks_outbound_links' => $site->tracksOutboundLinks,
                'tracks_file_downloads' => $site->tracksFileDownloads,
                'tracks_contact_links' => $site->tracksContactLinks,
                'tracks_site_search' => $site->tracksSiteSearch,
                'retention_days' => $site->retentionDays,
            ], $this->sites),
            'link_count' => $this->linkCount,
            'pixel_providers' => array_map(fn (PixelProvider $provider) => $provider->value, $this->pixelProviders),
            'pixel_link_count' => $this->pixelLinkCount,
            'click_retention_days' => $this->clickRetentionDays(),
            'geolocation_available' => $this->geolocationAvailable,
            'data_center_detection_available' => $this->dataCenterDetectionAvailable,
            'uses_cookies' => $this->usesCookies(),
            'has_consentless_cookies' => $this->hasConsentlessCookies(),
        ];
    }
}
