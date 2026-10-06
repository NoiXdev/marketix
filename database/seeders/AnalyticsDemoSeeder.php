<?php

namespace Database\Seeders;

use App\Enums\ConsentMode;
use App\Enums\GoalType;
use App\Enums\TrackingMode;
use App\Models\Funnel;
use App\Models\Goal;
use App\Models\Project;
use App\Models\Site;
use App\Support\Analytics\ReturningVisits;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AnalyticsDemoSeeder extends Seeder
{
    private const DAYS = 400;

    private const BASE_DAILY_VISITS = 140;

    private const DOMAIN = 'demo-shop.ch';

    private const HOUR_WEIGHTS = [1, 0.6, 0.4, 0.3, 0.3, 0.6, 1.5, 3, 4.5, 5, 5, 4.8, 4.5, 4.8, 5, 4.8, 4.5, 4.5, 5, 5.5, 5, 4, 2.8, 1.8];

    private const WEEKDAY_FACTORS = [1 => 1.05, 2 => 1.1, 3 => 1.08, 4 => 1.0, 5 => 0.92, 6 => 0.7, 7 => 0.75];

    private const PAGES = [
        '/' => 30, '/produkte' => 14, '/produkte/kaffeemaschine-pro' => 6, '/produkte/milchschaeumer' => 4,
        '/produkte/bohnen-espresso' => 5, '/preise' => 6, '/blog' => 5, '/blog/perfekter-espresso' => 5,
        '/blog/latte-art-guide' => 4, '/blog/entkalken' => 3, '/ueber-uns' => 3, '/kontakt' => 4,
        '/warenkorb' => 4, '/newsletter' => 2, '/faq' => 3, '/jobs' => 1,
    ];

    private const ENTRY_PAGES = [
        '/' => 40, '/blog/perfekter-espresso' => 10, '/produkte' => 10, '/produkte/kaffeemaschine-pro' => 8,
        '/blog/latte-art-guide' => 7, '/preise' => 6, '/blog/entkalken' => 5, '/produkte/bohnen-espresso' => 4,
        '/faq' => 3, '/jobs' => 1, '/sommer-sale-2025' => 1, '/shop/espresso-maschine' => 1,
    ];

    private const COUNTRIES = [
        'CH' => ['Switzerland', ['Zürich', 'Bern', 'Basel', 'Luzern', 'St. Gallen', 'Genève', 'Lausanne'], 38],
        'DE' => ['Germany', ['Berlin', 'München', 'Hamburg', 'Stuttgart', 'Köln'], 24],
        'AT' => ['Austria', ['Wien', 'Graz', 'Linz'], 8],
        'US' => ['United States', ['New York', 'San Francisco', 'Chicago'], 7],
        'FR' => ['France', ['Paris', 'Lyon'], 5],
        'IT' => ['Italy', ['Milano', 'Roma'], 4],
        'NL' => ['Netherlands', ['Amsterdam', 'Rotterdam'], 3],
        'GB' => ['United Kingdom', ['London', 'Manchester'], 3],
        'ES' => ['Spain', ['Madrid', 'Barcelona'], 2],
        'SE' => ['Sweden', ['Stockholm'], 1],
        'PL' => ['Poland', ['Warsaw'], 1],
        'CA' => ['Canada', ['Toronto'], 1],
        'BR' => ['Brazil', ['São Paulo'], 1],
        'JP' => ['Japan', ['Tokyo'], 1],
        'AU' => ['Australia', ['Sydney'], 1],
    ];

    private const HOSTS = ['www.demo-shop.ch' => 96, 'demo-shop.ch' => 3, 'staging.demo-shop.ch' => 1];

    private const TITLES = [
        '/' => 'Demo Shop – Kaffeemaschinen & Zubehör', '/produkte' => 'Alle Produkte – Demo Shop',
        '/produkte/kaffeemaschine-pro' => 'Kaffeemaschine Pro – Demo Shop', '/produkte/milchschaeumer' => 'Milchschäumer – Demo Shop',
        '/produkte/bohnen-espresso' => 'Espresso-Bohnen – Demo Shop', '/preise' => 'Preise – Demo Shop', '/blog' => 'Blog – Demo Shop',
        '/blog/perfekter-espresso' => 'So gelingt der perfekte Espresso – Demo Shop', '/blog/latte-art-guide' => 'Latte Art für Einsteiger – Demo Shop',
        '/blog/entkalken' => 'Kaffeemaschine richtig entkalken – Demo Shop', '/ueber-uns' => 'Über uns – Demo Shop',
        '/kontakt' => 'Kontakt – Demo Shop', '/warenkorb' => 'Warenkorb – Demo Shop', '/newsletter' => 'Newsletter – Demo Shop',
        '/faq' => 'Häufige Fragen – Demo Shop', '/jobs' => 'Jobs – Demo Shop', '/aktion/herbst-sale' => 'Herbst-Sale: bis 30 % Rabatt – Demo Shop',
        '/sommer-sale-2025' => 'Seite nicht gefunden – Demo Shop', '/shop/espresso-maschine' => 'Seite nicht gefunden – Demo Shop',
    ];

    private const REGIONS = [
        'Zürich' => 'Zurich', 'Bern' => 'Bern', 'Basel' => 'Basel-City', 'Luzern' => 'Lucerne', 'St. Gallen' => 'Saint Gallen',
        'Genève' => 'Geneva', 'Lausanne' => 'Vaud', 'Berlin' => 'Land Berlin', 'München' => 'Bavaria', 'Hamburg' => 'Hamburg',
        'Stuttgart' => 'Baden-Wurttemberg', 'Köln' => 'North Rhine-Westphalia', 'Wien' => 'Vienna', 'Graz' => 'Styria',
        'Linz' => 'Upper Austria', 'New York' => 'New York', 'San Francisco' => 'California', 'Chicago' => 'Illinois',
        'Paris' => 'Île-de-France', 'Lyon' => 'Auvergne-Rhône-Alpes', 'Milano' => 'Lombardy', 'Roma' => 'Lazio',
        'Amsterdam' => 'North Holland', 'Rotterdam' => 'South Holland', 'London' => 'England', 'Manchester' => 'England',
        'Madrid' => 'Madrid', 'Barcelona' => 'Catalonia', 'Stockholm' => 'Stockholm County', 'Warsaw' => 'Mazovia',
        'Toronto' => 'Ontario', 'São Paulo' => 'São Paulo', 'Tokyo' => 'Tokyo', 'Sydney' => 'New South Wales',
    ];

    private const LANGUAGES = [
        'CH' => ['de' => 70, 'fr' => 20, 'it' => 5, 'en' => 5],
        'DE' => ['de' => 92, 'en' => 8],
        'AT' => ['de' => 92, 'en' => 8],
        'FR' => ['fr' => 95, 'en' => 5],
        'IT' => ['it' => 95, 'en' => 5],
        'NL' => ['nl' => 80, 'en' => 20],
        'ES' => ['es' => 85, 'en' => 15],
        'PL' => ['pl' => 85, 'en' => 15],
        'SE' => ['sv' => 70, 'en' => 30],
        'JP' => ['ja' => 85, 'en' => 15],
        'BR' => ['pt' => 90, 'en' => 10],
    ];

    private const PLATFORMS = [
        ['Desktop', 'Windows', 'Chrome', 22], ['Desktop', 'Windows', 'Edge', 8], ['Desktop', 'Windows', 'Firefox', 4],
        ['Desktop', 'macOS', 'Safari', 8], ['Desktop', 'macOS', 'Chrome', 8], ['Desktop', 'macOS', 'Firefox', 2],
        ['Desktop', 'Linux', 'Firefox', 2], ['Desktop', 'Linux', 'Chrome', 1], ['Mobile', 'iOS', 'Safari', 20],
        ['Mobile', 'iOS', 'Chrome', 3], ['Mobile', 'Android', 'Chrome', 14], ['Mobile', 'Android', 'Opera', 1],
        ['Mobile', 'Android', 'Other', 2], ['Tablet', 'iOS', 'Safari', 4], ['Tablet', 'Android', 'Chrome', 1],
    ];

    private const SEARCH_ENGINES = ['www.google.com' => 60, 'www.google.ch' => 20, 'www.bing.com' => 8, 'duckduckgo.com' => 7, 'www.ecosia.org' => 5];

    private const SOCIAL_SITES = ['l.facebook.com' => 30, 'www.instagram.com' => 25, 'www.linkedin.com' => 20, 't.co' => 10, 'www.reddit.com' => 15];

    private const REFERRAL_SITES = ['www.kaffee-magazin.ch' => 30, 'www.watson.ch' => 20, 'www.blick.ch' => 15, 'medium.com' => 15, 'www.heise.de' => 10, 'github.com' => 10];

    private const NOT_FOUND_PAGES = ['/sommer-sale-2025', '/shop/espresso-maschine'];

    private const SEARCH_TERMS = ['espressomaschine', 'entkalker', 'milchschäumer', 'bohnen', 'siebträger', 'reparatur', 'gutschein', 'tamper'];

    private const OUTBOUND_URLS = ['www.instagram.com/demoshop', 'www.youtube.com/@demoshop', 'www.kaffee-magazin.ch/test', 'github.com/demoshop'];

    private const PRODUCTS = [
        ['Kaffeemaschine Pro', 899], ['Milchschäumer', 89], ['Espresso Bohnen 1 kg', 39], ['Entkalker-Set', 19], ['Tamper Edelstahl', 49],
    ];

    private array $visitorPool = [];

    private array $visits = [];

    private array $pageViews = [];

    private array $events = [];

    private Site $site;

    private CarbonImmutable $now;

    public function run(): void
    {
        mt_srand(20261005);
        $this->now = CarbonImmutable::now();

        $project = Project::query()->orderBy('created_at')->first() ?? Project::create(['name' => 'Demo Project']);
        $this->site = $this->resetSite($project);
        $this->createGoals();

        for ($offset = self::DAYS - 1; $offset >= 0; $offset--) {
            $this->seedDay($this->now->subDays($offset)->startOfDay(), $offset);
        }

        $this->seedLiveVisits();
        $this->flush(true);
        ReturningVisits::backfill($this->site->id);

        $this->command?->info(sprintf(
            'Seeded analytics demo site "%s" (%s) in project "%s".',
            $this->site->name,
            $this->site->domain,
            $project->name,
        ));
    }

    private function resetSite(Project $project): Site
    {
        $site = Site::query()->where('project_id', $project->id)->where('domain', self::DOMAIN)->first();

        if ($site !== null) {
            DB::table('visits')->where('site_id', $site->id)->delete();
            $site->goals()->withTrashed()->forceDelete();
            $site->funnels()->withTrashed()->forceDelete();
            $site->update(['site_search_params' => 'q']);

            return $site;
        }

        return Site::create([
            'project_id' => $project->id,
            'name' => 'Demo Shop',
            'domain' => self::DOMAIN,
            'tracking_id' => Site::generateTrackingId(),
            'tracking_mode' => TrackingMode::Cookieless,
            'consent_mode' => ConsentMode::Immediate,
            'respect_dnt' => false,
            'site_search_params' => 'q',
        ]);
    }

    private function createGoals(): void
    {
        $goals = [
            ['Kauf abgeschlossen', GoalType::Event, 'purchase'],
            ['Newsletter-Anmeldung', GoalType::Event, 'newsletter_subscribe'],
            ['Kontaktseite besucht', GoalType::Pageview, '/kontakt'],
            ['Blog gelesen', GoalType::Pageview, '/blog/*'],
        ];

        $funnels = [
            'Kaufprozess' => [
                ['type' => 'pageview', 'value' => '/produkte/*', 'label' => 'Produkt angesehen'],
                ['type' => 'pageview', 'value' => '/warenkorb', 'label' => 'Warenkorb geöffnet'],
                ['type' => 'event', 'value' => 'purchase', 'label' => 'Kauf abgeschlossen'],
            ],
            'Newsletter' => [
                ['type' => 'pageview', 'value' => '/blog/*', 'label' => 'Blogartikel gelesen'],
                ['type' => 'pageview', 'value' => '/newsletter', 'label' => 'Newsletter-Seite besucht'],
                ['type' => 'event', 'value' => 'newsletter_subscribe', 'label' => 'Angemeldet'],
            ],
        ];

        foreach ($funnels as $name => $steps) {
            Funnel::create([
                'project_id' => $this->site->project_id,
                'site_id' => $this->site->id,
                'name' => $name,
                'steps' => $steps,
            ]);
        }

        foreach ($goals as [$name, $type, $match]) {
            Goal::create([
                'project_id' => $this->site->project_id,
                'site_id' => $this->site->id,
                'name' => $name,
                'type' => $type,
                'match_value' => $match,
            ]);
        }
    }

    private function seedDay(CarbonImmutable $day, int $offset): void
    {
        $progress = 1 - $offset / self::DAYS;
        $volume = self::BASE_DAILY_VISITS * (1 + 0.5 * $progress) * self::WEEKDAY_FACTORS[$day->dayOfWeekIso] * $this->random(0.85, 1.15);

        $campaignActive = $offset < 21;
        $viralDay = $offset === 40;

        if ($campaignActive) {
            $volume *= 1.25;
        }
        if ($viralDay) {
            $volume *= 2.5;
        }

        $hourWeights = self::HOUR_WEIGHTS;
        if ($day->isSunday() && $day->month === 3 && $day->day > 24) {
            $hourWeights[1] = 0;
            $hourWeights[2] = 0;
        }
        if ($offset === 0) {
            $hourWeights = array_map(
                fn (float $w, int $h) => $h < $this->now->hour ? $w : ($h === $this->now->hour ? $w * $this->now->minute / 60 : 0),
                $hourWeights,
                array_keys($hourWeights),
            );
            $volume *= array_sum($hourWeights) / array_sum(self::HOUR_WEIGHTS);
        }

        $count = (int) round($volume);
        for ($i = 0; $i < $count; $i++) {
            $hour = $this->weighted($hourWeights);
            $start = $day->setTime($hour, mt_rand(0, 59), mt_rand(0, 59));
            if ($start->gt($this->now)) {
                continue;
            }

            if (mt_rand(1, 100) <= 2) {
                $this->seedBotVisit($start);
            } else {
                $this->seedVisit($start, $this->source($campaignActive, $viralDay, $start));
            }
        }

        $this->flush();
    }

    private function source(bool $campaignActive, bool $viralDay, CarbonImmutable $at): array
    {
        $weights = ['direct' => 28, 'organic' => 30, 'social' => 12, 'referral' => 10, 'email' => 7, 'paid_search' => 8, 'paid_social' => 5];
        if ($campaignActive) {
            $weights['paid_search'] = 16;
            $weights['paid_social'] = 12;
            $weights['email'] = 10;
        }
        if ($viralDay) {
            $weights['social'] = 80;
        }

        $kind = $this->weighted($weights);
        $sale = $campaignActive ? 'herbst-sale' : null;

        return match ($kind) {
            'direct' => ['referer_domain' => null],
            'organic' => ['referer_domain' => $this->weighted(self::SEARCH_ENGINES)],
            'social' => ['referer_domain' => $viralDay ? 'www.reddit.com' : $this->weighted(self::SOCIAL_SITES)],
            'referral' => ['referer_domain' => $this->weighted(self::REFERRAL_SITES)],
            'email' => [
                'referer_domain' => null,
                'utm_source' => 'newsletter',
                'utm_medium' => 'email',
                'utm_campaign' => $sale ?? Str::lower($at->locale('de')->monthName).'-newsletter',
                'utm_content' => $this->pick(['header-button', 'produkt-teaser', 'footer-link']),
            ],
            'paid_search' => [
                'referer_domain' => 'www.google.com',
                'utm_source' => 'google',
                'utm_medium' => 'cpc',
                'utm_campaign' => $sale ?? $this->pick(['brand-search', 'generic-search']),
                'utm_term' => $this->pick(['kaffeemaschine kaufen', 'espresso bohnen', 'milchschäumer', 'siebträger schweiz']),
                'utm_content' => $this->pick(['ad-a', 'ad-b']),
            ],
            'paid_social' => [
                'referer_domain' => $this->pick(['l.facebook.com', 'www.instagram.com']),
                'utm_source' => $this->pick(['facebook', 'instagram']),
                'utm_medium' => 'paid_social',
                'utm_campaign' => $sale ?? 'retargeting',
                'utm_content' => $this->pick(['carousel', 'video', 'story']),
            ],
        };
    }

    private function seedVisit(CarbonImmutable $start, array $source): void
    {
        $code = $this->weighted(array_map(fn (array $c) => $c[2], self::COUNTRIES));
        [$country, $cities] = self::COUNTRIES[$code];
        $language = $this->weighted(self::LANGUAGES[$code] ?? ['en' => 100]);
        [$device, $os, $browser] = $this->platform();
        $visitor = $this->visitor();

        $isCampaign = isset($source['utm_source']);
        $entry = $isCampaign && ($source['utm_campaign'] ?? null) === 'herbst-sale'
            ? $this->pick(['/aktion/herbst-sale', '/aktion/herbst-sale', '/produkte'])
            : $this->weighted(self::ENTRY_PAGES);

        $bounceChance = match (true) {
            ($source['utm_medium'] ?? null) === 'email' => 30,
            in_array($source['utm_medium'] ?? null, ['cpc', 'paid_social'], true) => 55,
            str_starts_with($entry, '/blog/') => 60,
            default => 40,
        };

        $paths = [$entry];
        if (mt_rand(1, 100) > $bounceChance) {
            $length = 2;
            while ($length < 12 && mt_rand(1, 100) <= 62) {
                $length++;
            }
            while (count($paths) < $length) {
                $paths[] = $this->nextPath(end($paths));
            }
        }

        $visitId = (string) Str::ulid();
        $at = $start;
        $times = [];
        foreach ($paths as $i => $path) {
            if ($i > 0) {
                $at = $at->addSeconds(mt_rand(15, 240));
            }
            if ($at->gt($this->now)) {
                break;
            }
            $times[] = $at;
        }
        $paths = array_slice($paths, 0, count($times));

        $engaged = [];
        foreach ($paths as $i => $path) {
            $engaged[$i] = isset($times[$i + 1])
                ? (int) round($times[$i]->diffInSeconds($times[$i + 1]) * $this->random(0.6, 0.95))
                : match (true) {
                    count($paths) === 1 && mt_rand(1, 100) <= 60 => mt_rand(0, 9),
                    str_starts_with($path, '/blog/') => mt_rand(40, 420),
                    default => mt_rand(5, 180),
                };
        }
        $lastActivity = end($times)->addSeconds(end($engaged));
        if ($lastActivity->gt($this->now)) {
            $lastActivity = $this->now;
        }

        $this->visits[] = [
            'id' => $visitId,
            'site_id' => $this->site->id,
            'project_id' => $this->site->project_id,
            'visitor_hash' => $visitor,
            'started_at' => $start,
            'last_activity_at' => $lastActivity,
            'pageview_count' => count($paths),
            'entry_path' => $paths[0],
            'exit_path' => end($paths),
            'country_code' => $code,
            'browser' => $browser,
            'os' => $os,
            'device' => $device,
            'referer_domain' => $source['referer_domain'],
            'utm_source' => $source['utm_source'] ?? null,
            'utm_medium' => $source['utm_medium'] ?? null,
            'utm_campaign' => $source['utm_campaign'] ?? null,
            'utm_term' => $source['utm_term'] ?? null,
            'utm_content' => $source['utm_content'] ?? null,
            'is_bot' => false,
            'created_at' => $start,
            'updated_at' => $lastActivity,
        ];

        $city = $this->pick($cities);
        $host = $this->weighted(self::HOSTS);
        foreach ($paths as $i => $path) {
            $refererDomain = $i === 0 ? $source['referer_domain'] : 'www.'.self::DOMAIN;
            $this->pageViews[] = [
                'id' => (string) Str::ulid(),
                'visit_id' => $visitId,
                'site_id' => $this->site->id,
                'project_id' => $this->site->project_id,
                'visitor_hash' => $visitor,
                'hostname' => $host,
                'path' => $path,
                'title' => self::TITLES[$path] ?? null,
                'referer' => $refererDomain ? 'https://'.$refererDomain.($i === 0 ? '/' : $paths[$i - 1]) : null,
                'referer_domain' => $refererDomain,
                'utm_source' => $i === 0 ? ($source['utm_source'] ?? null) : null,
                'utm_medium' => $i === 0 ? ($source['utm_medium'] ?? null) : null,
                'utm_campaign' => $i === 0 ? ($source['utm_campaign'] ?? null) : null,
                'utm_term' => $i === 0 ? ($source['utm_term'] ?? null) : null,
                'utm_content' => $i === 0 ? ($source['utm_content'] ?? null) : null,
                'country' => $country,
                'country_code' => $code,
                'region' => self::REGIONS[$city] ?? null,
                'city' => $city,
                'browser' => $browser,
                'os' => $os,
                'device' => $device,
                'language' => $language,
                'engaged_seconds' => $engaged[$i],
                'scroll_depth' => str_starts_with($path, '/blog/') ? mt_rand(35, 100) : mt_rand(15, 100),
                'is_bot' => false,
                'created_at' => $times[$i],
            ];

            $this->eventsFor($path, $visitId, $visitor, $times[$i]->addSeconds(mt_rand(3, 30)));
        }
    }

    private function seedBotVisit(CarbonImmutable $start): void
    {
        $visitId = (string) Str::ulid();
        $visitor = hash('sha256', 'bot-'.mt_rand());
        $path = $this->weighted(self::PAGES);

        $this->visits[] = [
            'id' => $visitId, 'site_id' => $this->site->id, 'project_id' => $this->site->project_id,
            'visitor_hash' => $visitor, 'started_at' => $start, 'last_activity_at' => $start, 'pageview_count' => 1,
            'entry_path' => $path, 'exit_path' => $path, 'country_code' => 'US', 'browser' => 'Other', 'os' => 'Linux',
            'device' => 'Desktop', 'referer_domain' => null, 'utm_source' => null, 'utm_medium' => null,
            'utm_campaign' => null, 'utm_term' => null, 'utm_content' => null, 'is_bot' => true,
            'created_at' => $start, 'updated_at' => $start,
        ];

        $this->pageViews[] = [
            'id' => (string) Str::ulid(), 'visit_id' => $visitId, 'site_id' => $this->site->id,
            'project_id' => $this->site->project_id, 'visitor_hash' => $visitor, 'hostname' => 'www.'.self::DOMAIN, 'path' => $path,
            'title' => self::TITLES[$path] ?? null, 'referer' => null,
            'referer_domain' => null, 'utm_source' => null, 'utm_medium' => null, 'utm_campaign' => null,
            'utm_term' => null, 'utm_content' => null, 'country' => 'United States', 'country_code' => 'US', 'region' => 'Virginia',
            'city' => 'Ashburn', 'browser' => 'Other', 'os' => 'Linux', 'device' => 'Desktop', 'language' => 'en',
            'engaged_seconds' => null, 'scroll_depth' => null, 'is_bot' => true, 'created_at' => $start,
        ];
    }

    private function seedLiveVisits(): void
    {
        foreach (range(1, 5) as $i) {
            $start = $this->now->subSeconds(mt_rand(30, 240));
            $this->seedVisit($start, $this->source(false, false, $start));
        }
    }

    private function eventsFor(string $path, string $visitId, string $visitor, CarbonImmutable $at): void
    {
        if ($at->gt($this->now)) {
            return;
        }

        $events = match (true) {
            $path === '/warenkorb' => [['add_to_cart', $this->productProps()]],
            $path === '/newsletter' && mt_rand(1, 100) <= 45 => [['newsletter_subscribe', ['source' => 'newsletter-page']]],
            in_array($path, self::NOT_FOUND_PAGES, true) => [['not_found', null]],
            $path === '/preise' && mt_rand(1, 100) <= 15 => [['file_download', ['url' => self::DOMAIN.'/downloads/preisliste-2026.pdf']]],
            str_starts_with($path, '/produkte/') && mt_rand(1, 100) <= 6 => [['file_download', ['url' => self::DOMAIN.'/downloads/'.$this->pick(['bedienungsanleitung.pdf', 'datenblatt.pdf', 'garantie.pdf'])]]],
            $path === '/kontakt' && mt_rand(1, 100) <= 30 => [['contact_form', ['topic' => $this->pick(['Beratung', 'Reparatur', 'Bestellung'])]]],
            str_starts_with($path, '/produkte') && mt_rand(1, 100) <= 9 => [['site_search', ['term' => $this->pick(self::SEARCH_TERMS)]]],
            str_starts_with($path, '/produkte/') && mt_rand(1, 100) <= 12 => [['add_to_cart', $this->productProps()]],
            (str_starts_with($path, '/blog/') || $path === '/ueber-uns') && mt_rand(1, 100) <= 6 => [['outbound_click', ['url' => $this->pick(self::OUTBOUND_URLS)]]],
            default => [],
        };

        if ($path === '/warenkorb' && mt_rand(1, 100) <= 38) {
            $events[] = ['purchase', ['value' => mt_rand(39, 950), 'currency' => 'CHF']];
        }

        foreach ($events as [$name, $props]) {
            $this->events[] = [
                'id' => (string) Str::ulid(),
                'visit_id' => $visitId,
                'site_id' => $this->site->id,
                'project_id' => $this->site->project_id,
                'visitor_hash' => $visitor,
                'name' => $name,
                'props' => $props === null ? null : json_encode($props),
                'path' => $path,
                'is_bot' => false,
                'created_at' => $at,
            ];
        }
    }

    private function productProps(): array
    {
        [$product, $price] = $this->pick(self::PRODUCTS);

        return ['product' => $product, 'price' => $price];
    }

    private function nextPath(string $current): string
    {
        return match (true) {
            $current === '/warenkorb' && mt_rand(1, 100) <= 45 => '/kontakt',
            str_starts_with($current, '/produkte/') && mt_rand(1, 100) <= 30 => '/warenkorb',
            $current === '/aktion/herbst-sale' && mt_rand(1, 100) <= 60 => $this->pick(array_keys(array_filter(self::PAGES, fn ($w, $p) => str_starts_with($p, '/produkte/'), ARRAY_FILTER_USE_BOTH))),
            $current === '/blog' => $this->pick(['/blog/perfekter-espresso', '/blog/latte-art-guide', '/blog/entkalken']),
            default => $this->weighted(self::PAGES),
        };
    }

    private function visitor(): string
    {
        if ($this->visitorPool !== [] && mt_rand(1, 100) <= 28) {
            return $this->visitorPool[array_rand($this->visitorPool)];
        }

        $hash = hash('sha256', 'demo-visitor-'.count($this->visitorPool).'-'.mt_rand());
        $this->visitorPool[] = $hash;
        if (count($this->visitorPool) > 6000) {
            array_shift($this->visitorPool);
        }

        return $hash;
    }

    private function platform(): array
    {
        $index = $this->weighted(array_map(fn (array $p) => $p[3], self::PLATFORMS));

        return array_slice(self::PLATFORMS[$index], 0, 3);
    }

    private function flush(bool $force = false): void
    {
        if (! $force && count($this->pageViews) < 4000) {
            return;
        }

        foreach (array_chunk($this->visits, 500) as $chunk) {
            DB::table('visits')->insert($chunk);
        }
        foreach (array_chunk($this->pageViews, 500) as $chunk) {
            DB::table('page_views')->insert($chunk);
        }
        foreach (array_chunk($this->events, 500) as $chunk) {
            DB::table('events')->insert($chunk);
        }

        $this->visits = [];
        $this->pageViews = [];
        $this->events = [];
    }

    private function weighted(array $weights): string|int
    {
        $total = array_sum($weights);
        $roll = $this->random(0, $total);
        foreach ($weights as $key => $weight) {
            $roll -= $weight;
            if ($roll <= 0 && $weight > 0) {
                return $key;
            }
        }

        return array_key_last(array_filter($weights, fn ($w) => $w > 0));
    }

    private function pick(array $items): mixed
    {
        return $items[array_rand($items)];
    }

    private function random(float $min, float $max): float
    {
        return $min + mt_rand() / mt_getrandmax() * ($max - $min);
    }
}
