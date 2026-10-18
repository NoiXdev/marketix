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
use App\Support\Privacy\NoticeOptions;
use App\Support\Privacy\NoticeWarnings;
use App\Support\Privacy\ProcessingSnapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * NoticeWarnings is the blunt, operator-facing counterpart to NoticeComposer:
 * it never gets published, so it is allowed to say plainly what is wrong
 * instead of describing it neutrally.
 */
class NoticeWarningsTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_pixels_produce_a_consent_warning(): void
    {
        $project = $this->projectWithLinks();
        $url = $project->urls()->first();

        Pixel::factory()->create(['project_id' => $project->id, 'provider' => PixelProvider::Facebook])
            ->urls()->attach($url);

        $warnings = NoticeWarnings::for(
            ProcessingSnapshot::for($project),
            $this->noticeOptions(),
        );

        $this->assertWarningPresent($warnings, 'pixels_without_consent');
    }

    public function test_cookie_mode_with_immediate_consent_names_the_affected_domains(): void
    {
        $project = $this->projectWithLinks();

        Site::factory()->forProject($project)->create([
            'domain' => 'immediate-one.example.com',
            'tracking_mode' => TrackingMode::Cookie,
            'consent_mode' => ConsentMode::Immediate,
        ]);

        Site::factory()->forProject($project)->create([
            'domain' => 'immediate-two.example.com',
            'tracking_mode' => TrackingMode::Cookie,
            'consent_mode' => ConsentMode::Immediate,
        ]);

        // A site that does not set a cookie without consent; its domain must
        // not show up in the warning.
        Site::factory()->forProject($project)->create([
            'domain' => 'safe.example.com',
            'tracking_mode' => TrackingMode::Cookieless,
        ]);

        $warnings = NoticeWarnings::for(
            ProcessingSnapshot::for($project),
            $this->noticeOptions(),
        );

        $warning = $this->assertWarningPresent($warnings, 'cookie_without_consent');

        $this->assertStringContainsString('immediate-one.example.com', $warning['message']);
        $this->assertStringContainsString('immediate-two.example.com', $warning['message']);
        $this->assertStringNotContainsString('safe.example.com', $warning['message']);
    }

    public function test_cookie_without_consent_fires_regardless_of_legal_basis(): void
    {
        // A review of the previous task established that selecting
        // LegitimateInterest does not make a cookie set before consent
        // lawful, so this warning must not be gated on the chosen basis.
        $project = $this->projectWithLinks();

        Site::factory()->forProject($project)->create([
            'domain' => 'immediate.example.com',
            'tracking_mode' => TrackingMode::Cookie,
            'consent_mode' => ConsentMode::Immediate,
        ]);

        $warnings = NoticeWarnings::for(
            ProcessingSnapshot::for($project),
            $this->noticeOptions(['legalBasis' => LegalBasis::LegitimateInterest]),
        );

        $warning = $this->assertWarningPresent($warnings, 'cookie_without_consent');
        $this->assertStringContainsString('immediate.example.com', $warning['message']);
    }

    public function test_missing_party_details_are_listed(): void
    {
        $project = $this->projectWithLinks();

        $warnings = NoticeWarnings::for(
            ProcessingSnapshot::for($project),
            $this->noticeOptions([
                'role' => ControllerRole::Client,
                'controllerName' => null,
                'controllerAddress' => null,
                'agencyName' => null,
                'agencyAddress' => null,
                'hostingProvider' => null,
                'hostingLocation' => null,
            ]),
        );

        $warning = $this->assertWarningPresent($warnings, 'missing_party_details');

        foreach ([
            trans('privacy_notice.warnings.fields.controller_name', [], 'de'),
            trans('privacy_notice.warnings.fields.controller_address', [], 'de'),
            trans('privacy_notice.warnings.fields.agency_name', [], 'de'),
            trans('privacy_notice.warnings.fields.agency_address', [], 'de'),
            trans('privacy_notice.warnings.fields.hosting_provider', [], 'de'),
            trans('privacy_notice.warnings.fields.hosting_location', [], 'de'),
        ] as $label) {
            $this->assertStringContainsString($label, $warning['message']);
        }
    }

    public function test_missing_agency_details_are_not_required_for_the_own_company_role(): void
    {
        $project = $this->projectWithLinks();

        $warnings = NoticeWarnings::for(
            ProcessingSnapshot::for($project),
            $this->noticeOptions([
                'role' => ControllerRole::OwnCompany,
                'controllerName' => 'Example Ltd',
                'controllerAddress' => 'Musterweg 1, 50672 Köln',
                'agencyName' => null,
                'agencyAddress' => null,
                'hostingProvider' => 'Hetzner Online GmbH',
                'hostingLocation' => 'Nürnberg, Germany',
            ]),
        );

        $this->assertWarningAbsent($warnings, 'missing_party_details');
    }

    public function test_consent_basis_with_link_clicks_warns_that_the_notice_names_legitimate_interest(): void
    {
        $project = $this->projectWithLinks();

        $warnings = NoticeWarnings::for(
            ProcessingSnapshot::for($project),
            $this->noticeOptions(['legalBasis' => LegalBasis::Consent]),
        );

        $this->assertWarningPresent($warnings, 'consent_claimed_for_link_clicks');
    }

    public function test_consent_basis_without_links_does_not_warn_about_link_clicks(): void
    {
        $project = Project::factory()->create();

        Site::factory()->forProject($project)->create([
            'domain' => 'solo.example.com',
            'tracking_mode' => TrackingMode::Cookieless,
        ]);

        $warnings = NoticeWarnings::for(
            ProcessingSnapshot::for($project),
            $this->noticeOptions(['legalBasis' => LegalBasis::Consent]),
        );

        $this->assertWarningAbsent($warnings, 'consent_claimed_for_link_clicks');
    }

    public function test_a_fully_configured_cookieless_project_without_pixels_has_no_warnings(): void
    {
        $project = $this->projectWithLinks();

        Site::factory()->forProject($project)->create([
            'domain' => 'clean.example.com',
            'tracking_mode' => TrackingMode::Cookieless,
        ]);

        $warnings = NoticeWarnings::for(
            ProcessingSnapshot::for($project),
            $this->noticeOptions([
                'role' => ControllerRole::OwnCompany,
                'legalBasis' => LegalBasis::LegitimateInterest,
                'controllerName' => 'Example Ltd',
                'controllerAddress' => 'Musterweg 1, 50672 Köln',
                'agencyName' => null,
                'agencyAddress' => null,
                'hostingProvider' => 'Hetzner Online GmbH',
                'hostingLocation' => 'Nürnberg, Germany',
            ]),
        );

        $this->assertSame([], $warnings);
    }

    // ── assertions ─────────────────────────────────────────────────────

    /** @param  list<array{key: string, message: string}>  $warnings */
    private function assertWarningPresent(array $warnings, string $key): array
    {
        $matches = array_values(array_filter($warnings, fn (array $warning) => $warning['key'] === $key));

        $this->assertNotEmpty($matches, "Expected a [{$key}] warning, found none.");
        $this->assertNotSame('', trim($matches[0]['message']), "The [{$key}] warning has an empty message.");

        return $matches[0];
    }

    /** @param  list<array{key: string, message: string}>  $warnings */
    private function assertWarningAbsent(array $warnings, string $key): void
    {
        $matches = array_filter($warnings, fn (array $warning) => $warning['key'] === $key);

        $this->assertEmpty($matches, "Did not expect a [{$key}] warning.");
    }

    // ── fixtures ───────────────────────────────────────────────────────

    private function projectWithLinks(): Project
    {
        $project = Project::factory()->create();
        $domain = Domain::factory()->create(['project_id' => $project->id]);
        Url::factory()->create(['project_id' => $project->id, 'domain_id' => $domain->id]);

        return $project;
    }

    /** @param  array<string, mixed>  $overrides */
    private function noticeOptions(array $overrides = []): NoticeOptions
    {
        $party = fn (string $key, ?string $default) => array_key_exists($key, $overrides)
            ? $overrides[$key]
            : $default;

        return new NoticeOptions(
            role: $overrides['role'] ?? ControllerRole::Client,
            legalBasis: $overrides['legalBasis'] ?? LegalBasis::LegitimateInterest,
            locale: 'de',
            controllerName: $party('controllerName', 'Example Ltd'),
            controllerAddress: $party('controllerAddress', 'Musterweg 1, 50672 Köln'),
            agencyName: $party('agencyName', 'Agency Ltd'),
            agencyAddress: $party('agencyAddress', 'Hohenzollernring 1, 50672 Köln'),
            hostingProvider: $party('hostingProvider', 'Hetzner Online GmbH'),
            hostingLocation: $party('hostingLocation', 'Nürnberg, Germany'),
            disabledActivities: $overrides['disabledActivities'] ?? [],
        );
    }
}
