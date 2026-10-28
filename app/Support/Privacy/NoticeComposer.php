<?php

namespace App\Support\Privacy;

use App\Enums\ConsentMode;
use App\Enums\ControllerRole;
use App\Enums\LegalBasis;
use App\Enums\PixelProvider;
use App\Enums\TrackingMode;

/**
 * Turns a ProcessingSnapshot plus the generator form's answers into an
 * ordered list of notice sections, rendered from the `privacy_notice.*`
 * catalog of the requested locale.
 *
 * Two rules govern the output and are deliberately not configurable:
 *
 * 1. A section whose activity is absent or switched off is omitted
 *    entirely, heading included — an empty heading reads as a claim.
 * 2. The retargeting pixel section cites no legal basis and never claims
 *    consent. It describes what happens; the blunt assessment of that
 *    belongs in the UI warning block, not in text a client publishes.
 */
final class NoticeComposer
{
    private const KEY_PREFIX = 'privacy_notice.';

    /** @var list<string> */
    private array $placeholders = [];

    private ProcessingSnapshot $snapshot;

    private NoticeOptions $options;

    /** @return list<NoticeSection> */
    public function compose(ProcessingSnapshot $snapshot, NoticeOptions $options): array
    {
        $this->placeholders = [];
        $this->snapshot = $snapshot;
        $this->options = $options;

        $sections = [
            $this->controllerSection(),
            $this->processorSection(),
            $this->clickSection(),
            $this->analyticsSection(),
            $this->ipSection(),
            $this->cookieSection(),
            $this->pixelSection(),
            $this->legalBasisSection(),
            $this->retentionSection(),
            $this->rightsSection(),
        ];

        return array_values(array_filter(
            $sections,
            fn (?NoticeSection $section) => $section instanceof NoticeSection,
        ));
    }

    /**
     * Placeholder labels that appeared in the last composed notice, in the
     * order they were first emitted.
     *
     * @return list<string>
     */
    public function placeholders(): array
    {
        return $this->placeholders;
    }

    /** @param  list<NoticeSection>  $sections */
    public function toPlainText(array $sections): string
    {
        $blocks = [];

        foreach ($sections as $section) {
            $blocks[] = $section->heading;

            foreach ($section->paragraphs as $paragraph) {
                $blocks[] = $paragraph;
            }
        }

        return implode("\n\n", $blocks);
    }

    /** @param  list<NoticeSection>  $sections */
    public function toHtml(array $sections): string
    {
        $lines = [];

        foreach ($sections as $section) {
            $lines[] = '<h3>'.e($section->heading).'</h3>';

            foreach ($section->paragraphs as $paragraph) {
                $lines[] = '<p>'.e($paragraph).'</p>';
            }
        }

        return implode("\n", $lines);
    }

    // ── sections, in the order the spec fixes ──────────────────────────

    private function controllerSection(): NoticeSection
    {
        return new NoticeSection($this->t('controller.heading'), [
            $this->t('controller.intro'),
            $this->t('controller.party', [
                'name' => $this->party($this->options->controllerName, 'controller_name'),
                'address' => $this->party($this->options->controllerAddress, 'controller_address'),
            ]),
        ]);
    }

    private function processorSection(): NoticeSection
    {
        $paragraphs = [];

        $paragraphs[] = $this->options->role === ControllerRole::Client
            ? $this->t('processors.agency', [
                'name' => $this->party($this->options->agencyName, 'agency_name'),
                'address' => $this->party($this->options->agencyAddress, 'agency_address'),
            ])
            : $this->t('processors.own_company');

        $paragraphs[] = $this->t('processors.hosting', [
            'provider' => $this->party($this->options->hostingProvider, 'hosting_provider'),
            'location' => $this->party($this->options->hostingLocation, 'hosting_location'),
        ]);

        // Naming the pixels follows the operator's toggle, but denying them
        // follows reality. Switching the pixel row off may drop the pixel
        // paragraph — an omission — yet never turn the notice into a claim
        // that no advertising transfer happens, which on a project with live
        // pixels is the opposite of what the redirect interstitial does.
        if ($this->usesPixels()) {
            $paragraphs[] = $this->t('processors.transfer_pixels');
        } elseif ($this->measures() && $this->snapshot->pixelProviders() === []) {
            $paragraphs[] = $this->t('processors.no_advertising_transfer');
        }

        return new NoticeSection($this->t('processors.heading'), $paragraphs);
    }

    private function clickSection(): ?NoticeSection
    {
        if (! $this->tracksClicks()) {
            return null;
        }

        return new NoticeSection($this->t('clicks.heading'), [
            $this->t('clicks.intro'),
            $this->t('clicks.data'),
        ]);
    }

    private function analyticsSection(): ?NoticeSection
    {
        if (! $this->tracksSites()) {
            return null;
        }

        $paragraphs = [$this->t('analytics.intro')];

        foreach ($this->snapshot->sites() as $site) {
            $paragraphs[] = $this->siteParagraph($site);
        }

        $paragraphs[] = $this->t('analytics.page_view');
        $paragraphs[] = $this->t('analytics.visit');

        // Each of these is a per-site flag, so the sentence names the sites
        // it is true for rather than claiming it for all of them.
        $optional = [
            NoticeOptions::ACTIVITY_OUTBOUND_LINKS => ['analytics.outbound', fn (SiteProcessing $site) => $site->tracksOutboundLinks],
            NoticeOptions::ACTIVITY_FILE_DOWNLOADS => ['analytics.downloads', fn (SiteProcessing $site) => $site->tracksFileDownloads],
            NoticeOptions::ACTIVITY_CONTACT_LINKS => ['analytics.contact_links', fn (SiteProcessing $site) => $site->tracksContactLinks],
            NoticeOptions::ACTIVITY_SITE_SEARCH => ['analytics.site_search', fn (SiteProcessing $site) => $site->tracksSiteSearch],
        ];

        foreach ($optional as $activity => [$key, $predicate]) {
            $domains = $this->domains($predicate);

            if ($domains !== '' && $this->options->wants($activity)) {
                $paragraphs[] = $this->t($key, ['domains' => $domains]);
            }
        }

        if ($this->options->wants(NoticeOptions::ACTIVITY_CUSTOM_EVENTS)) {
            $paragraphs[] = $this->t('analytics.custom_events');
        }

        return new NoticeSection($this->t('analytics.heading'), $paragraphs);
    }

    private function ipSection(): ?NoticeSection
    {
        if (! $this->measures()) {
            return null;
        }

        $paragraphs = [
            $this->t('ip.hash'),
            $this->t('ip.salt'),
            $this->t('ip.'.$this->scopeKey('scope')),
        ];

        // No GeoLite2 database on this instance means no geolocation takes
        // place at all, so the passage would describe something that does
        // not happen.
        if ($this->snapshot->geolocationAvailable()) {
            $paragraphs[] = $this->t('ip.geolocation');
        }

        // Only website measurement looks up the network operator, and only
        // once the GeoLite2-ASN database is on this instance.
        if ($this->tracksSites() && $this->snapshot->dataCenterDetectionAvailable()) {
            $paragraphs[] = $this->t('ip.data_center');
        }

        return new NoticeSection($this->t('ip.heading'), $paragraphs);
    }

    private function cookieSection(): ?NoticeSection
    {
        if (! $this->tracksSites() || ! $this->snapshot->usesCookies()) {
            return null;
        }

        $paragraphs = [
            $this->t('cookies.properties'),
            $this->t('cookies.purpose'),
        ];

        $immediate = $this->domains(fn (SiteProcessing $site) => $site->trackingMode === TrackingMode::Cookie
            && $site->consentMode === ConsentMode::Immediate);

        $afterConsent = $this->domains(fn (SiteProcessing $site) => $site->trackingMode === TrackingMode::Hybrid
            || ($site->trackingMode === TrackingMode::Cookie && $site->consentMode !== ConsentMode::Immediate));

        if ($immediate !== '') {
            $paragraphs[] = $this->t('cookies.set_immediately', ['domains' => $immediate]);
        }

        if ($afterConsent !== '') {
            $paragraphs[] = $this->t('cookies.set_after_consent', ['domains' => $afterConsent]);
        }

        $paragraphs[] = $this->t('cookies.control');

        return new NoticeSection($this->t('cookies.heading'), $paragraphs);
    }

    /**
     * Deliberately free of any legal basis and of any consent claim — see
     * the class docblock.
     */
    private function pixelSection(): ?NoticeSection
    {
        if (! $this->usesPixels()) {
            return null;
        }

        $providers = implode(', ', array_map(
            fn (PixelProvider $provider) => $provider->label(),
            $this->snapshot->pixelProviders(),
        ));

        return new NoticeSection($this->t('pixels.heading'), [
            $this->t('pixels.intro'),
            $this->t('pixels.providers', ['providers' => $providers]),
            $this->t('pixels.transmission'),
            $this->t('pixels.vendor_notices'),
        ]);
    }

    private function legalBasisSection(): ?NoticeSection
    {
        if (! $this->measures()) {
            return null;
        }

        $paragraphs = $this->options->legalBasis === LegalBasis::Consent
            ? $this->consentParagraphs()
            : [
                $this->t('legal_basis.legitimate_interest', [
                    'activities' => $this->t('legal_basis.'.$this->scopeKey('activities')),
                ]),
                $this->t('legal_basis.legitimate_interest_detail'),
            ];

        return new NoticeSection($this->t('legal_basis.heading'), $paragraphs);
    }

    /**
     * Consent can only ever cover site analytics. Link clicks are counted
     * server-side on every request and the product has no consent path for
     * them at all, so asserting consent there would publish a false
     * statement; they are stated under legitimate interest instead, which
     * is the only basis the always-on counting can run on.
     *
     * @return list<string>
     */
    private function consentParagraphs(): array
    {
        $paragraphs = [];

        if ($this->tracksClicks()) {
            $paragraphs[] = $this->t('legal_basis.clicks_legitimate_interest');
        }

        if ($this->tracksSites()) {
            $paragraphs[] = $this->t('legal_basis.consent', [
                'activities' => $this->t('legal_basis.activities_sites'),
            ]);
            $paragraphs[] = $this->t('legal_basis.consent_withdrawal');
        }

        return $paragraphs;
    }

    private function retentionSection(): ?NoticeSection
    {
        if (! $this->measures()) {
            return null;
        }

        $paragraphs = [];

        if ($this->tracksClicks()) {
            $paragraphs[] = $this->t('retention.clicks', [
                'duration' => $this->duration($this->snapshot->clickRetentionDays()),
            ]);
        }

        if ($this->tracksSites()) {
            $periods = array_unique(array_map(
                fn (SiteProcessing $site) => $site->retentionDays,
                $this->snapshot->sites(),
            ));

            if (count($periods) === 1) {
                $paragraphs[] = $this->t('retention.sites_uniform', [
                    'duration' => $this->duration((int) reset($periods)),
                ]);
            } else {
                $paragraphs[] = $this->t('retention.sites_intro');

                foreach ($this->snapshot->sites() as $site) {
                    $paragraphs[] = $this->t('retention.site', [
                        'domain' => $site->domain,
                        'duration' => $this->duration($site->retentionDays),
                    ]);
                }
            }
        }

        return new NoticeSection($this->t('retention.heading'), $paragraphs);
    }

    private function rightsSection(): NoticeSection
    {
        // Consent governs the rights list only when consent governs
        // something in this notice. Site analytics is the only activity it
        // can cover; a links-only project on Consent obtains no consent at
        // all, so offering an Art. 7(3) withdrawal and Art. 20 portability
        // there would promise rights that have nothing to attach to.
        $consentGoverns = $this->options->legalBasis === LegalBasis::Consent && $this->tracksSites();

        $paragraphs = [
            $consentGoverns
                ? $this->t('rights.list_consent')
                : $this->t('rights.list_legitimate_interest'),
        ];

        // The consent list leaves out Art. 21, which does not apply to
        // consent-based processing. Link clicks run on legitimate interest
        // even when the notice's chosen basis is consent, so the objection
        // right still has to be stated for them. The legitimate-interest
        // list already carries Art. 21, so this only applies alongside the
        // consent list.
        if ($consentGoverns && $this->tracksClicks()) {
            $paragraphs[] = $this->t('rights.objection_clicks');
        }

        if ($this->tracksSites() && $this->snapshot->usesCookies()) {
            $paragraphs[] = $this->t('rights.cookie_identifier');
        }

        if ($anonymous = $this->anonymousScopeKey()) {
            $paragraphs[] = $this->t('rights.anonymous', [
                'scope' => $this->t('rights.'.$anonymous),
            ]);
        }

        // Mandatory regardless of mode, configuration or legal basis.
        $paragraphs[] = $this->t('rights.complaint');

        return new NoticeSection($this->t('rights.heading'), $paragraphs);
    }

    // ── helpers ────────────────────────────────────────────────────────

    private function siteParagraph(SiteProcessing $site): string
    {
        $key = match ($site->trackingMode) {
            TrackingMode::Cookieless => 'analytics.site.cookieless',
            TrackingMode::Hybrid => 'analytics.site.hybrid',
            TrackingMode::Cookie => $site->consentMode === ConsentMode::Immediate
                ? 'analytics.site.cookie_immediately'
                : 'analytics.site.cookie_after_consent',
        };

        $paragraph = $this->t($key, ['domain' => $site->domain]);

        if ($site->respectsDnt) {
            $paragraph .= ' '.$this->t('analytics.site.dnt');
        }

        return $paragraph;
    }

    /**
     * Suffix picking the variant of a sentence that enumerates the active
     * measurement activities, so a notice never names an activity that is
     * not taking place.
     */
    private function scopeKey(string $prefix): string
    {
        if ($this->tracksClicks() && $this->tracksSites()) {
            return $prefix.'_both';
        }

        return $this->tracksClicks() ? $prefix.'_clicks' : $prefix.'_sites';
    }

    /**
     * Which processing has no identifier a visitor could present: link
     * clicks always, and sites that are not cookie-identified from the
     * first request.
     */
    private function anonymousScopeKey(): ?string
    {
        $sites = $this->tracksSites() && $this->anySite(
            fn (SiteProcessing $site) => $site->trackingMode !== TrackingMode::Cookie,
        );

        return match (true) {
            $this->tracksClicks() && $sites => 'anonymous_scope_both',
            $this->tracksClicks() => 'anonymous_scope_clicks',
            $sites => 'anonymous_scope_sites',
            default => null,
        };
    }

    private function tracksClicks(): bool
    {
        return $this->snapshot->linkCount() > 0
            && $this->options->wants(NoticeOptions::ACTIVITY_LINK_CLICKS);
    }

    private function tracksSites(): bool
    {
        return $this->snapshot->sites() !== []
            && $this->options->wants(NoticeOptions::ACTIVITY_SITE_ANALYTICS);
    }

    private function usesPixels(): bool
    {
        return $this->snapshot->pixelProviders() !== []
            && $this->options->wants(NoticeOptions::ACTIVITY_PIXELS);
    }

    /** True when any measurement — link clicks or site analytics — is active. */
    private function measures(): bool
    {
        return $this->tracksClicks() || $this->tracksSites();
    }

    /** @param  callable(SiteProcessing): bool  $predicate */
    private function anySite(callable $predicate): bool
    {
        foreach ($this->snapshot->sites() as $site) {
            if ($predicate($site)) {
                return true;
            }
        }

        return false;
    }

    /** @param  callable(SiteProcessing): bool  $predicate */
    private function domains(callable $predicate): string
    {
        return implode(', ', array_map(
            fn (SiteProcessing $site) => $site->domain,
            array_filter($this->snapshot->sites(), $predicate),
        ));
    }

    /**
     * Whole months read naturally and are what the configuration defaults
     * produce; a site's own retention_days override may not divide evenly,
     * and is then stated in days rather than rounded.
     */
    private function duration(int $days): string
    {
        if ($days > 0 && $days % 30 === 0) {
            return trans_choice(self::KEY_PREFIX.'duration.months', intdiv($days, 30), [], $this->options->locale);
        }

        return trans_choice(self::KEY_PREFIX.'duration.days', $days, [], $this->options->locale);
    }

    /**
     * A party detail, or a visible placeholder when it is missing. Silent
     * omission would publish a notice with a nameless controller.
     */
    private function party(?string $value, string $key): string
    {
        $value = $value === null ? '' : trim($value);

        if ($value !== '') {
            return $value;
        }

        $placeholder = $this->t('placeholders.'.$key);

        if (! in_array($placeholder, $this->placeholders, true)) {
            $this->placeholders[] = $placeholder;
        }

        return $placeholder;
    }

    /** @param  array<string, string>  $replacements */
    private function t(string $key, array $replacements = []): string
    {
        return (string) trans(self::KEY_PREFIX.$key, $replacements, $this->options->locale);
    }
}
