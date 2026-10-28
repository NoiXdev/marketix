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
use App\Support\Privacy\ProcessingSnapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * German is the reference catalog; English, French and Dutch are
 * translations of it. A translation that loses a key renders the key path
 * itself into a published legal text, and one that loses a `:token`
 * renders a sentence with a missing name, domain or number — so both are
 * compared mechanically against German rather than by eye.
 */
class NoticeTranslationsTest extends TestCase
{
    use RefreshDatabase;

    /** The reference locale every other catalog is measured against. */
    private const REFERENCE = 'de';

    /** @var list<string> */
    private const TRANSLATIONS = ['en', 'fr', 'nl'];

    /**
     * Keys the composer never renders on its own, or whose literal text is
     * too short to be searched for in the composed notice. Each one is
     * asserted explicitly in test_a_notice_can_be_composed_in_each_locale
     * instead, so nothing here goes unchecked — with one exception: the
     * `warnings.*` keys are never part of a composed notice at all, by
     * design (NoticeComposer never reads them; NoticeWarnings does). Their
     * key-set and `:token` parity is still enforced by the two tests above
     * this one, and their content is exercised by NoticeWarningsTest.
     *
     * @var list<string>
     */
    private const SEPARATELY_ASSERTED = [
        'roles.own_company',
        'roles.client',
        'legal_bases.legitimate_interest',
        'legal_bases.consent',
        'duration.months',
        'duration.days',
        'controller.party',
        'retention.site',
        'warnings.pixels_without_consent',
        'warnings.cookie_without_consent',
        'warnings.missing_party_details',
        'warnings.consent_claimed_for_link_clicks',
        'warnings.fields.controller_name',
        'warnings.fields.controller_address',
        'warnings.fields.agency_name',
        'warnings.fields.agency_address',
        'warnings.fields.hosting_provider',
        'warnings.fields.hosting_location',
    ];

    public function test_every_locale_defines_the_same_notice_keys(): void
    {
        $reference = array_keys($this->catalog(self::REFERENCE));

        $this->assertNotEmpty($reference, 'The German reference catalog is empty.');

        foreach (self::TRANSLATIONS as $locale) {
            $keys = array_keys($this->catalog($locale));

            $this->assertSame(
                [],
                array_values(array_diff($reference, $keys)),
                "Locale [{$locale}] is missing keys that German defines.",
            );

            $this->assertSame(
                [],
                array_values(array_diff($keys, $reference)),
                "Locale [{$locale}] defines keys German does not.",
            );
        }
    }

    public function test_every_locale_uses_the_same_placeholder_tokens_per_key(): void
    {
        $reference = $this->catalog(self::REFERENCE);

        foreach (self::TRANSLATIONS as $locale) {
            $catalog = $this->catalog($locale);

            foreach ($reference as $key => $value) {
                $this->assertArrayHasKey($key, $catalog, "Locale [{$locale}] is missing [{$key}].");

                $this->assertSame(
                    $this->tokens($value),
                    $this->tokens($catalog[$key]),
                    "The placeholder tokens of [{$key}] differ between German and [{$locale}].",
                );

                $this->assertNotSame(
                    '',
                    trim($catalog[$key]),
                    "Locale [{$locale}] leaves [{$key}] empty.",
                );
            }
        }
    }

    public function test_a_notice_can_be_composed_in_each_locale(): void
    {
        $this->withGeolocation();

        $byLocale = [];

        foreach (array_merge([self::REFERENCE], self::TRANSLATIONS) as $locale) {
            $rendered = [];

            foreach ($this->configurations() as $label => $configuration) {
                [$project, $overrides] = $configuration();

                $composer = new NoticeComposer;
                $text = $composer->toPlainText($composer->compose(
                    ProcessingSnapshot::for($project),
                    $this->noticeOptions($locale, $overrides),
                ));

                $this->assertNotSame('', trim($text), "Configuration [{$label}] composed nothing in [{$locale}].");

                // trans() returns the key path itself when nothing resolves,
                // so an untranslated string shows up as "privacy_notice.".
                // A key missing from one locale only falls back to English
                // instead — that case is what the key parity test is for.
                $this->assertStringNotContainsString(
                    'privacy_notice.',
                    $text,
                    "Configuration [{$label}] leaked an untranslated key path in [{$locale}].",
                );

                $rendered[] = $text;
            }

            // The `roles` and `legal_bases` entries are the form's option
            // labels, rendered by the enums rather than by the composer.
            $this->app->setLocale($locale);

            foreach (ControllerRole::cases() as $role) {
                $this->assertLabel($role->label(), 'roles.'.$role->value, $locale);
            }

            foreach (LegalBasis::cases() as $basis) {
                $this->assertLabel($basis->label(), 'legal_bases.'.$basis->value, $locale);
            }

            $byLocale[$locale] = implode("\n\n", $rendered);
        }

        $this->assertEveryKeyIsExercised($byLocale[self::REFERENCE]);
        $this->assertShortKeysAreExercised($byLocale);
    }

    // ── coverage guards ────────────────────────────────────────────────

    /**
     * The composition test is only worth as much as the configurations it
     * runs: a key that no configuration reaches can be missing from a
     * translation and still pass. This asserts the reference run touched
     * every catalog entry the composer is able to emit.
     */
    private function assertEveryKeyIsExercised(string $referenceOutput): void
    {
        foreach ($this->catalog(self::REFERENCE) as $key => $value) {
            if (in_array($key, self::SEPARATELY_ASSERTED, true)) {
                continue;
            }

            $fragment = $this->longestLiteralFragment($value);

            $this->assertGreaterThan(
                4,
                mb_strlen($fragment),
                "[{$key}] has no searchable literal text; add it to SEPARATELY_ASSERTED with its own assertion.",
            );

            $this->assertStringContainsString(
                $fragment,
                $referenceOutput,
                "No configuration in this test reaches [{$key}], so a missing translation of it would pass unnoticed.",
            );
        }
    }

    /**
     * The entries skipped above: enum labels, the pluralised durations and
     * the two keys that are little more than their own placeholders.
     *
     * @param  array<string, string>  $byLocale
     */
    private function assertShortKeysAreExercised(array $byLocale): void
    {
        foreach ($byLocale as $locale => $output) {
            // duration.months and duration.days, both branches of the
            // retention wording, reached through differing per-site periods.
            $this->assertStringContainsString(
                trans_choice('privacy_notice.duration.months', 24, [], $locale),
                $output,
                "The month form of the retention period is missing in [{$locale}].",
            );

            $this->assertStringContainsString(
                trans_choice('privacy_notice.duration.days', 45, [], $locale),
                $output,
                "The day form of the retention period is missing in [{$locale}].",
            );

            // retention.site, the per-site line carrying both of the above.
            $this->assertStringContainsString(
                trans('privacy_notice.retention.site', [
                    'domain' => 'days.example.com',
                    'duration' => trans_choice('privacy_notice.duration.days', 45, [], $locale),
                ], $locale),
                $output,
                "The per-site retention line is missing in [{$locale}].",
            );

            // controller.party, which is nothing but the two party details.
            $this->assertStringContainsString(
                trans('privacy_notice.controller.party', [
                    'name' => trans('privacy_notice.placeholders.controller_name', [], $locale),
                    'address' => trans('privacy_notice.placeholders.controller_address', [], $locale),
                ], $locale),
                $output,
                "The controller line is missing in [{$locale}].",
            );
        }
    }

    private function assertLabel(string $label, string $key, string $locale): void
    {
        $this->assertNotSame('', trim($label), "[{$key}] is empty in [{$locale}].");
        $this->assertSame(
            $this->catalog($locale)[$key],
            $label,
            "[{$key}] does not come from the [{$locale}] catalog.",
        );
    }

    // ── fixtures ───────────────────────────────────────────────────────

    /**
     * Between them these reach every branch of the composer: both roles,
     * both legal bases, all four site variants, every optional analytics
     * activity, pixels on and off, uniform and per-site retention, and all
     * three scope variants of the IP and rights wording.
     *
     * @return array<string, callable(): array{0: Project, 1: array<string, mixed>}>
     */
    private function configurations(): array
    {
        return [
            // Links and sites, agency as processor, legitimate interest,
            // pixels, every optional activity, retention differing per site.
            'everything' => fn () => [
                $this->projectWithLinksAndSites(withPixel: true),
                [
                    'role' => ControllerRole::Client,
                    'legalBasis' => LegalBasis::LegitimateInterest,
                    // Left blank on purpose: every placeholder has to render.
                    'controllerName' => null,
                    'controllerAddress' => null,
                    'agencyName' => null,
                    'agencyAddress' => null,
                    'hostingProvider' => null,
                    'hostingLocation' => null,
                ],
            ],

            // Own company, consent, no pixels, a single cookieless site:
            // the consent wording, the own-company processor paragraph and
            // the uniform retention sentence.
            'own company on consent' => fn () => [
                $this->projectWithLinksAndSites(withPixel: false, uniform: true),
                [
                    'role' => ControllerRole::OwnCompany,
                    'legalBasis' => LegalBasis::Consent,
                ],
            ],

            // Links only: the clicks-only scope of the IP, legal basis and
            // rights wording.
            'links only' => fn () => [$this->projectWithLinks(), []],

            // Sites only: the sites-only scope of the same three.
            'sites only' => fn () => [$this->projectWithSites(), []],
        ];
    }

    private function projectWithLinksAndSites(bool $withPixel, bool $uniform = false): Project
    {
        $project = $this->projectWithLinks();

        if ($uniform) {
            Site::factory()->forProject($project)->create([
                'domain' => 'only.example.com',
                'tracking_mode' => TrackingMode::Cookieless,
            ]);
        } else {
            // One site per tracking variant, so every per-site sentence of
            // the analytics and cookie sections is rendered.
            Site::factory()->forProject($project)->create([
                'domain' => 'days.example.com',
                'tracking_mode' => TrackingMode::Cookieless,
                'respect_dnt' => true,
                'track_outbound_links' => true,
                'track_file_downloads' => true,
                'site_search_params' => 'q',
                'retention_days' => 45,
            ]);

            Site::factory()->forProject($project)->create([
                'domain' => 'hybrid.example.com',
                'tracking_mode' => TrackingMode::Hybrid,
                'retention_days' => 720,
            ]);

            Site::factory()->forProject($project)->create([
                'domain' => 'cookie.example.com',
                'tracking_mode' => TrackingMode::Cookie,
                'consent_mode' => ConsentMode::Immediate,
                'retention_days' => 720,
            ]);

            Site::factory()->forProject($project)->create([
                'domain' => 'signal.example.com',
                'tracking_mode' => TrackingMode::Cookie,
                'consent_mode' => ConsentMode::ThirdPartySignal,
                'retention_days' => 720,
            ]);
        }

        if ($withPixel) {
            $url = $project->urls()->first();

            Pixel::factory()->create(['project_id' => $project->id, 'provider' => PixelProvider::Facebook])
                ->urls()->attach($url);
        }

        return $project;
    }

    private function projectWithLinks(): Project
    {
        $project = Project::factory()->create();
        $domain = Domain::factory()->create(['project_id' => $project->id]);
        Url::factory()->create(['project_id' => $project->id, 'domain_id' => $domain->id]);

        return $project;
    }

    private function projectWithSites(): Project
    {
        $project = Project::factory()->create();

        Site::factory()->forProject($project)->create([
            'domain' => 'solo.example.com',
            'tracking_mode' => TrackingMode::Cookieless,
        ]);

        return $project;
    }

    /** @param  array<string, mixed>  $overrides */
    private function noticeOptions(string $locale, array $overrides): NoticeOptions
    {
        $party = fn (string $key, ?string $default) => array_key_exists($key, $overrides)
            ? $overrides[$key]
            : $default;

        return new NoticeOptions(
            role: $overrides['role'] ?? ControllerRole::Client,
            legalBasis: $overrides['legalBasis'] ?? LegalBasis::LegitimateInterest,
            locale: $locale,
            controllerName: $party('controllerName', 'Example Ltd'),
            controllerAddress: $party('controllerAddress', 'Musterweg 1, 50672 Köln'),
            agencyName: $party('agencyName', 'Agency Ltd'),
            agencyAddress: $party('agencyAddress', 'Hohenzollernring 1, 50672 Köln'),
            hostingProvider: $party('hostingProvider', 'Hetzner Online GmbH'),
            hostingLocation: $party('hostingLocation', 'Nürnberg, Germany'),
            disabledActivities: $overrides['disabledActivities'] ?? [],
        );
    }

    /** The geolocation and data center passages only render when their databases are present. */
    private function withGeolocation(): void
    {
        $this->app->instance(GeoIpService::class, new class extends GeoIpService
        {
            public function __construct() {}

            public function isAvailable(): bool
            {
                return true;
            }

            public function detectsDataCenters(): bool
            {
                return true;
            }
        });
    }

    // ── catalog helpers ────────────────────────────────────────────────

    /**
     * The catalog of a locale, flattened to dotted key paths.
     *
     * @return array<string, string>
     */
    private function catalog(string $locale): array
    {
        $path = lang_path($locale.'/privacy_notice.php');

        $this->assertFileExists($path, "Locale [{$locale}] has no privacy notice catalog.");

        return $this->flatten(require $path);
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, string>
     */
    private function flatten(array $values, string $prefix = ''): array
    {
        $flat = [];

        foreach ($values as $key => $value) {
            $path = $prefix === '' ? (string) $key : $prefix.'.'.$key;

            if (is_array($value)) {
                $flat += $this->flatten($value, $path);

                continue;
            }

            $flat[$path] = (string) $value;
        }

        return $flat;
    }

    /**
     * The `:token` placeholders of a string, sorted, so a reordered
     * sentence still counts as equivalent.
     *
     * @return list<string>
     */
    private function tokens(string $value): array
    {
        preg_match_all('/:[a-z_]+/', $value, $matches);

        $tokens = array_values(array_unique($matches[0]));
        sort($tokens);

        return $tokens;
    }

    /** The longest run of literal text in a sentence, placeholders removed. */
    private function longestLiteralFragment(string $value): string
    {
        $fragments = array_map('trim', preg_split('/:[a-z_]+/', $value) ?: []);

        usort($fragments, fn (string $a, string $b) => mb_strlen($b) <=> mb_strlen($a));

        return $fragments[0] ?? '';
    }
}
