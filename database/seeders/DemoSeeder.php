<?php

namespace Database\Seeders;

use App\Enums\CrawlMode;
use App\Enums\CrawlStatus;
use App\Enums\GoalType;
use App\Enums\RedirectType;
use App\Enums\UrlStatus;
use App\Models\Crawl;
use App\Models\CrawlLink;
use App\Models\CrawlPage;
use App\Models\Domain;
use App\Models\Event;
use App\Models\Goal;
use App\Models\PageView;
use App\Models\Pixel;
use App\Models\Project;
use App\Models\QrCode;
use App\Models\QrTemplate;
use App\Models\ScheduledReport;
use App\Models\Site;
use App\Models\Statistic;
use App\Models\Url;
use App\Models\User;
use App\Models\Visit;
use Database\Factories\QrCodeFactory;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Builds the demo instance's data: one fictional company, populated so that
 * every menu item looks alive. All timestamps are relative to now() so the
 * dashboards never look stale, no matter when the last reset ran.
 *
 * Content is deliberately mixed German/English, mirroring a real customer's
 * link inventory.
 */
class DemoSeeder extends Seeder
{
    use WithoutModelEvents;

    public const COMPANY = 'Nordlicht Kaffeerösterei';

    private User $demoUser;

    private Project $project;

    private Domain $domain;

    /** @var Collection<int, Url> */
    private Collection $urls;

    public function run(): void
    {
        $this->seedUsersAndProject();
        $this->seedDomain();
        $this->seedLinks();
        $this->seedMarketingAssets();
        $this->seedAnalytics();
        $this->seedCrawls();
    }

    private function seedUsersAndProject(): void
    {
        $this->demoUser = User::create([
            'name' => 'Demo',
            'email' => config('demo.email'),
            'password' => config('demo.password'),
            'locale' => 'de',
        ]);

        // super_admin is intentionally not mass-assignable.
        $this->demoUser->super_admin = true;
        $this->demoUser->save();

        $this->project = Project::create(['name' => self::COMPANY, 'locked' => false]);
        $this->project->users()->attach($this->demoUser, ['role' => 'admin', 'active' => true]);

        $teammates = [
            ['name' => 'Lena Brandt', 'email' => 'lena@nordlicht.example', 'role' => 'admin', 'locale' => 'de'],
            ['name' => 'Tom Feldkamp', 'email' => 'tom@nordlicht.example', 'role' => 'member', 'locale' => 'de'],
            ['name' => 'Priya Raman', 'email' => 'priya@nordlicht.example', 'role' => 'member', 'locale' => 'en'],
        ];

        foreach ($teammates as $mate) {
            $user = User::create([
                'name' => $mate['name'],
                'email' => $mate['email'],
                'password' => bin2hex(random_bytes(16)),
                'locale' => $mate['locale'],
            ]);

            $this->project->users()->attach($user, ['role' => $mate['role'], 'active' => true]);
        }
    }

    private function seedDomain(): void
    {
        $this->domain = Domain::create([
            'project_id' => $this->project->id,
            'name' => 'nord.'.config('app.domain'),
            'redirect_root' => 'https://example.com/',
            'redirect_not_found' => 'https://example.com/404',
        ]);
    }

    private function seedLinks(): void
    {
        $inventory = [
            ['slug' => 'sommeraktion', 'name' => 'Sommeraktion 2026', 'url' => 'https://example.com/sommeraktion', 'weight' => 5],
            ['slug' => 'newsletter', 'name' => 'Newsletter-Anmeldung', 'url' => 'https://example.com/newsletter', 'weight' => 3],
            ['slug' => 'shop', 'name' => 'Onlineshop', 'url' => 'https://example.com/shop', 'weight' => 4],
            ['slug' => 'espresso-guide', 'name' => 'Espresso Guide (PDF)', 'url' => 'https://example.com/guides/espresso.pdf', 'weight' => 2],
            ['slug' => 'wholesale', 'name' => 'Wholesale enquiry', 'url' => 'https://example.com/wholesale', 'weight' => 2],
            ['slug' => 'instagram', 'name' => 'Instagram', 'url' => 'https://example.com/social/instagram', 'weight' => 3],
            ['slug' => 'linkedin', 'name' => 'LinkedIn', 'url' => 'https://example.com/social/linkedin', 'weight' => 1],
            ['slug' => 'roastery-tour', 'name' => 'Röstereiführung buchen', 'url' => 'https://example.com/tour', 'weight' => 2],
            ['slug' => 'abo', 'name' => 'Kaffee-Abo', 'url' => 'https://example.com/abo', 'weight' => 4],
            ['slug' => 'karriere', 'name' => 'Karriere', 'url' => 'https://example.com/karriere', 'weight' => 1],
            ['slug' => 'press-kit', 'name' => 'Press kit', 'url' => 'https://example.com/press', 'weight' => 1],
            ['slug' => 'menu', 'name' => 'Café-Karte', 'url' => 'https://example.com/menu', 'weight' => 3],
            ['slug' => 'gutschein', 'name' => 'Geschenkgutschein', 'url' => 'https://example.com/gutschein', 'weight' => 2],
            ['slug' => 'faq', 'name' => 'FAQ', 'url' => 'https://example.com/faq', 'weight' => 1],
            ['slug' => 'store-locator', 'name' => 'Store locator', 'url' => 'https://example.com/stores', 'weight' => 2],
            ['slug' => 'workshop', 'name' => 'Barista-Workshop', 'url' => 'https://example.com/workshop', 'weight' => 2],
            ['slug' => 'b2b-katalog', 'name' => 'B2B-Katalog', 'url' => 'https://example.com/b2b', 'weight' => 1],
            ['slug' => 'nachhaltigkeit', 'name' => 'Nachhaltigkeitsbericht', 'url' => 'https://example.com/impact', 'weight' => 1],
        ];

        $memberIds = $this->project->users()->pluck('users.id')->all();

        $this->urls = collect($inventory)->map(function (array $item) use ($memberIds) {
            // forceCreate: 'created_at' is intentionally absent from Url::$fillable
            // (mass-assignment guards timestamps), so create() would silently drop
            // it and every link would show as created today.
            $url = Url::forceCreate([
                'project_id' => $this->project->id,
                'domain_id' => $this->domain->id,
                'user_id' => $memberIds[array_rand($memberIds)],
                'slug' => $item['slug'],
                'url' => $item['url'],
                'type' => RedirectType::cases()[0],
                'status' => UrlStatus::ACTIVATED,
                'archived' => false,
                'targeting_geo' => [],
                'targeting_device' => [],
                'targeting_language' => [],
                'targeting_ab' => [],
                'created_at' => now()->subDays(random_int(95, 140)),
            ]);

            $this->seedClicks($url, $item['weight']);

            return $url;
        });
    }

    /**
     * 90 days of clicks with a weekday rhythm and one campaign spike, so the
     * dashboard charts tell a story instead of showing flat noise.
     */
    private function seedClicks(Url $url, int $weight): void
    {
        $countries = [
            ['Germany', 'DE', 'Hamburg', 'de'],
            ['Germany', 'DE', 'Berlin', 'de'],
            ['Austria', 'AT', 'Vienna', 'de'],
            ['Switzerland', 'CH', 'Zurich', 'de'],
            ['Netherlands', 'NL', 'Amsterdam', 'nl'],
            ['France', 'FR', 'Lyon', 'fr'],
            ['United Kingdom', 'GB', 'Manchester', 'en'],
            ['United States', 'US', 'Portland', 'en'],
        ];

        $browsers = ['Chrome', 'Safari', 'Firefox', 'Edge'];
        $systems = ['iOS', 'Android', 'macOS', 'Windows'];
        $referers = [
            'https://www.google.com/', 'https://www.instagram.com/',
            'https://www.linkedin.com/', null, null,
        ];

        // First pass: how many clicks land on each day, using the same
        // weekday/weekend/spike formula as before. Computed up front (and
        // reused, not re-rolled) so the total click volume is known before
        // sizing the returning-visitor pool below.
        $dailyCounts = [];
        for ($day = 89; $day >= 0; $day--) {
            $isWeekend = now()->subDays($day)->isWeekend();
            $spike = ($day >= 40 && $day <= 46) ? 3 : 1;
            $dailyCounts[$day] = (int) round($weight * ($isWeekend ? 1.5 : 3.5) * $spike * (0.6 + lcg_value()));
        }

        $totalClicks = array_sum($dailyCounts);

        // Bounded pool of "returning visitors" per link: drawing from a pool
        // sized to ~1/0.7 of total volume makes roughly 70% of draws land on
        // a fresh slot (coupon-collector effect), so real repeat visits occur
        // instead of every click hashing to a brand-new, never-repeated visitor.
        $visitorPoolSize = max(5, (int) round($totalClicks / 0.7));

        $rows = [];

        foreach ($dailyCounts as $day => $count) {
            $date = now()->subDays($day);

            for ($i = 0; $i < $count; $i++) {
                [$country, $code, $city, $language] = $countries[array_rand($countries)];
                $referer = $referers[array_rand($referers)];
                $at = $date->copy()->setTime(random_int(6, 22), random_int(0, 59));

                $rows[] = [
                    'id' => (string) Str::ulid(),
                    'project_id' => $this->project->id,
                    'url_id' => $url->id,
                    'visitor_hash' => hash('sha256', $url->id.'-visitor-'.random_int(0, $visitorPoolSize - 1)),
                    'country' => $country,
                    'country_code' => $code,
                    'city' => $city,
                    'language' => $language,
                    // Mirrors RecordClickStatisticJob::handle(): 'domain' is the
                    // referrer's hostname, not the link's own domain — a link's
                    // own domain would make every click "self-referred" and
                    // flatten the Top Referrers breakdown to one value.
                    'domain' => $referer ? parse_url($referer, PHP_URL_HOST) : null,
                    'referer' => $referer,
                    'browser' => $browsers[array_rand($browsers)],
                    'os' => $systems[array_rand($systems)],
                    'is_bot' => false,
                    'created_at' => $at,
                    'updated_at' => $at,
                ];
            }
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            Statistic::insert($chunk);
        }

        // Derived from the same rows just inserted (not a fudge factor), so
        // this denormalized counter can never disagree with the live
        // distinct-visitor_hash count the link detail page computes.
        $uniqueClicks = count(array_unique(array_column($rows, 'visitor_hash')));

        $url->fill([
            'clicks' => count($rows),
            'unique_clicks' => $uniqueClicks,
        ])->save();
    }

    /**
     * QR codes, two branded templates, tracking pixels and scheduled
     * reports, so every "Marketing" menu item has something in it.
     *
     * QR `type`/`content` shapes verified against
     * resources/js/data/qrTypes.ts (the frontend's canonical content
     * builder) so nothing here encodes to an empty payload. Pixel
     * `provider` values verified against App\Enums\PixelProvider (there is
     * no "meta" case — Meta's pixel is the "facebook" provider there, so
     * the pixel keeps the "Meta Pixel" name customers actually use while
     * carrying the "facebook" provider value the app recognises).
     *
     * Two of the QR codes ('menu' and 'workshop') are attached to their
     * matching Url from seedLinks() and marked dynamic: QrCodeController
     * ::index() computes the list's scans/unique_scans straight from the
     * backing Url's clicks/unique_clicks (qr_codes itself has no scans
     * column — see 2026_06_17_000000_link_qr_codes_to_urls.php), so a
     * dynamic QR on a link that already has 90 days of click history shows
     * real scan numbers with no separate scan-seeding needed. Leaving every
     * QR static would make the whole list show a dash in that column, with
     * nothing to demonstrate QR scan tracking exists.
     */
    private function seedMarketingAssets(): void
    {
        $qrDefinitions = [
            ['name' => 'Café-Karte (Tischaufsteller)', 'type' => 'link', 'content' => ['url' => 'https://example.com/menu'], 'attach_slug' => 'menu'],
            ['name' => 'Verpackung — Herkunft', 'type' => 'link', 'content' => ['url' => 'https://example.com/impact']],
            ['name' => 'Workshop-Anmeldung', 'type' => 'link', 'content' => ['url' => 'https://example.com/workshop'], 'attach_slug' => 'workshop'],
            ['name' => 'Wholesale contact', 'type' => 'email', 'content' => ['email' => 'wholesale@nordlicht.example']],
            ['name' => 'Roastery phone', 'type' => 'phone', 'content' => ['phone' => '+4940123456']],
            ['name' => 'WLAN Gastzugang', 'type' => 'wifi', 'content' => ['ssid' => 'Nordlicht Gast', 'password' => 'espresso', 'encryption' => 'WPA', 'hidden' => 'false']],
        ];

        foreach ($qrDefinitions as $definition) {
            $attachUrl = isset($definition['attach_slug'])
                ? $this->urls->firstWhere('slug', $definition['attach_slug'])
                : null;

            QrCode::factory()
                ->forProject($this->project)
                ->create([
                    'name' => $definition['name'],
                    'type' => $definition['type'],
                    'is_dynamic' => $attachUrl !== null,
                    'url_id' => $attachUrl?->id,
                    'content' => $definition['content'],
                ]);
        }

        foreach (['Nordlicht Primär', 'Nordlicht Invers'] as $index => $name) {
            QrTemplate::factory()
                ->forProject($this->project)
                ->create([
                    'name' => $name,
                    'style' => array_merge(QrCodeFactory::defaultStyle(), [
                        'foreground' => $index === 0 ? '#1d3b2a' : '#ffffff',
                        'background' => $index === 0 ? '#ffffff' : '#1d3b2a',
                        'module_mode' => 'rounded',
                        'module_rounding' => 0.6,
                    ]),
                ]);
        }

        Pixel::factory()->forProject($this->project)->create([
            'provider' => 'google_analytics',
            'name' => 'Google Analytics',
        ]);
        Pixel::factory()->forProject($this->project)->create([
            'provider' => 'facebook',
            'name' => 'Meta Pixel',
        ]);

        ScheduledReport::factory()
            ->forProject($this->project, $this->demoUser)
            ->create([
                'name' => 'Wochenreport Marketing',
                'type' => 'project_summary',
                'frequency' => 'weekly',
                'weekday' => 1,
                'period' => 'last_7_days',
                'recipients' => ['marketing@nordlicht.example'],
                'last_sent_at' => now()->subDays(3),
                'next_run_at' => now()->addDays(4),
            ]);

        ScheduledReport::factory()
            ->forProject($this->project, $this->demoUser)
            ->create([
                'name' => 'Monthly link performance',
                'type' => 'link',
                'subject_id' => $this->urls->first()->id,
                'frequency' => 'monthly',
                'weekday' => null,
                'day_of_month' => 1,
                'period' => 'previous_month',
                'recipients' => ['ceo@nordlicht.example'],
                'next_run_at' => now()->addMonth()->startOfMonth(),
            ]);
    }

    /**
     * 90 days of visits/page views/events on one analytics Site, plus two
     * goals, so the analytics dashboard, goals page and events page all
     * have a real story instead of an empty state.
     *
     * Per-day visit counts are deliberately lower than a real Nordlicht
     * would see (see the halved weekday/weekend base below) — the original
     * brief numbers made this loop the slowest part of DemoSeederTest.
     * The tests only assert lower bounds, not exact totals, so this stays
     * well within them.
     */
    private function seedAnalytics(): void
    {
        $site = Site::factory()->forProject($this->project)->create([
            'name' => self::COMPANY,
            'domain' => 'nordlicht.example',
            // Site::booted()'s creating() hook normally assigns this, but
            // DemoSeeder uses WithoutModelEvents, so it never fires here.
            'tracking_id' => Site::generateTrackingId(),
        ]);

        $paths = ['/', '/shop', '/shop/espresso', '/abo', '/workshop', '/menu', '/impact', '/kontakt'];
        $sources = [
            ['google', 'organic', null],
            ['newsletter', 'email', 'sommeraktion'],
            ['instagram', 'social', 'sommeraktion'],
            [null, null, null],
        ];

        for ($day = 89; $day >= 0; $day--) {
            $date = now()->subDays($day);
            $visits = (int) round(($date->isWeekend() ? 6 : 14) * (0.7 + lcg_value()));

            for ($v = 0; $v < $visits; $v++) {
                [$source, $medium, $campaign] = $sources[array_rand($sources)];
                $startedAt = $date->copy()->setTime(random_int(7, 21), random_int(0, 59));

                $visit = Visit::factory()->forSite($site)->create([
                    'started_at' => $startedAt,
                    'last_activity_at' => $startedAt->copy()->addMinutes(random_int(1, 12)),
                    'entry_path' => $paths[array_rand($paths)],
                    'exit_path' => $paths[array_rand($paths)],
                    'utm_source' => $source,
                    'utm_medium' => $medium,
                    'utm_campaign' => $campaign,
                    'created_at' => $startedAt,
                    'updated_at' => $startedAt,
                ]);

                $views = random_int(1, 4);

                for ($p = 0; $p < $views; $p++) {
                    PageView::factory()->forVisit($visit)->create([
                        'path' => $paths[array_rand($paths)],
                        'utm_source' => $source,
                        'utm_medium' => $medium,
                        'utm_campaign' => $campaign,
                        'created_at' => $startedAt->copy()->addMinutes($p),
                    ]);
                }

                $visit->forceFill(['pageview_count' => $views])->save();

                // Roughly one in eight visits converts.
                if (random_int(1, 8) === 1) {
                    Event::factory()->forVisit($visit)->create([
                        'name' => 'newsletter_signup',
                        'path' => '/newsletter',
                        'created_at' => $startedAt->copy()->addMinutes($views),
                    ]);
                }
            }
        }

        Goal::factory()->forSite($site)->create([
            'name' => 'Newsletter-Anmeldung',
            'type' => GoalType::Event,
            'match_value' => 'newsletter_signup',
        ]);

        Goal::factory()->forSite($site)->create([
            'name' => 'Abo-Abschluss',
            'type' => GoalType::Event,
            'match_value' => 'subscription_started',
        ]);
    }

    /**
     * Two completed v3 SEO crawls so a prospect can open a full issue
     * report immediately instead of waiting for a live crawl.
     *
     * CrawlPageFactory's default only sets `url` and `status_code` — a page
     * with nothing else renders an empty issue report. crawlPageFindings()
     * below fills in the columns the crawler UI actually reads (verified
     * against app/Models/CrawlPage.php, the crawl_pages migrations, and
     * resources/js/Pages/Crawls/{Show,Page}.tsx + app/Http/Controllers/
     * CrawlController.php):
     *   - `content_category`, `issues` — drive the category sidebar counts,
     *     the "all URLs" table's issue count, and CrawlScore.
     *   - `title`/`meta_description` (+ their _length columns) — drive the
     *     SERP preview and on-page meta card on the page detail view.
     *   - `word_count`, `is_indexable` — shown on the page detail view.
     *   - `structured_data_items` — renders the structured-data card on the
     *     page detail view.
     * A handful of indices are given specific, realistic findings; every
     * other page gets plausible filler content with no issues, so the
     * "clean" pages don't stand out as obviously fake.
     *
     * `crawl_links` also gets one real row (Presse & News → the 404 page)
     * so "Broken link" has an actual reference to show, matching how
     * AggregateCrawlJob::checkBrokenLinks() would have recorded it.
     */
    private function seedCrawls(): void
    {
        $runs = [
            ['url' => 'https://example.com', 'pages' => 24, 'finished' => 3],
            ['url' => 'https://example.com/shop', 'pages' => 18, 'finished' => 21],
        ];

        foreach ($runs as $run) {
            $crawl = Crawl::factory()->create([
                'project_id' => $this->project->id,
                'start_url' => $run['url'],
                'mode' => CrawlMode::FullSite,
                'max_pages' => 25,
                'status' => CrawlStatus::Completed,
                'pages_crawled' => $run['pages'],
                'started_at' => now()->subDays($run['finished'])->subMinutes(4),
                'finished_at' => now()->subDays($run['finished']),
                'created_at' => now()->subDays($run['finished'])->subMinutes(5),
            ]);

            $summary = [];
            $pages = [];

            for ($i = 0; $i < $run['pages']; $i++) {
                $url = rtrim($run['url'], '/').'/'.Str::slug('seite-'.($i + 1));

                $page = CrawlPage::factory()->create(array_merge([
                    'crawl_id' => $crawl->id,
                    'url' => $url,
                    // A believable mix: mostly 200, a couple of redirects and one 404.
                    'status_code' => match (true) {
                        $i === 3 => 301,
                        $i === 7 => 404,
                        default => 200,
                    },
                ], $this->crawlPageFindings($i, $url)));

                $pages[$i] = $page;

                foreach ($page->issues ?? [] as $code) {
                    $summary[$code] = ($summary[$code] ?? 0) + 1;
                }
            }

            // A broken internal link: the "Presse & News" page (index 8) links
            // to the page that 404s (index 7). Its own `broken_link` issue was
            // already set by crawlPageFindings() above.
            if (isset($pages[8], $pages[7])) {
                CrawlLink::create([
                    'crawl_id' => $crawl->id,
                    'from_page_id' => $pages[8]->id,
                    'to_url' => $pages[7]->url,
                    'type' => 'internal',
                    'status_code' => 404,
                ]);
            }

            $crawl->update(['summary' => $summary]);
        }
    }

    /**
     * Per-index content/issue overrides for seedCrawls(). A handful of fixed
     * indices carry a specific, real finding (duplicate title, missing meta
     * description, an over-long title, a structured-data parse error, the
     * 404 page, and the broken-link source page); everything else gets
     * unique, issue-free filler content.
     *
     * @return array<string, mixed>
     */
    private function crawlPageFindings(int $i, string $url): array
    {
        $duplicateTitle = 'Kaffee kaufen – Online Shop';

        return match (true) {
            $i === 0 => $this->pageAttrs(
                title: 'Nordlicht Kaffeerösterei – Rösterei & Onlineshop',
                description: 'Frisch geröstete Spezialitätenkaffees direkt aus unserer Rösterei in Hamburg – Espresso, Filterkaffee und Kaffee-Abos.',
                wordCount: 420,
            ),
            $i === 1 => $this->pageAttrs(
                title: $duplicateTitle,
                description: 'Entdecke unsere Kaffeesorten im Online-Shop: Espresso, Filterkaffee und Geschenksets.',
                wordCount: 260,
                issues: ['duplicate_title'],
            ),
            $i === 2 => $this->pageAttrs(
                title: $duplicateTitle,
                description: 'Bestelle deinen Lieblingskaffee bequem online – versandkostenfrei ab 40€.',
                wordCount: 240,
                issues: ['duplicate_title'],
            ),
            $i === 3 => [
                // Redirect target — mirrors what RedirectClassifier would record
                // for a single 301 hop. No HTML body was fetched, so no meta
                // fields apply.
                'final_url' => rtrim($url, '/').'-neu',
                'redirect_chain' => [$url, rtrim($url, '/').'-neu'],
                'content_category' => null,
                'is_indexable' => false,
                'issues' => ['internal_redirect_3xx'],
            ],
            $i === 4 => $this->pageAttrs(
                title: 'Über uns',
                description: null,
                wordCount: 180,
                issues: ['missing_meta_description'],
            ),
            $i === 5 => $this->pageAttrs(
                title: 'Nachhaltiger Kaffeegenuss aus fairem Handel und direkter Röstpartnerschaft mit Kleinbauern',
                description: 'Mehr über unsere Handelspartnerschaften und die Herkunft unserer Rohkaffees.',
                wordCount: 310,
                issues: ['title_too_long'],
            ),
            $i === 6 => array_merge(
                $this->pageAttrs(
                    title: 'Kaffee-Abo Übersicht',
                    description: 'Wähle dein persönliches Kaffee-Abo — flexibel kündbar, jederzeit anpassbar.',
                    wordCount: 300,
                    issues: ['structured_data_parse_error'],
                ),
                ['structured_data_items' => [
                    ['format' => 'json-ld', 'type' => null, 'valid' => false, 'missing' => [], 'error' => 'parse'],
                ]],
            ),
            $i === 7 => [
                // The 404 page the broken link (index 8) points at.
                'content_category' => null,
                'is_indexable' => false,
                'issues' => ['client_error'],
            ],
            $i === 8 => $this->pageAttrs(
                title: 'Presse & News',
                description: 'Aktuelle Pressemitteilungen und News rund um die Nordlicht Kaffeerösterei.',
                wordCount: 220,
                issues: ['broken_link'],
            ),
            default => $this->pageAttrs(
                title: 'Produktseite '.($i + 1).' – Nordlicht Kaffeerösterei',
                description: 'Detailinformationen zu unserem Kaffeeprodukt Nummer '.($i + 1).'.',
                wordCount: 150 + ($i * 7 % 300),
            ),
        };
    }

    /**
     * @param  string[]  $issues
     * @return array<string, mixed>
     */
    private function pageAttrs(?string $title, ?string $description, int $wordCount, array $issues = []): array
    {
        return [
            'title' => $title,
            'title_length' => $title !== null ? mb_strlen($title) : 0,
            'meta_description' => $description,
            'meta_description_length' => $description !== null ? mb_strlen($description) : 0,
            'word_count' => $wordCount,
            'content_category' => 'html',
            'is_indexable' => true,
            'in_sitemap' => true,
            'issues' => $issues,
        ];
    }
}
