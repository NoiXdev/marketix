<?php

namespace Tests\Feature\Privacy;

use App\Enums\ConsentMode;
use App\Enums\ControllerRole;
use App\Enums\LegalBasis;
use App\Enums\PixelProvider;
use App\Enums\TrackingMode;
use App\Models\Domain;
use App\Models\Pixel;
use App\Models\Project;
use App\Models\Site;
use App\Models\Url;
use App\Services\GeoIpService;
use App\Support\Privacy\NoticeComposer;
use App\Support\Privacy\NoticeOptions;
use App\Support\Privacy\NoticeSection;
use App\Support\Privacy\ProcessingSnapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NoticeComposerTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_pixel_section_never_claims_consent(): void
    {
        $project = $this->projectWithEverything();

        $sections = (new NoticeComposer)->compose(
            ProcessingSnapshot::for($project),
            $this->noticeOptions(),
        );

        $pixels = $this->section($sections, trans('privacy_notice.pixels.heading', [], 'de'));
        $this->assertNotNull($pixels, 'The pixel section is missing.');

        $body = implode("\n", $pixels->paragraphs);

        foreach (['Einwillig', 'einwillig', 'Zustimmung', 'Rechtsgrundlage', 'Art. 6'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $body);
        }
    }

    public function test_the_pixel_section_names_every_provider_in_use(): void
    {
        $project = Project::factory()->create();
        $domain = Domain::factory()->create(['project_id' => $project->id]);
        $url = Url::factory()->create(['project_id' => $project->id, 'domain_id' => $domain->id]);

        foreach ([PixelProvider::Facebook, PixelProvider::TikTok, PixelProvider::LinkedIn] as $provider) {
            Pixel::factory()->create(['project_id' => $project->id, 'provider' => $provider])
                ->urls()->attach($url);
        }

        $sections = (new NoticeComposer)->compose(
            ProcessingSnapshot::for($project),
            $this->noticeOptions(),
        );

        $body = implode("\n", $this->section($sections, trans('privacy_notice.pixels.heading', [], 'de'))->paragraphs);

        $this->assertStringContainsString('Facebook', $body);
        $this->assertStringContainsString('TikTok', $body);
        $this->assertStringContainsString('LinkedIn', $body);
    }

    public function test_a_project_without_pixels_has_no_pixel_section(): void
    {
        $project = Project::factory()->create();
        $domain = Domain::factory()->create(['project_id' => $project->id]);
        Url::factory()->create(['project_id' => $project->id, 'domain_id' => $domain->id]);

        $composer = new NoticeComposer;
        $sections = $composer->compose(ProcessingSnapshot::for($project), $this->noticeOptions());

        $this->assertSectionAbsent($composer, $sections, trans('privacy_notice.pixels.heading', [], 'de'));
        $this->assertStringNotContainsString(
            trans('privacy_notice.pixels.transmission', [], 'de'),
            $composer->toPlainText($sections),
        );
    }

    public function test_a_project_without_pixels_denies_any_advertising_transfer(): void
    {
        $project = Project::factory()->create();
        $domain = Domain::factory()->create(['project_id' => $project->id]);
        Url::factory()->create(['project_id' => $project->id, 'domain_id' => $domain->id]);

        $composer = new NoticeComposer;
        $sections = $composer->compose(ProcessingSnapshot::for($project), $this->noticeOptions());

        $processors = $this->section($sections, trans('privacy_notice.processors.heading', [], 'de'));
        $this->assertNotNull($processors, 'The processor section is missing.');

        $this->assertContains(
            trans('privacy_notice.processors.no_advertising_transfer', [], 'de'),
            $processors->paragraphs,
            'A project that really transfers nothing to advertising providers must say so.',
        );
    }

    public function test_a_project_with_pixels_never_denies_the_advertising_transfer(): void
    {
        $project = $this->projectWithEverything();

        $composer = new NoticeComposer;

        // Both the notice that names the pixels and the one whose pixel row
        // was switched off: neither may claim the transfer does not happen.
        foreach ([[], [NoticeOptions::ACTIVITY_PIXELS]] as $disabled) {
            $sections = $composer->compose(
                ProcessingSnapshot::for($project),
                $this->noticeOptions(['disabledActivities' => $disabled]),
            );

            $this->assertStringNotContainsString(
                trans('privacy_notice.processors.no_advertising_transfer', [], 'de'),
                $composer->toPlainText($sections),
                $disabled === [] ? 'pixels listed' : 'pixel row switched off',
            );
        }
    }

    public function test_a_project_with_no_sites_omits_the_analytics_section(): void
    {
        $project = Project::factory()->create();
        $domain = Domain::factory()->create(['project_id' => $project->id]);
        Url::factory()->create(['project_id' => $project->id, 'domain_id' => $domain->id]);

        $composer = new NoticeComposer;
        $sections = $composer->compose(ProcessingSnapshot::for($project), $this->noticeOptions());

        $this->assertSectionAbsent($composer, $sections, trans('privacy_notice.analytics.heading', [], 'de'));
        $this->assertStringNotContainsString(
            trans('privacy_notice.analytics.page_view', [], 'de'),
            $composer->toPlainText($sections),
        );
        $this->assertNotNull($this->section($sections, trans('privacy_notice.clicks.heading', [], 'de')));
    }

    public function test_a_project_with_no_links_omits_the_click_section(): void
    {
        $project = Project::factory()->create();
        Site::factory()->forProject($project)->create(['tracking_mode' => TrackingMode::Cookieless]);

        $composer = new NoticeComposer;
        $sections = $composer->compose(ProcessingSnapshot::for($project), $this->noticeOptions());

        $this->assertSectionAbsent($composer, $sections, trans('privacy_notice.clicks.heading', [], 'de'));
        $this->assertStringNotContainsString(
            trans('privacy_notice.clicks.data', [], 'de'),
            $composer->toPlainText($sections),
        );
        $this->assertNotNull($this->section($sections, trans('privacy_notice.analytics.heading', [], 'de')));
    }

    public function test_a_cookieless_only_project_has_no_cookie_section(): void
    {
        $project = Project::factory()->create();
        Site::factory()->forProject($project)->create(['tracking_mode' => TrackingMode::Cookieless]);
        Site::factory()->forProject($project)->create(['tracking_mode' => TrackingMode::Cookieless]);

        $composer = new NoticeComposer;
        $sections = $composer->compose(ProcessingSnapshot::for($project), $this->noticeOptions());

        $this->assertSectionAbsent($composer, $sections, trans('privacy_notice.cookies.heading', [], 'de'));
        $this->assertStringNotContainsString(
            trans('privacy_notice.cookies.properties', [], 'de'),
            $composer->toPlainText($sections),
        );
        $this->assertStringNotContainsString('mx_vid', $composer->toPlainText($sections));
    }

    public function test_the_geolocation_passage_is_omitted_when_the_database_is_absent(): void
    {
        $project = Project::factory()->create();
        Site::factory()->forProject($project)->create();

        $withGeo = $this->composeWithGeolocation($project, true);
        $withoutGeo = $this->composeWithGeolocation($project, false);

        $passage = trans('privacy_notice.ip.geolocation', [], 'de');

        $this->assertStringContainsString($passage, $withGeo);
        $this->assertStringNotContainsString($passage, $withoutGeo);
        $this->assertStringNotContainsString('GeoLite2', $withoutGeo);

        // The surrounding section survives — only the passage goes.
        $this->assertStringContainsString(trans('privacy_notice.ip.heading', [], 'de'), $withoutGeo);
    }

    public function test_a_missing_controller_name_becomes_a_visible_placeholder(): void
    {
        $project = $this->projectWithEverything();

        $composer = new NoticeComposer;
        $sections = $composer->compose(
            ProcessingSnapshot::for($project),
            $this->noticeOptions(['controllerName' => null]),
        );

        $this->assertStringContainsString('[Name des Verantwortlichen]', $composer->toPlainText($sections));
        $this->assertContains('[Name des Verantwortlichen]', $composer->placeholders());
    }

    public function test_both_retention_periods_appear_when_sites_differ(): void
    {
        $project = Project::factory()->create();
        Site::factory()->forProject($project)->create(['domain' => 'one.example.com', 'retention_days' => 720]);
        Site::factory()->forProject($project)->create(['domain' => 'two.example.com', 'retention_days' => 45]);

        $composer = new NoticeComposer;
        $sections = $composer->compose(ProcessingSnapshot::for($project), $this->noticeOptions());

        $retention = $this->section($sections, trans('privacy_notice.retention.heading', [], 'de'));
        $this->assertNotNull($retention);

        $body = implode("\n", $retention->paragraphs);

        $this->assertStringContainsString('24 Monate', $body);
        $this->assertStringContainsString('45 Tage', $body);
        $this->assertStringContainsString('one.example.com', $body);
        $this->assertStringContainsString('two.example.com', $body);
    }

    public function test_the_rights_section_always_names_the_right_to_complain(): void
    {
        $configurations = [
            fn (Project $project) => null,
            fn (Project $project) => Site::factory()->forProject($project)->create([
                'tracking_mode' => TrackingMode::Cookieless,
            ]),
            fn (Project $project) => Site::factory()->forProject($project)->create([
                'tracking_mode' => TrackingMode::Cookie,
                'consent_mode' => ConsentMode::Immediate,
            ]),
        ];

        foreach ($configurations as $index => $configure) {
            $project = Project::factory()->create();
            $configure($project);

            foreach ([LegalBasis::LegitimateInterest, LegalBasis::Consent] as $basis) {
                $sections = (new NoticeComposer)->compose(
                    ProcessingSnapshot::for($project),
                    $this->noticeOptions(['legalBasis' => $basis]),
                );

                $rights = $this->section($sections, trans('privacy_notice.rights.heading', [], 'de'));

                $this->assertNotNull($rights, "Configuration {$index} produced no rights section.");
                $this->assertStringContainsString('Art. 77', implode("\n", $rights->paragraphs));
            }
        }
    }

    public function test_the_rights_section_states_what_cannot_be_exercised_in_cookieless_mode(): void
    {
        $project = Project::factory()->create();
        Site::factory()->forProject($project)->create(['tracking_mode' => TrackingMode::Cookieless]);

        $cookieless = (new NoticeComposer)->compose(ProcessingSnapshot::for($project), $this->noticeOptions());
        $body = implode("\n", $this->section($cookieless, trans('privacy_notice.rights.heading', [], 'de'))->paragraphs);

        $this->assertStringContainsString('Art. 15', $body);
        $this->assertStringContainsString('Art. 17', $body);
        $this->assertStringContainsString('48 Stunden', $body);
        $this->assertStringNotContainsString('mx_vid', $body);

        $cookied = Project::factory()->create();
        Site::factory()->forProject($cookied)->create(['tracking_mode' => TrackingMode::Cookie]);

        $sections = (new NoticeComposer)->compose(ProcessingSnapshot::for($cookied), $this->noticeOptions());
        $body = implode("\n", $this->section($sections, trans('privacy_notice.rights.heading', [], 'de'))->paragraphs);

        $this->assertStringContainsString('mx_vid', $body);
    }

    public function test_consent_is_never_asserted_for_link_click_recording(): void
    {
        $project = Project::factory()->create();
        $domain = Domain::factory()->create(['project_id' => $project->id]);
        Url::factory()->create(['project_id' => $project->id, 'domain_id' => $domain->id]);
        Site::factory()->forProject($project)->create(['tracking_mode' => TrackingMode::Cookieless]);

        $sections = (new NoticeComposer)->compose(
            ProcessingSnapshot::for($project),
            $this->noticeOptions(['legalBasis' => LegalBasis::Consent]),
        );

        $basis = $this->section($sections, trans('privacy_notice.legal_basis.heading', [], 'de'));
        $this->assertNotNull($basis);

        $body = implode("\n", $basis->paragraphs);
        $clicks = trans('privacy_notice.legal_basis.activities_clicks', [], 'de');
        $sites = trans('privacy_notice.legal_basis.activities_sites', [], 'de');

        // Clicks are counted server-side on every request with no consent
        // path anywhere in the product, so they run on legitimate interest.
        $this->assertStringContainsString(
            trans('privacy_notice.legal_basis.clicks_legitimate_interest', [], 'de'),
            $body,
        );
        $this->assertStringContainsString('Art. 6 Abs. 1 lit. f DSGVO', $body);

        // The consent sentence covers site analytics and nothing else.
        $this->assertStringContainsString(
            trans('privacy_notice.legal_basis.consent', ['activities' => $sites], 'de'),
            $body,
        );
        $this->assertStringNotContainsString(
            trans('privacy_notice.legal_basis.consent', ['activities' => $clicks], 'de'),
            $body,
        );
        $this->assertStringNotContainsString(
            trans('privacy_notice.legal_basis.activities_both', [], 'de'),
            $body,
        );

        // Clicks running on legitimate interest carry the Art. 21 objection
        // right, which the consent variant of the rights list omits.
        $rights = implode("\n", $this->section($sections, trans('privacy_notice.rights.heading', [], 'de'))->paragraphs);
        $this->assertStringContainsString('Art. 21', $rights);
    }

    public function test_a_links_only_project_on_consent_claims_no_consent_at_all(): void
    {
        $project = Project::factory()->create();
        $domain = Domain::factory()->create(['project_id' => $project->id]);
        Url::factory()->create(['project_id' => $project->id, 'domain_id' => $domain->id]);

        $sections = (new NoticeComposer)->compose(
            ProcessingSnapshot::for($project),
            $this->noticeOptions(['legalBasis' => LegalBasis::Consent]),
        );

        $body = implode("\n", $this->section($sections, trans('privacy_notice.legal_basis.heading', [], 'de'))->paragraphs);

        $this->assertStringContainsString('Art. 6 Abs. 1 lit. f DSGVO', $body);
        $this->assertStringNotContainsString('Art. 6 Abs. 1 lit. a DSGVO', $body);
        $this->assertStringNotContainsString('Einwilligung', $body);

        // No consent is obtained anywhere in this notice, so the rights
        // section must not offer a withdrawal of one, nor portability,
        // which only attaches to consent-based processing.
        $rights = implode("\n", $this->section($sections, trans('privacy_notice.rights.heading', [], 'de'))->paragraphs);

        $this->assertStringNotContainsString('Art. 7 Abs. 3', $rights);
        $this->assertStringNotContainsString('Art. 20', $rights);
        $this->assertSame(1, substr_count($rights, 'Art. 21'), 'The objection right must be stated exactly once.');
    }

    public function test_the_rights_list_follows_whether_consent_governs_anything(): void
    {
        // [legal basis, has links, has sites] => [consent list?, Art. 21 occurrences]
        $combinations = [
            'consent with sites and links' => [LegalBasis::Consent, true, true, true, 1],
            'consent with sites only' => [LegalBasis::Consent, false, true, true, 0],
            'consent with links only' => [LegalBasis::Consent, true, false, false, 1],
            'legitimate interest' => [LegalBasis::LegitimateInterest, true, true, false, 1],
        ];

        foreach ($combinations as $label => [$basis, $hasLinks, $hasSites, $consentList, $objections]) {
            $project = Project::factory()->create();

            if ($hasLinks) {
                $domain = Domain::factory()->create(['project_id' => $project->id]);
                Url::factory()->create(['project_id' => $project->id, 'domain_id' => $domain->id]);
            }

            if ($hasSites) {
                Site::factory()->forProject($project)->create(['tracking_mode' => TrackingMode::Cookieless]);
            }

            $sections = (new NoticeComposer)->compose(
                ProcessingSnapshot::for($project),
                $this->noticeOptions(['legalBasis' => $basis]),
            );

            $rights = implode("\n", $this->section($sections, trans('privacy_notice.rights.heading', [], 'de'))->paragraphs);

            $expected = $consentList ? 'rights.list_consent' : 'rights.list_legitimate_interest';
            $unexpected = $consentList ? 'rights.list_legitimate_interest' : 'rights.list_consent';

            $this->assertStringContainsString(trans('privacy_notice.'.$expected, [], 'de'), $rights, $label);
            $this->assertStringNotContainsString(trans('privacy_notice.'.$unexpected, [], 'de'), $rights, $label);

            // Art. 7(3) withdrawal and Art. 20 portability presuppose
            // consent-based processing and may only appear with that list.
            $this->assertSame($consentList, str_contains($rights, 'Art. 7 Abs. 3'), $label);
            $this->assertSame($consentList, str_contains($rights, 'Art. 20'), $label);

            // Stated exactly once, whether it comes from the
            // legitimate-interest list or from the separate clicks paragraph.
            $this->assertSame($objections, substr_count($rights, 'Art. 21'), $label);

            $this->assertStringContainsString('Art. 77', $rights, $label);
        }
    }

    public function test_contact_link_tracking_names_only_the_sites_that_do_it(): void
    {
        $project = Project::factory()->create();
        Site::factory()->forProject($project)->create(['domain' => 'shop.example.com', 'tracking_mode' => TrackingMode::Cookieless, 'track_contact_links' => true]);
        Site::factory()->forProject($project)->create(['domain' => 'blog.example.com', 'tracking_mode' => TrackingMode::Cookieless, 'track_contact_links' => false]);

        $composer = new NoticeComposer;
        $body = implode("\n", $this->section($composer->compose(ProcessingSnapshot::for($project), $this->noticeOptions()), trans('privacy_notice.analytics.heading', [], 'de'))->paragraphs);

        $this->assertStringContainsString(trans('privacy_notice.analytics.contact_links', ['domains' => 'shop.example.com'], 'de'), $body);

        $sections = $composer->compose(ProcessingSnapshot::for($project), $this->noticeOptions(['disabledActivities' => [NoticeOptions::ACTIVITY_CONTACT_LINKS]]));
        $this->assertStringNotContainsString(
            trans('privacy_notice.analytics.contact_links', ['domains' => 'shop.example.com'], 'de'),
            $composer->toPlainText($sections),
        );
    }

    public function test_switched_off_activities_are_omitted(): void
    {
        $project = Project::factory()->create();
        $domain = Domain::factory()->create(['project_id' => $project->id]);
        $url = Url::factory()->create(['project_id' => $project->id, 'domain_id' => $domain->id]);

        Site::factory()->forProject($project)->create([
            'domain' => 'shop.example.com',
            'tracking_mode' => TrackingMode::Cookieless,
            'track_outbound_links' => true,
            'site_search_params' => 'q',
        ]);

        Pixel::factory()->create(['project_id' => $project->id, 'provider' => PixelProvider::Facebook])
            ->urls()->attach($url);

        $composer = new NoticeComposer;
        $sections = $composer->compose(
            ProcessingSnapshot::for($project),
            $this->noticeOptions([
                'disabledActivities' => [
                    NoticeOptions::ACTIVITY_PIXELS,
                    NoticeOptions::ACTIVITY_SITE_SEARCH,
                ],
            ]),
        );

        $this->assertSectionAbsent($composer, $sections, trans('privacy_notice.pixels.heading', [], 'de'));
        $this->assertStringNotContainsString(
            trans('privacy_notice.pixels.transmission', [], 'de'),
            $composer->toPlainText($sections),
        );

        // Omitting the pixels is the operator's call; denying them is not.
        // The transfer still happens on every click, so the notice may not
        // assert the opposite of what the redirect interstitial does.
        $this->assertStringNotContainsString(
            trans('privacy_notice.processors.no_advertising_transfer', [], 'de'),
            $composer->toPlainText($sections),
        );

        $analytics = $this->section($sections, trans('privacy_notice.analytics.heading', [], 'de'));
        $this->assertNotNull($analytics, 'Switching off site search must not take the analytics section with it.');

        $body = implode("\n", $analytics->paragraphs);

        $this->assertStringNotContainsString(
            trans('privacy_notice.analytics.site_search', ['domains' => 'shop.example.com'], 'de'),
            $body,
        );

        // What was left switched on is still there.
        $this->assertStringContainsString(trans('privacy_notice.analytics.page_view', [], 'de'), $body);
        $this->assertStringContainsString(
            trans('privacy_notice.analytics.outbound', ['domains' => 'shop.example.com'], 'de'),
            $body,
        );
    }

    public function test_html_output_escapes_party_details(): void
    {
        $project = $this->projectWithEverything();

        $composer = new NoticeComposer;
        $sections = $composer->compose(
            ProcessingSnapshot::for($project),
            $this->noticeOptions([
                'controllerName' => 'Müller <GmbH> & Co.',
                'controllerAddress' => '<script>alert("xss")</script>, 50672 Köln',
            ]),
        );

        $html = $composer->toHtml($sections);

        $this->assertStringContainsString('&lt;GmbH&gt;', $html);
        $this->assertStringContainsString('&amp;', $html);
        $this->assertStringContainsString('Müller', $html);
        $this->assertStringNotContainsString('<GmbH>', $html);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringContainsString('<h3>', $html);
        $this->assertStringContainsString('<p>', $html);
    }

    // ── helpers ────────────────────────────────────────────────────────

    private function projectWithEverything(): Project
    {
        $project = Project::factory()->create();
        $domain = Domain::factory()->create(['project_id' => $project->id]);
        $url = Url::factory()->create(['project_id' => $project->id, 'domain_id' => $domain->id]);

        Site::factory()->forProject($project)->create([
            'domain' => 'shop.example.com',
            'tracking_mode' => TrackingMode::Cookie,
            'consent_mode' => ConsentMode::Immediate,
        ]);

        Pixel::factory()->create(['project_id' => $project->id, 'provider' => PixelProvider::Facebook])
            ->urls()->attach($url);

        return $project;
    }

    private function noticeOptions(array $overrides = []): NoticeOptions
    {
        return new NoticeOptions(
            role: $overrides['role'] ?? ControllerRole::Client,
            legalBasis: $overrides['legalBasis'] ?? LegalBasis::LegitimateInterest,
            locale: $overrides['locale'] ?? 'de',
            controllerName: array_key_exists('controllerName', $overrides) ? $overrides['controllerName'] : 'Beispiel GmbH',
            controllerAddress: array_key_exists('controllerAddress', $overrides) ? $overrides['controllerAddress'] : 'Musterweg 1, 50672 Köln',
            agencyName: array_key_exists('agencyName', $overrides) ? $overrides['agencyName'] : 'Agentur GmbH',
            agencyAddress: array_key_exists('agencyAddress', $overrides) ? $overrides['agencyAddress'] : 'Hohenzollernring 1, 50672 Köln',
            hostingProvider: array_key_exists('hostingProvider', $overrides) ? $overrides['hostingProvider'] : 'Hetzner Online GmbH',
            hostingLocation: array_key_exists('hostingLocation', $overrides) ? $overrides['hostingLocation'] : 'Nürnberg, Deutschland',
            disabledActivities: $overrides['disabledActivities'] ?? [],
        );
    }

    /**
     * A section is only really omitted when its heading is gone from both
     * the structured output and the rendered text.
     *
     * @param  list<NoticeSection>  $sections
     */
    private function assertSectionAbsent(NoticeComposer $composer, array $sections, string $heading): void
    {
        $this->assertNull($this->section($sections, $heading), "Section \"{$heading}\" should not have been composed.");

        $this->assertNotContains(
            $heading,
            explode("\n\n", $composer->toPlainText($sections)),
            "Heading \"{$heading}\" still appears in the rendered notice.",
        );

        $this->assertStringNotContainsString('<h3>'.$heading.'</h3>', $composer->toHtml($sections));
    }

    /** @param  list<NoticeSection>  $sections */
    private function section(array $sections, string $heading): ?NoticeSection
    {
        foreach ($sections as $section) {
            if ($section->heading === $heading) {
                return $section;
            }
        }

        return null;
    }

    private function composeWithGeolocation(Project $project, bool $available): string
    {
        $this->app->instance(GeoIpService::class, new class($available) extends GeoIpService
        {
            public function __construct(private bool $available) {}

            public function isAvailable(): bool
            {
                return $this->available;
            }
        });

        $composer = new NoticeComposer;

        return $composer->toPlainText(
            $composer->compose(ProcessingSnapshot::for($project), $this->noticeOptions()),
        );
    }
}
