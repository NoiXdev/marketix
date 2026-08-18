<?php

namespace Database\Seeders;

use App\Enums\RedirectType;
use App\Enums\UrlStatus;
use App\Models\Domain;
use App\Models\Pixel;
use App\Models\Project;
use App\Models\QrCode;
use App\Models\QrTemplate;
use App\Models\ScheduledReport;
use App\Models\Statistic;
use App\Models\Url;
use App\Models\User;
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
}
