# SEO Crawler & Analyse-Tool Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ein eigener SEO-Crawler pro Project (Full-Site + Single-Page), der Websites analysiert und Ergebnisse als Issue-Zusammenfassung plus durchsuchbare URL-Tabelle darstellt.

**Architecture:** `spatie/crawler` liefert den Crawl-Motor (robots.txt, Delay, Domain-Grenze, optionales JS-Rendering via Browsershot). Ein `CrawlObserver` lässt pro Seite eine Pipeline reiner Analyzer-Klassen laufen und persistiert `crawl_pages` + `crawl_links`. Ein nachgelagerter `AggregateCrawlJob` berechnet Inlinks, Orphans, Depth, Sitemap-Abgleich, Duplicate-Titel und die Issue-Summary. Alles läuft in Queue-Jobs.

**Tech Stack:** Laravel 13 / PHP 8.3, Inertia + React 19 / TypeScript, `spatie/crawler` (Guzzle + Symfony DomCrawler), Spatie Browsershot (vorhanden), MariaDB (DDEV), PHPUnit.

## Global Constraints

- Alle Artisan/Composer/NPM-Befehle über DDEV: `ddev php artisan …`, `ddev composer …`, `ddev npm …`.
- Frontend-Gate ist `ddev npm run build` (Lint im Projekt defekt — nicht `npm run lint`).
- Ziggy: `route()` immer mit Objekt-Param, z.B. `route('…', { project: id, crawl: id })` — nie Bare-Value.
- Multi-Tenancy: jede neue Tabelle hat `project_id`; Controller scopen ausschließlich über `$project = $request->get('project')` und `$project->crawls()…`.
- Neue Tenant-Routes im `/project/{project}`-Block, Middleware `auth` + `ProjectBindingMiddleware`, Route-Namen-Präfix `app.project.crawls.*`.
- Models nutzen ULIDs (`HasUlids`), `foreignUlid('project_id')->constrained()->cascadeOnDelete()`, `HasFactory`.
- Enums: string-backed mit `label()` und (falls für Selects gebraucht) `options()` — Muster wie `App\Enums\TrackingMode`.
- Tests: `RefreshDatabase`; User/Project via Factory, User an Project hängen mit `->users()->attach($user, ['role' => 'member', 'active' => true])`; Routen im Test über `route()` mit Objekt-Param aufrufen.
- Out of Scope (nicht bauen): aktives Broken-Link-Probing externer Ziele (Folge-Feature), Vergleichs-/Trend-UI zwischen Läufen, SPA-Auto-Erkennung.

---

## File Structure

**Backend**
- `app/Enums/CrawlMode.php` — `full_site` | `single_page`
- `app/Enums/CrawlStatus.php` — `queued` | `running` | `completed` | `failed`
- `app/Crawler/IssueCode.php` — Issue-Katalog (Enum) mit `severity()` + `label()`
- `app/Crawler/AnalyzerResult.php` — DTO: `array $data`, `IssueCode[] $issues`
- `app/Crawler/PageContext.php` — DTO: url, statusCode, baseHost, robotsBlocked
- `app/Crawler/Analyzers/Analyzer.php` — Interface
- `app/Crawler/Analyzers/{Meta,Heading,Indexability,Link,Image,StructuredData}Analyzer.php`
- `app/Crawler/PageAnalyzer.php` — orchestriert alle Analyzer, merged zu `AnalyzerResult`
- `app/Crawler/SitemapReader.php` — holt & parst sitemap.xml → URL-Liste
- `app/Observers/CrawlPageObserver.php` — spatie `CrawlObserver`, persistiert Pages/Links
- `app/Jobs/RunCrawlJob.php` — konfiguriert & startet den Crawler
- `app/Jobs/AggregateCrawlJob.php` — Nachbearbeitung/Summary
- `app/Models/{Crawl,CrawlPage,CrawlLink}.php`
- `app/Http/Controllers/CrawlController.php`
- `app/Http/Requests/StoreCrawlRequest.php`
- `app/Rules/SafeCrawlUrl.php` — SSRF-Schutz
- `app/Policies/CrawlPolicy.php`
- `database/migrations/2026_08_12_000001_create_crawls_table.php` (+ `_000002`, `_000003`)
- `database/factories/{Crawl,CrawlPage,CrawlLink}Factory.php`

**Frontend**
- `resources/js/Pages/Crawls/Index.tsx`
- `resources/js/Pages/Crawls/Create.tsx`
- `resources/js/Pages/Crawls/Show.tsx`
- `resources/js/Pages/Crawls/Page.tsx`
- `resources/js/Components/Sidebar.tsx` (Nav-Eintrag ergänzen)
- `resources/js/types/index.d.ts` (Crawl-Typen ergänzen)

**Tests**
- `tests/Unit/Crawler/*Test.php` (Analyzer, PageAnalyzer, SitemapReader, IssueCode)
- `tests/Feature/Crawler/{CrawlObserverTest,AggregateCrawlJobTest,CrawlControllerTest,SafeCrawlUrlTest}.php`

---

## Task 1: Dependency + Enums + Issue-Katalog

**Files:**
- Install: `spatie/crawler`
- Create: `app/Enums/CrawlMode.php`, `app/Enums/CrawlStatus.php`, `app/Crawler/IssueCode.php`
- Test: `tests/Unit/Crawler/IssueCodeTest.php`

**Interfaces:**
- Produces:
  - `CrawlMode::FullSite` (`'full_site'`), `CrawlMode::SinglePage` (`'single_page'`); `label(): string`, `options(): array`
  - `CrawlStatus::Queued|Running|Completed|Failed` (`'queued'|'running'|'completed'|'failed'`); `label(): string`
  - `IssueCode` (string enum) cases + `severity(): string` (`'error'|'warning'|'notice'`) + `label(): string`

- [ ] **Step 1: Install the crawler**

```bash
ddev composer require spatie/crawler
```

- [ ] **Step 2: Write the failing test for IssueCode severities**

```php
<?php
// tests/Unit/Crawler/IssueCodeTest.php
namespace Tests\Unit\Crawler;

use App\Crawler\IssueCode;
use PHPUnit\Framework\TestCase;

class IssueCodeTest extends TestCase
{
    public function test_every_issue_code_has_a_known_severity(): void
    {
        foreach (IssueCode::cases() as $code) {
            $this->assertContains($code->severity(), ['error', 'warning', 'notice'], $code->value);
        }
    }

    public function test_server_error_is_an_error_and_thin_content_is_a_notice(): void
    {
        $this->assertSame('error', IssueCode::ServerError->severity());
        $this->assertSame('notice', IssueCode::ThinContent->severity());
    }
}
```

- [ ] **Step 3: Run test to verify it fails**

Run: `ddev php artisan test --filter=IssueCodeTest`
Expected: FAIL (class `App\Crawler\IssueCode` not found).

- [ ] **Step 4: Implement the enums**

```php
<?php
// app/Enums/CrawlMode.php
namespace App\Enums;

enum CrawlMode: string
{
    case FullSite = 'full_site';
    case SinglePage = 'single_page';

    public function label(): string
    {
        return match ($this) {
            self::FullSite => __('crawler.mode_full_site'),
            self::SinglePage => __('crawler.mode_single_page'),
        };
    }

    /** @return array<int, array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(fn (self $c) => ['value' => $c->value, 'label' => $c->label()], self::cases());
    }
}
```

```php
<?php
// app/Enums/CrawlStatus.php
namespace App\Enums;

enum CrawlStatus: string
{
    case Queued = 'queued';
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Queued => __('crawler.status_queued'),
            self::Running => __('crawler.status_running'),
            self::Completed => __('crawler.status_completed'),
            self::Failed => __('crawler.status_failed'),
        };
    }
}
```

```php
<?php
// app/Crawler/IssueCode.php
namespace App\Crawler;

enum IssueCode: string
{
    // status / links
    case ClientError = 'client_error';        // 4xx
    case ServerError = 'server_error';         // 5xx
    case RedirectChain = 'redirect_chain';     // >1 hop
    case OrphanPage = 'orphan_page';           // 0 inlinks
    // on-page / meta
    case MissingTitle = 'missing_title';
    case DuplicateTitle = 'duplicate_title';
    case TitleTooLong = 'title_too_long';
    case MissingMetaDescription = 'missing_meta_description';
    case DuplicateMetaDescription = 'duplicate_meta_description';
    case ThinContent = 'thin_content';
    // headings
    case MissingH1 = 'missing_h1';
    case MultipleH1 = 'multiple_h1';
    case HeadingOrderSkip = 'heading_order_skip';
    // indexability
    case Noindex = 'noindex';
    case CanonicalMismatch = 'canonical_mismatch';
    case RobotsBlocked = 'robots_blocked';
    // technik
    case MissingAltText = 'missing_alt_text';
    case MissingStructuredData = 'missing_structured_data';
    // sitemap
    case NotInSitemap = 'not_in_sitemap';

    public function severity(): string
    {
        return match ($this) {
            self::ServerError, self::ClientError, self::MissingTitle, self::MissingH1 => 'error',
            self::RedirectChain, self::MultipleH1, self::HeadingOrderSkip, self::Noindex,
            self::CanonicalMismatch, self::RobotsBlocked, self::DuplicateTitle,
            self::DuplicateMetaDescription, self::OrphanPage, self::MissingMetaDescription => 'warning',
            self::TitleTooLong, self::ThinContent, self::MissingAltText,
            self::MissingStructuredData, self::NotInSitemap => 'notice',
        };
    }

    public function label(): string
    {
        return __('crawler.issue.'.$this->value);
    }
}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `ddev php artisan test --filter=IssueCodeTest`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add composer.json composer.lock app/Enums/CrawlMode.php app/Enums/CrawlStatus.php app/Crawler/IssueCode.php tests/Unit/Crawler/IssueCodeTest.php
git commit -m "feat(crawler): add spatie/crawler + crawl enums and issue catalog"
```

---

## Task 2: Migrations

**Files:**
- Create: `database/migrations/2026_08_12_000001_create_crawls_table.php`
- Create: `database/migrations/2026_08_12_000002_create_crawl_pages_table.php`
- Create: `database/migrations/2026_08_12_000003_create_crawl_links_table.php`

**Interfaces:**
- Produces: tables `crawls`, `crawl_pages`, `crawl_links` with the columns below.

- [ ] **Step 1: Write the crawls migration**

```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crawls', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('project_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('site_id')->nullable()->constrained()->nullOnDelete();
            $table->string('start_url');
            $table->string('mode')->default('full_site');
            $table->boolean('render_js')->default(false);
            $table->boolean('respect_robots')->default(true);
            $table->boolean('include_subdomains')->default(false);
            $table->unsignedInteger('delay_ms')->default(0);
            $table->unsignedInteger('max_pages')->nullable();
            $table->string('status')->default('queued')->index();
            $table->unsignedInteger('pages_crawled')->default(0);
            $table->text('error')->nullable();
            $table->json('summary')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crawls');
    }
};
```

- [ ] **Step 2: Write the crawl_pages migration**

```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crawl_pages', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('crawl_id')->constrained()->cascadeOnDelete();
            $table->text('url');
            $table->text('final_url')->nullable();
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->json('redirect_chain')->nullable();
            $table->string('content_type')->nullable();
            $table->unsignedInteger('response_time_ms')->nullable();
            $table->unsignedInteger('size_bytes')->nullable();
            $table->unsignedSmallInteger('depth')->nullable();
            $table->text('title')->nullable();
            $table->unsignedSmallInteger('title_length')->nullable();
            $table->text('meta_description')->nullable();
            $table->unsignedSmallInteger('meta_description_length')->nullable();
            $table->text('canonical')->nullable();
            $table->string('meta_robots')->nullable();
            $table->unsignedInteger('word_count')->nullable();
            $table->json('headings')->nullable();
            $table->boolean('is_indexable')->default(true);
            $table->string('indexability_reason')->nullable();
            $table->boolean('in_sitemap')->default(false);
            $table->unsignedInteger('inlinks_count')->default(0);
            $table->boolean('is_orphan')->default(false);
            $table->json('structured_data')->nullable();
            $table->json('images_missing_alt')->nullable();
            $table->json('issues')->nullable();
            $table->timestamps();
            $table->index(['crawl_id', 'status_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crawl_pages');
    }
};
```

- [ ] **Step 3: Write the crawl_links migration**

```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crawl_links', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('crawl_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('from_page_id')->constrained('crawl_pages')->cascadeOnDelete();
            $table->text('to_url');
            $table->string('type')->default('internal'); // internal | external
            $table->text('anchor')->nullable();
            $table->string('rel')->nullable();
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->timestamps();
            $table->index(['crawl_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crawl_links');
    }
};
```

- [ ] **Step 4: Run the migrations**

Run: `ddev php artisan migrate`
Expected: three tables created, no errors.

- [ ] **Step 5: Commit**

```bash
git add database/migrations/2026_08_12_00000*
git commit -m "feat(crawler): add crawls, crawl_pages, crawl_links migrations"
```

---

## Task 3: Models + Relations + Factories

**Files:**
- Create: `app/Models/Crawl.php`, `app/Models/CrawlPage.php`, `app/Models/CrawlLink.php`
- Create: `database/factories/CrawlFactory.php`, `CrawlPageFactory.php`, `CrawlLinkFactory.php`
- Modify: `app/Models/Project.php` (add `crawls()` relation)
- Test: `tests/Feature/Crawler/CrawlModelTest.php`

**Interfaces:**
- Consumes: `CrawlMode`, `CrawlStatus` from Task 1.
- Produces:
  - `Crawl`: casts `mode`→CrawlMode, `status`→CrawlStatus, bool flags, `summary`→array; relations `project()`, `site()`, `pages(): HasMany<CrawlPage>`, `links(): HasMany<CrawlLink>`.
  - `CrawlPage`: casts json columns to array, bool flags; relations `crawl()`, `outLinks(): HasMany<CrawlLink>` (fk `from_page_id`).
  - `CrawlLink`: relations `crawl()`, `fromPage()`.
  - `Project::crawls(): HasMany<Crawl>`.

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Feature/Crawler/CrawlModelTest.php
namespace Tests\Feature\Crawler;

use App\Enums\CrawlMode;
use App\Enums\CrawlStatus;
use App\Models\Crawl;
use App\Models\CrawlPage;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrawlModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_crawl_casts_and_relations(): void
    {
        $project = Project::factory()->create();
        $crawl = Crawl::factory()->for($project)->create([
            'mode' => CrawlMode::FullSite->value,
            'status' => CrawlStatus::Queued->value,
            'summary' => ['missing_title' => 2],
        ]);
        $page = CrawlPage::factory()->for($crawl)->create(['issues' => ['missing_title']]);

        $this->assertInstanceOf(CrawlMode::class, $crawl->mode);
        $this->assertInstanceOf(CrawlStatus::class, $crawl->status);
        $this->assertSame(['missing_title' => 2], $crawl->summary);
        $this->assertSame(['missing_title'], $page->issues);
        $this->assertTrue($crawl->pages->contains($page));
        $this->assertTrue($project->crawls->contains($crawl));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `ddev php artisan test --filter=CrawlModelTest`
Expected: FAIL (`Crawl` / factory missing).

- [ ] **Step 3: Implement the models**

```php
<?php
// app/Models/Crawl.php
namespace App\Models;

use App\Enums\CrawlMode;
use App\Enums\CrawlStatus;
use Database\Factories\CrawlFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Crawl extends Model
{
    /** @use HasFactory<CrawlFactory> */
    use HasFactory, HasUlids;

    protected $fillable = [
        'project_id', 'site_id', 'start_url', 'mode', 'render_js', 'respect_robots',
        'include_subdomains', 'delay_ms', 'max_pages', 'status', 'pages_crawled',
        'error', 'summary', 'started_at', 'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'mode' => CrawlMode::class,
            'status' => CrawlStatus::class,
            'render_js' => 'boolean',
            'respect_robots' => 'boolean',
            'include_subdomains' => 'boolean',
            'summary' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function pages(): HasMany
    {
        return $this->hasMany(CrawlPage::class);
    }

    public function links(): HasMany
    {
        return $this->hasMany(CrawlLink::class);
    }
}
```

```php
<?php
// app/Models/CrawlPage.php
namespace App\Models;

use Database\Factories\CrawlPageFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CrawlPage extends Model
{
    /** @use HasFactory<CrawlPageFactory> */
    use HasFactory, HasUlids;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'redirect_chain' => 'array',
            'headings' => 'array',
            'structured_data' => 'array',
            'images_missing_alt' => 'array',
            'issues' => 'array',
            'is_indexable' => 'boolean',
            'in_sitemap' => 'boolean',
            'is_orphan' => 'boolean',
        ];
    }

    public function crawl(): BelongsTo
    {
        return $this->belongsTo(Crawl::class);
    }

    public function outLinks(): HasMany
    {
        return $this->hasMany(CrawlLink::class, 'from_page_id');
    }
}
```

```php
<?php
// app/Models/CrawlLink.php
namespace App\Models;

use Database\Factories\CrawlLinkFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrawlLink extends Model
{
    /** @use HasFactory<CrawlLinkFactory> */
    use HasFactory, HasUlids;

    protected $guarded = ['id'];

    public function crawl(): BelongsTo
    {
        return $this->belongsTo(Crawl::class);
    }

    public function fromPage(): BelongsTo
    {
        return $this->belongsTo(CrawlPage::class, 'from_page_id');
    }
}
```

- [ ] **Step 4: Add the Project relation**

In `app/Models/Project.php`, next to `sites()`, add:

```php
    public function crawls(): HasMany
    {
        return $this->hasMany(Crawl::class);
    }
```

- [ ] **Step 5: Implement the factories**

```php
<?php
// database/factories/CrawlFactory.php
namespace Database\Factories;

use App\Models\Crawl;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

class CrawlFactory extends Factory
{
    protected $model = Crawl::class;

    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'start_url' => 'https://example.com',
            'mode' => 'full_site',
            'status' => 'queued',
            'delay_ms' => 0,
            'pages_crawled' => 0,
        ];
    }
}
```

```php
<?php
// database/factories/CrawlPageFactory.php
namespace Database\Factories;

use App\Models\Crawl;
use App\Models\CrawlPage;
use Illuminate\Database\Eloquent\Factories\Factory;

class CrawlPageFactory extends Factory
{
    protected $model = CrawlPage::class;

    public function definition(): array
    {
        return [
            'crawl_id' => Crawl::factory(),
            'url' => 'https://example.com/'.$this->faker->slug(),
            'status_code' => 200,
        ];
    }
}
```

```php
<?php
// database/factories/CrawlLinkFactory.php
namespace Database\Factories;

use App\Models\Crawl;
use App\Models\CrawlLink;
use App\Models\CrawlPage;
use Illuminate\Database\Eloquent\Factories\Factory;

class CrawlLinkFactory extends Factory
{
    protected $model = CrawlLink::class;

    public function definition(): array
    {
        return [
            'crawl_id' => Crawl::factory(),
            'from_page_id' => CrawlPage::factory(),
            'to_url' => 'https://example.com/'.$this->faker->slug(),
            'type' => 'internal',
        ];
    }
}
```

- [ ] **Step 6: Run test to verify it passes**

Run: `ddev php artisan test --filter=CrawlModelTest`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add app/Models/Crawl.php app/Models/CrawlPage.php app/Models/CrawlLink.php app/Models/Project.php database/factories/Crawl*Factory.php tests/Feature/Crawler/CrawlModelTest.php
git commit -m "feat(crawler): add Crawl/CrawlPage/CrawlLink models, relations, factories"
```

---

## Task 4: Analyzer contract + DTOs

**Files:**
- Create: `app/Crawler/AnalyzerResult.php`, `app/Crawler/PageContext.php`, `app/Crawler/Analyzers/Analyzer.php`
- Test: none (pure contracts; exercised by Tasks 5–10).

**Interfaces:**
- Produces:
  - `PageContext { public function __construct(public string $url, public int $statusCode, public string $baseHost, public bool $robotsBlocked = false) }`
  - `AnalyzerResult { public array $data = []; /** @var IssueCode[] */ public array $issues = []; public function add(string $key, mixed $value): void; public function issue(IssueCode $code): void; }`
  - `interface Analyzer { public function analyze(\Symfony\Component\DomCrawler\Crawler $dom, PageContext $ctx): AnalyzerResult; }`

- [ ] **Step 1: Implement PageContext**

```php
<?php
// app/Crawler/PageContext.php
namespace App\Crawler;

class PageContext
{
    public function __construct(
        public string $url,
        public int $statusCode,
        public string $baseHost,
        public bool $robotsBlocked = false,
    ) {}
}
```

- [ ] **Step 2: Implement AnalyzerResult**

```php
<?php
// app/Crawler/AnalyzerResult.php
namespace App\Crawler;

class AnalyzerResult
{
    /** @var array<string, mixed> */
    public array $data = [];

    /** @var IssueCode[] */
    public array $issues = [];

    public function add(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
    }

    public function issue(IssueCode $code): void
    {
        $this->issues[] = $code;
    }
}
```

- [ ] **Step 3: Implement the Analyzer interface**

```php
<?php
// app/Crawler/Analyzers/Analyzer.php
namespace App\Crawler\Analyzers;

use App\Crawler\AnalyzerResult;
use App\Crawler\PageContext;
use Symfony\Component\DomCrawler\Crawler;

interface Analyzer
{
    public function analyze(Crawler $dom, PageContext $ctx): AnalyzerResult;
}
```

- [ ] **Step 4: Commit**

```bash
git add app/Crawler/AnalyzerResult.php app/Crawler/PageContext.php app/Crawler/Analyzers/Analyzer.php
git commit -m "feat(crawler): add analyzer contract and result/context DTOs"
```

---

## Task 5: MetaAnalyzer

**Files:**
- Create: `app/Crawler/Analyzers/MetaAnalyzer.php`
- Test: `tests/Unit/Crawler/MetaAnalyzerTest.php`

**Interfaces:**
- Consumes: `Analyzer`, `AnalyzerResult`, `PageContext`, `IssueCode`.
- Produces `data`: `title`, `title_length`, `meta_description`, `meta_description_length`, `canonical`, `meta_robots`, `word_count`. Issues: `MissingTitle`, `TitleTooLong` (>60), `MissingMetaDescription`, `ThinContent` (word_count < 100).

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Unit/Crawler/MetaAnalyzerTest.php
namespace Tests\Unit\Crawler;

use App\Crawler\Analyzers\MetaAnalyzer;
use App\Crawler\IssueCode;
use App\Crawler\PageContext;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DomCrawler\Crawler;

class MetaAnalyzerTest extends TestCase
{
    private function analyze(string $html): \App\Crawler\AnalyzerResult
    {
        return (new MetaAnalyzer)->analyze(new Crawler($html), new PageContext('https://x.test/', 200, 'x.test'));
    }

    public function test_extracts_title_description_canonical(): void
    {
        $r = $this->analyze('<html><head><title>Hello World</title>'
            .'<meta name="description" content="A page">'
            .'<link rel="canonical" href="https://x.test/">'
            .'<meta name="robots" content="index,follow"></head>'
            .'<body>'.str_repeat('word ', 150).'</body></html>');

        $this->assertSame('Hello World', $r->data['title']);
        $this->assertSame(11, $r->data['title_length']);
        $this->assertSame('A page', $r->data['meta_description']);
        $this->assertSame('https://x.test/', $r->data['canonical']);
        $this->assertSame('index,follow', $r->data['meta_robots']);
        $this->assertGreaterThanOrEqual(150, $r->data['word_count']);
        $this->assertSame([], $r->issues);
    }

    public function test_flags_missing_title_and_description_and_thin_content(): void
    {
        $r = $this->analyze('<html><head></head><body>short</body></html>');

        $this->assertContains(IssueCode::MissingTitle, $r->issues);
        $this->assertContains(IssueCode::MissingMetaDescription, $r->issues);
        $this->assertContains(IssueCode::ThinContent, $r->issues);
    }

    public function test_flags_overlong_title(): void
    {
        $r = $this->analyze('<html><head><title>'.str_repeat('a', 61).'</title></head><body>'.str_repeat('w ', 150).'</body></html>');

        $this->assertContains(IssueCode::TitleTooLong, $r->issues);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `ddev php artisan test --filter=MetaAnalyzerTest`
Expected: FAIL (class missing).

- [ ] **Step 3: Implement MetaAnalyzer**

```php
<?php
// app/Crawler/Analyzers/MetaAnalyzer.php
namespace App\Crawler\Analyzers;

use App\Crawler\AnalyzerResult;
use App\Crawler\IssueCode;
use App\Crawler\PageContext;
use Symfony\Component\DomCrawler\Crawler;

class MetaAnalyzer implements Analyzer
{
    public function analyze(Crawler $dom, PageContext $ctx): AnalyzerResult
    {
        $r = new AnalyzerResult;

        $title = $this->first($dom, 'head title');
        $r->add('title', $title);
        $r->add('title_length', $title !== null ? mb_strlen($title) : 0);
        if ($title === null || trim($title) === '') {
            $r->issue(IssueCode::MissingTitle);
        } elseif (mb_strlen($title) > 60) {
            $r->issue(IssueCode::TitleTooLong);
        }

        $desc = $this->attr($dom, 'head meta[name="description"]', 'content');
        $r->add('meta_description', $desc);
        $r->add('meta_description_length', $desc !== null ? mb_strlen($desc) : 0);
        if ($desc === null || trim($desc) === '') {
            $r->issue(IssueCode::MissingMetaDescription);
        }

        $r->add('canonical', $this->attr($dom, 'head link[rel="canonical"]', 'href'));
        $r->add('meta_robots', $this->attr($dom, 'head meta[name="robots"]', 'content'));

        $words = str_word_count(strip_tags($this->bodyHtml($dom)));
        $r->add('word_count', $words);
        if ($words < 100) {
            $r->issue(IssueCode::ThinContent);
        }

        return $r;
    }

    private function first(Crawler $dom, string $selector): ?string
    {
        $node = $dom->filter($selector);

        return $node->count() ? trim($node->first()->text('')) : null;
    }

    private function attr(Crawler $dom, string $selector, string $attr): ?string
    {
        $node = $dom->filter($selector);

        return $node->count() ? $node->first()->attr($attr) : null;
    }

    private function bodyHtml(Crawler $dom): string
    {
        $body = $dom->filter('body');

        return $body->count() ? $body->first()->html('') : '';
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `ddev php artisan test --filter=MetaAnalyzerTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Crawler/Analyzers/MetaAnalyzer.php tests/Unit/Crawler/MetaAnalyzerTest.php
git commit -m "feat(crawler): add MetaAnalyzer"
```

---

## Task 6: HeadingAnalyzer

**Files:**
- Create: `app/Crawler/Analyzers/HeadingAnalyzer.php`
- Test: `tests/Unit/Crawler/HeadingAnalyzerTest.php`

**Interfaces:**
- Produces `data`: `headings` (ordered list `[{level:int, text:string}]`). Issues: `MissingH1` (0 h1), `MultipleH1` (>1 h1), `HeadingOrderSkip` (a heading whose level jumps more than +1 vs. the previous heading, e.g. H1→H3).

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Unit/Crawler/HeadingAnalyzerTest.php
namespace Tests\Unit\Crawler;

use App\Crawler\Analyzers\HeadingAnalyzer;
use App\Crawler\IssueCode;
use App\Crawler\PageContext;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DomCrawler\Crawler;

class HeadingAnalyzerTest extends TestCase
{
    private function analyze(string $body): \App\Crawler\AnalyzerResult
    {
        $html = '<html><body>'.$body.'</body></html>';

        return (new HeadingAnalyzer)->analyze(new Crawler($html), new PageContext('https://x.test/', 200, 'x.test'));
    }

    public function test_collects_ordered_headings(): void
    {
        $r = $this->analyze('<h1>A</h1><h2>B</h2>');

        $this->assertSame([
            ['level' => 1, 'text' => 'A'],
            ['level' => 2, 'text' => 'B'],
        ], $r->data['headings']);
        $this->assertSame([], $r->issues);
    }

    public function test_flags_missing_h1(): void
    {
        $r = $this->analyze('<h2>B</h2>');
        $this->assertContains(IssueCode::MissingH1, $r->issues);
    }

    public function test_flags_multiple_h1(): void
    {
        $r = $this->analyze('<h1>A</h1><h1>B</h1>');
        $this->assertContains(IssueCode::MultipleH1, $r->issues);
    }

    public function test_flags_heading_order_skip(): void
    {
        $r = $this->analyze('<h1>A</h1><h3>C</h3>');
        $this->assertContains(IssueCode::HeadingOrderSkip, $r->issues);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `ddev php artisan test --filter=HeadingAnalyzerTest`
Expected: FAIL.

- [ ] **Step 3: Implement HeadingAnalyzer**

```php
<?php
// app/Crawler/Analyzers/HeadingAnalyzer.php
namespace App\Crawler\Analyzers;

use App\Crawler\AnalyzerResult;
use App\Crawler\IssueCode;
use App\Crawler\PageContext;
use Symfony\Component\DomCrawler\Crawler;

class HeadingAnalyzer implements Analyzer
{
    public function analyze(Crawler $dom, PageContext $ctx): AnalyzerResult
    {
        $r = new AnalyzerResult;

        $headings = [];
        $dom->filter('h1,h2,h3,h4,h5,h6')->each(function (Crawler $node) use (&$headings) {
            $headings[] = [
                'level' => (int) substr($node->nodeName(), 1),
                'text' => trim($node->text('')),
            ];
        });
        $r->add('headings', $headings);

        $h1Count = count(array_filter($headings, fn ($h) => $h['level'] === 1));
        if ($h1Count === 0) {
            $r->issue(IssueCode::MissingH1);
        } elseif ($h1Count > 1) {
            $r->issue(IssueCode::MultipleH1);
        }

        $prev = null;
        foreach ($headings as $h) {
            if ($prev !== null && $h['level'] > $prev + 1) {
                $r->issue(IssueCode::HeadingOrderSkip);
                break;
            }
            $prev = $h['level'];
        }

        return $r;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `ddev php artisan test --filter=HeadingAnalyzerTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Crawler/Analyzers/HeadingAnalyzer.php tests/Unit/Crawler/HeadingAnalyzerTest.php
git commit -m "feat(crawler): add HeadingAnalyzer (missing/multiple H1, order skips)"
```

---

## Task 7: IndexabilityAnalyzer

**Files:**
- Create: `app/Crawler/Analyzers/IndexabilityAnalyzer.php`
- Test: `tests/Unit/Crawler/IndexabilityAnalyzerTest.php`

**Interfaces:**
- Produces `data`: `is_indexable` (bool), `indexability_reason` (?string). Issues: `RobotsBlocked` (ctx->robotsBlocked), `Noindex` (meta robots contains `noindex`), `CanonicalMismatch` (canonical present and its path differs from ctx url path). Precedence for `indexability_reason`: robots_blocked > noindex > canonicalised. `is_indexable=false` when robots blocked or noindex.

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Unit/Crawler/IndexabilityAnalyzerTest.php
namespace Tests\Unit\Crawler;

use App\Crawler\Analyzers\IndexabilityAnalyzer;
use App\Crawler\IssueCode;
use App\Crawler\PageContext;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DomCrawler\Crawler;

class IndexabilityAnalyzerTest extends TestCase
{
    private function analyze(string $head, PageContext $ctx): \App\Crawler\AnalyzerResult
    {
        $html = '<html><head>'.$head.'</head><body>x</body></html>';

        return (new IndexabilityAnalyzer)->analyze(new Crawler($html), $ctx);
    }

    public function test_noindex_marks_not_indexable(): void
    {
        $r = $this->analyze('<meta name="robots" content="noindex,follow">', new PageContext('https://x.test/p', 200, 'x.test'));

        $this->assertFalse($r->data['is_indexable']);
        $this->assertSame('noindex', $r->data['indexability_reason']);
        $this->assertContains(IssueCode::Noindex, $r->issues);
    }

    public function test_robots_block_wins_over_everything(): void
    {
        $r = $this->analyze('<meta name="robots" content="noindex">', new PageContext('https://x.test/p', 200, 'x.test', robotsBlocked: true));

        $this->assertFalse($r->data['is_indexable']);
        $this->assertSame('robots_blocked', $r->data['indexability_reason']);
        $this->assertContains(IssueCode::RobotsBlocked, $r->issues);
    }

    public function test_canonical_mismatch_is_flagged_but_still_indexable(): void
    {
        $r = $this->analyze('<link rel="canonical" href="https://x.test/other">', new PageContext('https://x.test/p', 200, 'x.test'));

        $this->assertTrue($r->data['is_indexable']);
        $this->assertContains(IssueCode::CanonicalMismatch, $r->issues);
    }

    public function test_plain_page_is_indexable(): void
    {
        $r = $this->analyze('', new PageContext('https://x.test/p', 200, 'x.test'));

        $this->assertTrue($r->data['is_indexable']);
        $this->assertNull($r->data['indexability_reason']);
        $this->assertSame([], $r->issues);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `ddev php artisan test --filter=IndexabilityAnalyzerTest`
Expected: FAIL.

- [ ] **Step 3: Implement IndexabilityAnalyzer**

```php
<?php
// app/Crawler/Analyzers/IndexabilityAnalyzer.php
namespace App\Crawler\Analyzers;

use App\Crawler\AnalyzerResult;
use App\Crawler\IssueCode;
use App\Crawler\PageContext;
use Symfony\Component\DomCrawler\Crawler;

class IndexabilityAnalyzer implements Analyzer
{
    public function analyze(Crawler $dom, PageContext $ctx): AnalyzerResult
    {
        $r = new AnalyzerResult;
        $indexable = true;
        $reason = null;

        $robotsNode = $dom->filter('head meta[name="robots"]');
        $robots = $robotsNode->count() ? strtolower($robotsNode->first()->attr('content') ?? '') : '';
        $noindex = str_contains($robots, 'noindex');

        $canonicalNode = $dom->filter('head link[rel="canonical"]');
        $canonical = $canonicalNode->count() ? $canonicalNode->first()->attr('href') : null;

        if ($ctx->robotsBlocked) {
            $indexable = false;
            $reason = 'robots_blocked';
            $r->issue(IssueCode::RobotsBlocked);
        } elseif ($noindex) {
            $indexable = false;
            $reason = 'noindex';
            $r->issue(IssueCode::Noindex);
        }

        if ($canonical !== null && $this->differs($canonical, $ctx->url)) {
            if ($reason === null) {
                $reason = 'canonicalised';
            }
            $r->issue(IssueCode::CanonicalMismatch);
        }

        $r->add('is_indexable', $indexable);
        $r->add('indexability_reason', $reason);

        return $r;
    }

    private function differs(string $canonical, string $url): bool
    {
        $norm = fn (string $u) => rtrim(parse_url($u, PHP_URL_PATH) ?? '/', '/') ?: '/';

        return $norm($canonical) !== $norm($url);
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `ddev php artisan test --filter=IndexabilityAnalyzerTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Crawler/Analyzers/IndexabilityAnalyzer.php tests/Unit/Crawler/IndexabilityAnalyzerTest.php
git commit -m "feat(crawler): add IndexabilityAnalyzer"
```

---

## Task 8: LinkExtractor

**Files:**
- Create: `app/Crawler/Analyzers/LinkExtractor.php`
- Test: `tests/Unit/Crawler/LinkExtractorTest.php`

**Interfaces:**
- Produces `data['links']` = list of `['to_url' => absolute, 'type' => 'internal'|'external', 'anchor' => string, 'rel' => ?string]`. Internal = same host as `ctx->baseHost` (or subdomain thereof). Relative hrefs resolved against `ctx->url`. Skips `mailto:`, `tel:`, `javascript:`, fragment-only (`#`).

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Unit/Crawler/LinkExtractorTest.php
namespace Tests\Unit\Crawler;

use App\Crawler\Analyzers\LinkExtractor;
use App\Crawler\PageContext;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DomCrawler\Crawler;

class LinkExtractorTest extends TestCase
{
    public function test_classifies_and_resolves_links(): void
    {
        $html = '<html><body>'
            .'<a href="/about" rel="nofollow">About</a>'
            .'<a href="https://other.test/x">Ext</a>'
            .'<a href="mailto:a@b.test">Mail</a>'
            .'<a href="#top">Top</a>'
            .'</body></html>';

        $r = (new LinkExtractor)->analyze(new Crawler($html), new PageContext('https://x.test/page', 200, 'x.test'));
        $links = $r->data['links'];

        $this->assertCount(2, $links); // mailto + fragment skipped
        $this->assertSame('https://x.test/about', $links[0]['to_url']);
        $this->assertSame('internal', $links[0]['type']);
        $this->assertSame('nofollow', $links[0]['rel']);
        $this->assertSame('About', $links[0]['anchor']);
        $this->assertSame('external', $links[1]['type']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `ddev php artisan test --filter=LinkExtractorTest`
Expected: FAIL.

- [ ] **Step 3: Implement LinkExtractor**

```php
<?php
// app/Crawler/Analyzers/LinkExtractor.php
namespace App\Crawler\Analyzers;

use App\Crawler\AnalyzerResult;
use App\Crawler\PageContext;
use Symfony\Component\DomCrawler\Crawler;

class LinkExtractor implements Analyzer
{
    public function analyze(Crawler $dom, PageContext $ctx): AnalyzerResult
    {
        $r = new AnalyzerResult;
        $links = [];

        $dom->filter('a[href]')->each(function (Crawler $node) use (&$links, $ctx) {
            $href = trim($node->attr('href') ?? '');
            if ($href === '' || str_starts_with($href, '#')
                || preg_match('/^(mailto:|tel:|javascript:)/i', $href)) {
                return;
            }

            $abs = $this->resolve($href, $ctx->url);
            if ($abs === null) {
                return;
            }

            $host = parse_url($abs, PHP_URL_HOST) ?? '';
            $internal = $host === $ctx->baseHost || str_ends_with($host, '.'.$ctx->baseHost);

            $links[] = [
                'to_url' => $abs,
                'type' => $internal ? 'internal' : 'external',
                'anchor' => trim($node->text('')),
                'rel' => $node->attr('rel'),
            ];
        });

        $r->add('links', $links);

        return $r;
    }

    private function resolve(string $href, string $base): ?string
    {
        if (preg_match('#^https?://#i', $href)) {
            return $href;
        }
        $b = parse_url($base);
        if (! isset($b['scheme'], $b['host'])) {
            return null;
        }
        $origin = $b['scheme'].'://'.$b['host'].(isset($b['port']) ? ':'.$b['port'] : '');
        if (str_starts_with($href, '/')) {
            return $origin.$href;
        }
        $path = rtrim(dirname($b['path'] ?? '/'), '/');

        return $origin.$path.'/'.$href;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `ddev php artisan test --filter=LinkExtractorTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Crawler/Analyzers/LinkExtractor.php tests/Unit/Crawler/LinkExtractorTest.php
git commit -m "feat(crawler): add LinkExtractor"
```

---

## Task 9: ImageAnalyzer

**Files:**
- Create: `app/Crawler/Analyzers/ImageAnalyzer.php`
- Test: `tests/Unit/Crawler/ImageAnalyzerTest.php`

**Interfaces:**
- Produces `data['images_missing_alt']` = list of `src` strings for `<img>` lacking a non-empty `alt`. Issue: `MissingAltText` if the list is non-empty.

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Unit/Crawler/ImageAnalyzerTest.php
namespace Tests\Unit\Crawler;

use App\Crawler\Analyzers\ImageAnalyzer;
use App\Crawler\IssueCode;
use App\Crawler\PageContext;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DomCrawler\Crawler;

class ImageAnalyzerTest extends TestCase
{
    public function test_flags_images_without_alt(): void
    {
        $html = '<html><body><img src="/a.png" alt="ok"><img src="/b.png"><img src="/c.png" alt=""></body></html>';
        $r = (new ImageAnalyzer)->analyze(new Crawler($html), new PageContext('https://x.test/', 200, 'x.test'));

        $this->assertSame(['/b.png', '/c.png'], $r->data['images_missing_alt']);
        $this->assertContains(IssueCode::MissingAltText, $r->issues);
    }

    public function test_clean_page_has_no_issue(): void
    {
        $r = (new ImageAnalyzer)->analyze(new Crawler('<html><body><img src="/a.png" alt="ok"></body></html>'), new PageContext('https://x.test/', 200, 'x.test'));

        $this->assertSame([], $r->data['images_missing_alt']);
        $this->assertSame([], $r->issues);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `ddev php artisan test --filter=ImageAnalyzerTest`
Expected: FAIL.

- [ ] **Step 3: Implement ImageAnalyzer**

```php
<?php
// app/Crawler/Analyzers/ImageAnalyzer.php
namespace App\Crawler\Analyzers;

use App\Crawler\AnalyzerResult;
use App\Crawler\IssueCode;
use App\Crawler\PageContext;
use Symfony\Component\DomCrawler\Crawler;

class ImageAnalyzer implements Analyzer
{
    public function analyze(Crawler $dom, PageContext $ctx): AnalyzerResult
    {
        $r = new AnalyzerResult;
        $missing = [];

        $dom->filter('img')->each(function (Crawler $node) use (&$missing) {
            $alt = $node->attr('alt');
            if ($alt === null || trim($alt) === '') {
                $missing[] = $node->attr('src') ?? '';
            }
        });

        $r->add('images_missing_alt', $missing);
        if ($missing !== []) {
            $r->issue(IssueCode::MissingAltText);
        }

        return $r;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `ddev php artisan test --filter=ImageAnalyzerTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Crawler/Analyzers/ImageAnalyzer.php tests/Unit/Crawler/ImageAnalyzerTest.php
git commit -m "feat(crawler): add ImageAnalyzer"
```

---

## Task 10: StructuredDataAnalyzer

**Files:**
- Create: `app/Crawler/Analyzers/StructuredDataAnalyzer.php`
- Test: `tests/Unit/Crawler/StructuredDataAnalyzerTest.php`

**Interfaces:**
- Produces `data['structured_data']` = list of Schema.org `@type` strings found in `<script type="application/ld+json">` blocks (deduplicated, flattens `@graph`). Issue: `MissingStructuredData` when none found.

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Unit/Crawler/StructuredDataAnalyzerTest.php
namespace Tests\Unit\Crawler;

use App\Crawler\Analyzers\StructuredDataAnalyzer;
use App\Crawler\IssueCode;
use App\Crawler\PageContext;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DomCrawler\Crawler;

class StructuredDataAnalyzerTest extends TestCase
{
    public function test_extracts_types_from_jsonld(): void
    {
        $html = '<html><head>'
            .'<script type="application/ld+json">{"@type":"Organization","name":"X"}</script>'
            .'<script type="application/ld+json">{"@graph":[{"@type":"WebPage"},{"@type":"BreadcrumbList"}]}</script>'
            .'</head><body>x</body></html>';

        $r = (new StructuredDataAnalyzer)->analyze(new Crawler($html), new PageContext('https://x.test/', 200, 'x.test'));

        $this->assertEqualsCanonicalizing(['Organization', 'WebPage', 'BreadcrumbList'], $r->data['structured_data']);
        $this->assertSame([], $r->issues);
    }

    public function test_flags_missing_structured_data(): void
    {
        $r = (new StructuredDataAnalyzer)->analyze(new Crawler('<html><body>x</body></html>'), new PageContext('https://x.test/', 200, 'x.test'));

        $this->assertSame([], $r->data['structured_data']);
        $this->assertContains(IssueCode::MissingStructuredData, $r->issues);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `ddev php artisan test --filter=StructuredDataAnalyzerTest`
Expected: FAIL.

- [ ] **Step 3: Implement StructuredDataAnalyzer**

```php
<?php
// app/Crawler/Analyzers/StructuredDataAnalyzer.php
namespace App\Crawler\Analyzers;

use App\Crawler\AnalyzerResult;
use App\Crawler\IssueCode;
use App\Crawler\PageContext;
use Symfony\Component\DomCrawler\Crawler;

class StructuredDataAnalyzer implements Analyzer
{
    public function analyze(Crawler $dom, PageContext $ctx): AnalyzerResult
    {
        $r = new AnalyzerResult;
        $types = [];

        $dom->filter('script[type="application/ld+json"]')->each(function (Crawler $node) use (&$types) {
            $data = json_decode($node->text(''), true);
            if (! is_array($data)) {
                return;
            }
            foreach ($this->extractTypes($data) as $t) {
                $types[] = $t;
            }
        });

        $types = array_values(array_unique($types));
        $r->add('structured_data', $types);
        if ($types === []) {
            $r->issue(IssueCode::MissingStructuredData);
        }

        return $r;
    }

    /** @return string[] */
    private function extractTypes(array $data): array
    {
        $out = [];
        if (isset($data['@graph']) && is_array($data['@graph'])) {
            foreach ($data['@graph'] as $node) {
                if (is_array($node)) {
                    $out = array_merge($out, $this->extractTypes($node));
                }
            }
        }
        if (isset($data['@type'])) {
            foreach ((array) $data['@type'] as $t) {
                if (is_string($t)) {
                    $out[] = $t;
                }
            }
        }

        return $out;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `ddev php artisan test --filter=StructuredDataAnalyzerTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Crawler/Analyzers/StructuredDataAnalyzer.php tests/Unit/Crawler/StructuredDataAnalyzerTest.php
git commit -m "feat(crawler): add StructuredDataAnalyzer"
```

---

## Task 11: PageAnalyzer (pipeline orchestrator)

**Files:**
- Create: `app/Crawler/PageAnalyzer.php`
- Test: `tests/Unit/Crawler/PageAnalyzerTest.php`

**Interfaces:**
- Consumes: all six analyzers from Tasks 5–10.
- Produces: `PageAnalyzer::analyze(string $html, PageContext $ctx): AnalyzerResult` — runs every analyzer, merges their `data` (later keys win) and concatenates `issues` (deduplicated by value). `links` and `images_missing_alt` come through in `data`.

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Unit/Crawler/PageAnalyzerTest.php
namespace Tests\Unit\Crawler;

use App\Crawler\IssueCode;
use App\Crawler\PageAnalyzer;
use App\Crawler\PageContext;
use PHPUnit\Framework\TestCase;

class PageAnalyzerTest extends TestCase
{
    public function test_merges_data_and_issues_from_all_analyzers(): void
    {
        $html = '<html><head><title>T</title></head><body><h2>only h2</h2><img src="/a.png"></body></html>';
        $r = (new PageAnalyzer)->analyze($html, new PageContext('https://x.test/p', 200, 'x.test'));

        // meta data present
        $this->assertArrayHasKey('title', $r->data);
        // heading + image issues both bubbled up
        $this->assertContains(IssueCode::MissingH1, $r->issues);
        $this->assertContains(IssueCode::MissingAltText, $r->issues);
        // issues are deduplicated (no duplicate enum values)
        $values = array_map(fn ($i) => $i->value, $r->issues);
        $this->assertSame($values, array_values(array_unique($values)));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `ddev php artisan test --filter=PageAnalyzerTest`
Expected: FAIL.

- [ ] **Step 3: Implement PageAnalyzer**

```php
<?php
// app/Crawler/PageAnalyzer.php
namespace App\Crawler;

use App\Crawler\Analyzers\HeadingAnalyzer;
use App\Crawler\Analyzers\ImageAnalyzer;
use App\Crawler\Analyzers\IndexabilityAnalyzer;
use App\Crawler\Analyzers\LinkExtractor;
use App\Crawler\Analyzers\MetaAnalyzer;
use App\Crawler\Analyzers\StructuredDataAnalyzer;
use Symfony\Component\DomCrawler\Crawler;

class PageAnalyzer
{
    /** @return \App\Crawler\Analyzers\Analyzer[] */
    private function analyzers(): array
    {
        return [
            new MetaAnalyzer,
            new HeadingAnalyzer,
            new IndexabilityAnalyzer,
            new LinkExtractor,
            new ImageAnalyzer,
            new StructuredDataAnalyzer,
        ];
    }

    public function analyze(string $html, PageContext $ctx): AnalyzerResult
    {
        $dom = new Crawler($html);
        $merged = new AnalyzerResult;
        $seen = [];

        foreach ($this->analyzers() as $analyzer) {
            $result = $analyzer->analyze($dom, $ctx);
            $merged->data = array_merge($merged->data, $result->data);
            foreach ($result->issues as $issue) {
                if (! isset($seen[$issue->value])) {
                    $seen[$issue->value] = true;
                    $merged->issues[] = $issue;
                }
            }
        }

        return $merged;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `ddev php artisan test --filter=PageAnalyzerTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Crawler/PageAnalyzer.php tests/Unit/Crawler/PageAnalyzerTest.php
git commit -m "feat(crawler): add PageAnalyzer pipeline orchestrator"
```

---

## Task 12: CrawlPageObserver (persist pages + links)

**Files:**
- Create: `app/Observers/CrawlPageObserver.php`
- Test: `tests/Feature/Crawler/CrawlObserverTest.php`

**Interfaces:**
- Consumes: `Crawl`, `CrawlPage`, `CrawlLink`, `PageAnalyzer`, `PageContext`, `IssueCode`.
- Produces: `CrawlPageObserver extends \Spatie\Crawler\CrawlObservers\CrawlObserver`. Constructor `__construct(Crawl $crawl, PageAnalyzer $analyzer, string $baseHost)`. Public method `recordResponse(string $url, int $status, array $headers, string $body, float $responseMs): CrawlPage` — builds the `PageContext`, runs the analyzer, extracts response-level fields (status → `ClientError`/`ServerError`; redirect headers → `redirect_chain`, `final_url`, `RedirectChain` issue), persists the `CrawlPage` and its `crawl_links`, increments `crawl.pages_crawled`, and returns the page. `crawled()` delegates to `recordResponse()` (feature-tested directly via `recordResponse` to avoid live HTTP). `crawlFailed()` persists an error page (status null, issue `ServerError`). Redirect chain is read from Guzzle `X-Guzzle-Redirect-History` + `X-Guzzle-Redirect-Status-History` headers.

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Feature/Crawler/CrawlObserverTest.php
namespace Tests\Feature\Crawler;

use App\Crawler\PageAnalyzer;
use App\Models\Crawl;
use App\Observers\CrawlPageObserver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrawlObserverTest extends TestCase
{
    use RefreshDatabase;

    public function test_records_page_with_analysis_links_and_status_issue(): void
    {
        $crawl = Crawl::factory()->create(['start_url' => 'https://x.test']);
        $observer = new CrawlPageObserver($crawl, new PageAnalyzer, 'x.test');

        $html = '<html><head><title>Home</title></head><body>'
            .'<h1>Home</h1><a href="/about">About</a><a href="https://other.test">Ext</a>'
            .str_repeat('word ', 150).'</body></html>';

        $page = $observer->recordResponse('https://x.test/', 200, ['Content-Type' => ['text/html']], $html, 123.0);

        $this->assertSame(200, $page->status_code);
        $this->assertSame('Home', $page->title);
        $this->assertSame(['level' => 1, 'text' => 'Home'], $page->headings[0]);
        $this->assertSame(2, $page->outLinks()->count());
        $this->assertSame(1, $crawl->fresh()->pages_crawled);
    }

    public function test_4xx_gets_client_error_issue(): void
    {
        $crawl = Crawl::factory()->create();
        $observer = new CrawlPageObserver($crawl, new PageAnalyzer, 'x.test');

        $page = $observer->recordResponse('https://x.test/missing', 404, [], '', 10.0);

        $this->assertContains('client_error', $page->issues);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `ddev php artisan test --filter=CrawlObserverTest`
Expected: FAIL.

- [ ] **Step 3: Implement CrawlPageObserver**

```php
<?php
// app/Observers/CrawlPageObserver.php
namespace App\Observers;

use App\Crawler\IssueCode;
use App\Crawler\PageAnalyzer;
use App\Crawler\PageContext;
use App\Models\Crawl;
use App\Models\CrawlPage;
use GuzzleHttp\Exception\RequestException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;
use Spatie\Crawler\CrawlObservers\CrawlObserver;

class CrawlPageObserver extends CrawlObserver
{
    public function __construct(
        private Crawl $crawl,
        private PageAnalyzer $analyzer,
        private string $baseHost,
    ) {}

    public function crawled(UriInterface $url, ResponseInterface $response, ?UriInterface $foundOnUrl = null, ?string $linkText = null): void
    {
        $this->recordResponse(
            (string) $url,
            $response->getStatusCode(),
            $response->getHeaders(),
            (string) $response->getBody(),
            0.0,
        );
    }

    public function crawlFailed(UriInterface $url, RequestException $requestException, ?UriInterface $foundOnUrl = null, ?string $linkText = null): void
    {
        $status = $requestException->getResponse()?->getStatusCode();
        $page = $this->crawl->pages()->create([
            'url' => (string) $url,
            'status_code' => $status,
            'issues' => [$status && $status >= 400 && $status < 500 ? IssueCode::ClientError->value : IssueCode::ServerError->value],
        ]);
        $this->crawl->increment('pages_crawled');
        // no links to record for a failed fetch
        unset($page);
    }

    /** @param array<string, string[]> $headers */
    public function recordResponse(string $url, int $status, array $headers, string $body, float $responseMs): CrawlPage
    {
        $ctx = new PageContext($url, $status, $this->baseHost);
        $analysis = $this->analyzer->analyze($body, $ctx);

        $issues = array_map(fn (IssueCode $i) => $i->value, $analysis->issues);
        if ($status >= 500) {
            $issues[] = IssueCode::ServerError->value;
        } elseif ($status >= 400) {
            $issues[] = IssueCode::ClientError->value;
        }

        [$chain, $finalUrl] = $this->redirects($url, $headers);
        if (count($chain) > 1) {
            $issues[] = IssueCode::RedirectChain->value;
        }

        $links = $analysis->data['links'] ?? [];
        unset($analysis->data['links']);

        $page = $this->crawl->pages()->create(array_merge($analysis->data, [
            'url' => $url,
            'final_url' => $finalUrl,
            'status_code' => $status,
            'redirect_chain' => $chain ?: null,
            'content_type' => $headers['Content-Type'][0] ?? $headers['content-type'][0] ?? null,
            'response_time_ms' => (int) round($responseMs),
            'size_bytes' => strlen($body),
            'issues' => array_values(array_unique($issues)),
        ]));

        foreach ($links as $link) {
            $page->outLinks()->create([
                'crawl_id' => $this->crawl->id,
                'to_url' => $link['to_url'],
                'type' => $link['type'],
                'anchor' => $link['anchor'],
                'rel' => $link['rel'],
            ]);
        }

        $this->crawl->increment('pages_crawled');

        return $page;
    }

    /**
     * @param  array<string, string[]>  $headers
     * @return array{0: string[], 1: ?string}
     */
    private function redirects(string $url, array $headers): array
    {
        $history = $headers['X-Guzzle-Redirect-History'][0] ?? null;
        if ($history === null) {
            return [[], null];
        }
        $chain = array_merge([$url], array_map('trim', explode(',', $history)));
        $final = end($chain) ?: null;

        return [$chain, $final];
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `ddev php artisan test --filter=CrawlObserverTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Observers/CrawlPageObserver.php tests/Feature/Crawler/CrawlObserverTest.php
git commit -m "feat(crawler): add CrawlPageObserver persisting pages and links"
```

---

## Task 13: SitemapReader

**Files:**
- Create: `app/Crawler/SitemapReader.php`
- Test: `tests/Feature/Crawler/SitemapReaderTest.php`

**Interfaces:**
- Produces: `SitemapReader::urlsFor(string $startUrl): array<string>` — fetches `{origin}/sitemap.xml` via the Laravel HTTP client, parses `<loc>` entries (supports a sitemap index by following child sitemaps one level), returns absolute URL strings. Network/parse failures return `[]`. Uses `Illuminate\Support\Facades\Http` so tests fake it.

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Feature/Crawler/SitemapReaderTest.php
namespace Tests\Feature\Crawler;

use App\Crawler\SitemapReader;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SitemapReaderTest extends TestCase
{
    public function test_parses_loc_entries(): void
    {
        Http::fake([
            'x.test/sitemap.xml' => Http::response(
                '<?xml version="1.0"?><urlset><url><loc>https://x.test/</loc></url>'
                .'<url><loc>https://x.test/about</loc></url></urlset>', 200),
        ]);

        $urls = (new SitemapReader)->urlsFor('https://x.test/');

        $this->assertSame(['https://x.test/', 'https://x.test/about'], $urls);
    }

    public function test_missing_sitemap_returns_empty(): void
    {
        Http::fake(['*' => Http::response('', 404)]);

        $this->assertSame([], (new SitemapReader)->urlsFor('https://x.test/'));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `ddev php artisan test --filter=SitemapReaderTest`
Expected: FAIL.

- [ ] **Step 3: Implement SitemapReader**

```php
<?php
// app/Crawler/SitemapReader.php
namespace App\Crawler;

use Illuminate\Support\Facades\Http;

class SitemapReader
{
    /** @return string[] */
    public function urlsFor(string $startUrl): array
    {
        $parts = parse_url($startUrl);
        if (! isset($parts['scheme'], $parts['host'])) {
            return [];
        }
        $origin = $parts['scheme'].'://'.$parts['host'];

        return $this->fetch($origin.'/sitemap.xml', followIndex: true);
    }

    /** @return string[] */
    private function fetch(string $url, bool $followIndex): array
    {
        try {
            $response = Http::timeout(15)->get($url);
        } catch (\Throwable) {
            return [];
        }
        if (! $response->successful()) {
            return [];
        }

        $xml = @simplexml_load_string($response->body());
        if ($xml === false) {
            return [];
        }

        // sitemap index → follow children one level
        if (isset($xml->sitemap) && $followIndex) {
            $urls = [];
            foreach ($xml->sitemap as $child) {
                $urls = array_merge($urls, $this->fetch((string) $child->loc, followIndex: false));
            }

            return array_values(array_unique($urls));
        }

        $urls = [];
        foreach ($xml->url ?? [] as $entry) {
            $loc = trim((string) $entry->loc);
            if ($loc !== '') {
                $urls[] = $loc;
            }
        }

        return $urls;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `ddev php artisan test --filter=SitemapReaderTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Crawler/SitemapReader.php tests/Feature/Crawler/SitemapReaderTest.php
git commit -m "feat(crawler): add SitemapReader"
```

---

## Task 14: AggregateCrawlJob

**Files:**
- Create: `app/Jobs/AggregateCrawlJob.php`
- Test: `tests/Feature/Crawler/AggregateCrawlJobTest.php`

**Interfaces:**
- Consumes: `Crawl`, `CrawlPage`, `CrawlLink`, `SitemapReader`, `IssueCode`, `CrawlStatus`.
- Produces: `AggregateCrawlJob(Crawl $crawl)` implementing `ShouldQueue`. `handle(SitemapReader $sitemap)`:
  1. `inlinks_count` per page = count of `crawl_links` (internal) whose `to_url` matches the page `url` (normalize trailing slash).
  2. `is_orphan` + `OrphanPage` issue for indexable pages with 0 inlinks that are not the start URL.
  3. `depth` via BFS over internal links from the start URL.
  4. Sitemap: `in_sitemap` true when page url ∈ sitemap set; add `NotInSitemap` issue to indexable, non-start pages missing from a **non-empty** sitemap.
  5. Duplicate `title` / `meta_description` across pages → `DuplicateTitle` / `DuplicateMetaDescription`.
  6. Rebuild each page's `issues` (persist added issues), then aggregate all page issues into `crawl.summary` (`['code' => count]`), set `status = completed`, `finished_at = now()`.

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Feature/Crawler/AggregateCrawlJobTest.php
namespace Tests\Feature\Crawler;

use App\Crawler\SitemapReader;
use App\Jobs\AggregateCrawlJob;
use App\Models\Crawl;
use App\Models\CrawlLink;
use App\Models\CrawlPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AggregateCrawlJobTest extends TestCase
{
    use RefreshDatabase;

    private function fakeSitemap(array $urls): void
    {
        $this->app->instance(SitemapReader::class, new class($urls) extends SitemapReader
        {
            public function __construct(private array $urls) {}

            public function urlsFor(string $startUrl): array
            {
                return $this->urls;
            }
        });
    }

    public function test_computes_inlinks_orphans_and_summary(): void
    {
        $this->fakeSitemap(['https://x.test/']);
        $crawl = Crawl::factory()->create(['start_url' => 'https://x.test/']);

        $home = CrawlPage::factory()->for($crawl)->create(['url' => 'https://x.test/', 'title' => 'Home', 'is_indexable' => true, 'issues' => []]);
        $about = CrawlPage::factory()->for($crawl)->create(['url' => 'https://x.test/about', 'title' => 'Home', 'is_indexable' => true, 'issues' => []]);

        // home links to about → about has 1 inlink; nobody links to... about links nowhere
        CrawlLink::factory()->create(['crawl_id' => $crawl->id, 'from_page_id' => $home->id, 'to_url' => 'https://x.test/about', 'type' => 'internal']);

        (new AggregateCrawlJob($crawl))->handle(app(SitemapReader::class));

        $about->refresh();
        $home->refresh();
        $crawl->refresh();

        $this->assertSame(1, $about->inlinks_count);
        $this->assertTrue($about->in_sitemap === false);      // /about not in sitemap
        $this->assertFalse($home->is_orphan);                 // start url never orphan
        $this->assertContains('duplicate_title', $about->issues); // both titled "Home"
        $this->assertSame('completed', $crawl->status->value);
        $this->assertArrayHasKey('duplicate_title', $crawl->summary);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `ddev php artisan test --filter=AggregateCrawlJobTest`
Expected: FAIL.

- [ ] **Step 3: Implement AggregateCrawlJob**

```php
<?php
// app/Jobs/AggregateCrawlJob.php
namespace App\Jobs;

use App\Crawler\IssueCode;
use App\Crawler\SitemapReader;
use App\Enums\CrawlStatus;
use App\Models\Crawl;
use App\Models\CrawlPage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class AggregateCrawlJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public Crawl $crawl) {}

    public function handle(SitemapReader $sitemap): void
    {
        $pages = $this->crawl->pages()->with('outLinks')->get();
        $norm = fn (?string $u) => $u === null ? '' : rtrim($u, '/');
        $start = $norm($this->crawl->start_url);

        // 1. inlink counts (internal links pointing at each page url)
        $inlinks = [];
        foreach ($this->crawl->links()->where('type', 'internal')->get() as $link) {
            $key = $norm($link->to_url);
            $inlinks[$key] = ($inlinks[$key] ?? 0) + 1;
        }

        // 3. depth via BFS over internal links from start
        $depths = $this->computeDepths($pages, $norm, $start);

        // 4. sitemap set
        $sitemapUrls = array_map($norm, $sitemap->urlsFor($this->crawl->start_url));
        $hasSitemap = $sitemapUrls !== [];
        $sitemapSet = array_flip($sitemapUrls);

        // 5. duplicate title / description
        $dupTitles = $this->duplicates($pages, 'title');
        $dupDescriptions = $this->duplicates($pages, 'meta_description');

        $summary = [];
        foreach ($pages as $page) {
            $key = $norm($page->url);
            $issues = collect($page->issues ?? [])->flip();

            $count = $inlinks[$key] ?? 0;
            $page->inlinks_count = $count;

            $orphan = $page->is_indexable && $count === 0 && $key !== $start;
            $page->is_orphan = $orphan;
            if ($orphan) {
                $issues[IssueCode::OrphanPage->value] = true;
            }

            $page->depth = $depths[$key] ?? null;

            $inSitemap = isset($sitemapSet[$key]);
            $page->in_sitemap = $inSitemap;
            if ($hasSitemap && ! $inSitemap && $page->is_indexable && $key !== $start) {
                $issues[IssueCode::NotInSitemap->value] = true;
            }

            if ($page->title !== null && ($dupTitles[$page->title] ?? 0) > 1) {
                $issues[IssueCode::DuplicateTitle->value] = true;
            }
            if ($page->meta_description !== null && ($dupDescriptions[$page->meta_description] ?? 0) > 1) {
                $issues[IssueCode::DuplicateMetaDescription->value] = true;
            }

            $page->issues = array_keys($issues->all());
            $page->save();

            foreach ($page->issues as $code) {
                $summary[$code] = ($summary[$code] ?? 0) + 1;
            }
        }

        $this->crawl->update([
            'summary' => $summary,
            'status' => CrawlStatus::Completed,
            'finished_at' => now(),
        ]);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, CrawlPage>  $pages
     * @return array<string, int>
     */
    private function computeDepths($pages, callable $norm, string $start): array
    {
        $adj = [];
        foreach ($pages as $page) {
            $from = $norm($page->url);
            $adj[$from] = [];
            foreach ($page->outLinks as $link) {
                if ($link->type === 'internal') {
                    $adj[$from][] = $norm($link->to_url);
                }
            }
        }

        $depths = [$start => 0];
        $queue = [$start];
        while ($queue !== []) {
            $current = array_shift($queue);
            foreach ($adj[$current] ?? [] as $next) {
                if (! isset($depths[$next])) {
                    $depths[$next] = $depths[$current] + 1;
                    $queue[] = $next;
                }
            }
        }

        return $depths;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, CrawlPage>  $pages
     * @return array<string, int>
     */
    private function duplicates($pages, string $field): array
    {
        $counts = [];
        foreach ($pages as $page) {
            $value = $page->{$field};
            if ($value !== null && trim((string) $value) !== '') {
                $counts[$value] = ($counts[$value] ?? 0) + 1;
            }
        }

        return $counts;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `ddev php artisan test --filter=AggregateCrawlJobTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Jobs/AggregateCrawlJob.php tests/Feature/Crawler/AggregateCrawlJobTest.php
git commit -m "feat(crawler): add AggregateCrawlJob (inlinks, orphans, depth, sitemap, duplicates, summary)"
```

---

## Task 15: RunCrawlJob

**Files:**
- Create: `app/Jobs/RunCrawlJob.php`
- Test: `tests/Feature/Crawler/RunCrawlJobTest.php`

**Interfaces:**
- Consumes: `Crawl`, `CrawlStatus`, `CrawlMode`, `CrawlPageObserver`, `PageAnalyzer`, `AggregateCrawlJob`, `spatie/crawler`.
- Produces: `RunCrawlJob(Crawl $crawl)` implementing `ShouldQueue`, with `public int $timeout = 1800;`. `handle()`: sets status `running` + `started_at`; builds and configures the `Spatie\Crawler\Crawler`; runs it; on completion dispatches `AggregateCrawlJob`. `failed(\Throwable $e)`: sets status `failed` + `error`. A protected `buildCrawler(CrawlPageObserver $observer): Crawler` holds the config mapping so it is unit-testable without live HTTP.

- [ ] **Step 1: Write the failing test (config mapping only, no live HTTP)**

```php
<?php
// tests/Feature/Crawler/RunCrawlJobTest.php
namespace Tests\Feature\Crawler;

use App\Enums\CrawlStatus;
use App\Jobs\AggregateCrawlJob;
use App\Jobs\RunCrawlJob;
use App\Models\Crawl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class RunCrawlJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_failed_hook_marks_crawl_failed(): void
    {
        $crawl = Crawl::factory()->create(['status' => 'running']);

        (new RunCrawlJob($crawl))->failed(new \RuntimeException('boom'));

        $crawl->refresh();
        $this->assertSame(CrawlStatus::Failed->value, $crawl->status->value);
        $this->assertStringContainsString('boom', $crawl->error);
    }

    public function test_single_page_crawl_of_local_fixture_records_one_page_and_aggregates(): void
    {
        Bus::fake([AggregateCrawlJob::class]);

        // Serve a tiny fixture via a data: style file URL is not supported by Guzzle;
        // instead point at a route registered in the test app.
        $this->app['router']->get('/_fixture', fn () => '<html><head><title>Fx</title></head><body><h1>Fx</h1>'.str_repeat('w ', 150).'</body></html>');
        $url = url('/_fixture');

        $crawl = Crawl::factory()->create(['start_url' => $url, 'mode' => 'single_page', 'respect_robots' => false]);

        (new RunCrawlJob($crawl))->handle();

        $crawl->refresh();
        $this->assertGreaterThanOrEqual(1, $crawl->pages_crawled);
        Bus::assertDispatched(AggregateCrawlJob::class);
    }
}
```

> Note: the second test performs a real local HTTP request against the test app's own server. If the CI sandbox forbids outbound sockets to `localhost`, mark it with `$this->markTestSkipped()` guarded by `@group network`. Keep the first (pure) test as the always-on guarantee.

- [ ] **Step 2: Run test to verify it fails**

Run: `ddev php artisan test --filter=RunCrawlJobTest`
Expected: FAIL.

- [ ] **Step 3: Implement RunCrawlJob**

```php
<?php
// app/Jobs/RunCrawlJob.php
namespace App\Jobs;

use App\Crawler\PageAnalyzer;
use App\Enums\CrawlMode;
use App\Enums\CrawlStatus;
use App\Models\Crawl;
use App\Observers\CrawlPageObserver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Spatie\Crawler\Crawler;
use Spatie\Crawler\CrawlProfiles\CrawlInternalUrls;
use Spatie\Crawler\CrawlProfiles\CrawlSubdomains;

class RunCrawlJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800;

    public function __construct(public Crawl $crawl) {}

    public function handle(): void
    {
        $this->crawl->update(['status' => CrawlStatus::Running, 'started_at' => now()]);

        $host = parse_url($this->crawl->start_url, PHP_URL_HOST) ?? '';
        $observer = new CrawlPageObserver($this->crawl, new PageAnalyzer, $host);

        $this->buildCrawler($observer)->startCrawling($this->crawl->start_url);

        AggregateCrawlJob::dispatch($this->crawl);
    }

    protected function buildCrawler(CrawlPageObserver $observer): Crawler
    {
        $crawl = $this->crawl;

        $profile = $crawl->include_subdomains
            ? new CrawlSubdomains($crawl->start_url)
            : new CrawlInternalUrls($crawl->start_url);

        $crawler = Crawler::create([
            'allow_redirects' => ['track_redirects' => true],
            'timeout' => 30,
            'connect_timeout' => 15,
        ])
            ->setCrawlProfile($profile)
            ->setConcurrency(5)
            ->setDelayBetweenRequests($crawl->delay_ms)
            ->setCrawlObserver($observer);

        if ($crawl->mode === CrawlMode::SinglePage) {
            $crawler->setTotalCrawlLimit(1);
        } elseif ($crawl->max_pages !== null) {
            $crawler->setTotalCrawlLimit($crawl->max_pages);
        }

        if ($crawl->render_js) {
            $crawler->executeJavaScript();
        }

        if (! $crawl->respect_robots) {
            $crawler->ignoreRobots();
        }

        return $crawler;
    }

    public function failed(\Throwable $e): void
    {
        $this->crawl->update([
            'status' => CrawlStatus::Failed,
            'error' => $e->getMessage(),
            'finished_at' => now(),
        ]);
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `ddev php artisan test --filter=RunCrawlJobTest`
Expected: PASS (network test skipped or green depending on sandbox).

- [ ] **Step 5: Commit**

```bash
git add app/Jobs/RunCrawlJob.php tests/Feature/Crawler/RunCrawlJobTest.php
git commit -m "feat(crawler): add RunCrawlJob orchestrating spatie/crawler"
```

---

## Task 16: SafeCrawlUrl rule + CrawlPolicy

**Files:**
- Create: `app/Rules/SafeCrawlUrl.php`, `app/Policies/CrawlPolicy.php`
- Test: `tests/Feature/Crawler/SafeCrawlUrlTest.php`

**Interfaces:**
- Produces:
  - `SafeCrawlUrl implements ValidationRule` — rejects non-http(s) schemes, missing host, and hosts that resolve **only** to private/reserved IPs (SSRF). Message via `$fail()`.
  - `CrawlPolicy` with `view(User, Crawl)`, `delete(User, Crawl)` returning whether the crawl's project is accessible to the user (`$user->canAccessProject($crawl->project)`).

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Feature/Crawler/SafeCrawlUrlTest.php
namespace Tests\Feature\Crawler;

use App\Rules\SafeCrawlUrl;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class SafeCrawlUrlTest extends TestCase
{
    private function passes(string $url): bool
    {
        return Validator::make(['u' => $url], ['u' => [new SafeCrawlUrl]])->passes();
    }

    public function test_rejects_non_http_schemes_and_localhost(): void
    {
        $this->assertFalse($this->passes('ftp://example.com'));
        $this->assertFalse($this->passes('http://localhost'));
        $this->assertFalse($this->passes('http://127.0.0.1'));
        $this->assertFalse($this->passes('http://192.168.0.1'));
        $this->assertFalse($this->passes('not-a-url'));
    }

    public function test_allows_public_https_url(): void
    {
        $this->assertTrue($this->passes('https://example.com/path'));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `ddev php artisan test --filter=SafeCrawlUrlTest`
Expected: FAIL.

- [ ] **Step 3: Implement SafeCrawlUrl and CrawlPolicy**

```php
<?php
// app/Rules/SafeCrawlUrl.php
namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class SafeCrawlUrl implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! filter_var($value, FILTER_VALIDATE_URL)) {
            $fail(__('crawler.invalid_url'));

            return;
        }

        $scheme = strtolower((string) parse_url($value, PHP_URL_SCHEME));
        if (! in_array($scheme, ['http', 'https'], true)) {
            $fail(__('crawler.invalid_url'));

            return;
        }

        $host = parse_url($value, PHP_URL_HOST);
        if (! $host || strtolower($host) === 'localhost') {
            $fail(__('crawler.invalid_url'));

            return;
        }

        $ips = @gethostbynamel($host) ?: (filter_var($host, FILTER_VALIDATE_IP) ? [$host] : []);
        if ($ips === []) {
            $fail(__('crawler.unresolvable_url'));

            return;
        }

        foreach ($ips as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                $fail(__('crawler.private_url'));

                return;
            }
        }
    }
}
```

```php
<?php
// app/Policies/CrawlPolicy.php
namespace App\Policies;

use App\Models\Crawl;
use App\Models\User;

class CrawlPolicy
{
    public function view(User $user, Crawl $crawl): bool
    {
        return $crawl->project !== null && $user->canAccessProject($crawl->project);
    }

    public function delete(User $user, Crawl $crawl): bool
    {
        return $this->view($user, $crawl);
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `ddev php artisan test --filter=SafeCrawlUrlTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Rules/SafeCrawlUrl.php app/Policies/CrawlPolicy.php tests/Feature/Crawler/SafeCrawlUrlTest.php
git commit -m "feat(crawler): add SafeCrawlUrl SSRF rule and CrawlPolicy"
```

---

## Task 17: CrawlController + routes + FormRequest + CSV export

**Files:**
- Create: `app/Http/Controllers/CrawlController.php`, `app/Http/Requests/StoreCrawlRequest.php`
- Modify: `routes/web.php` (add controller import + crawl routes inside the `/project/{project}` group), `resources/lang` files (crawler keys)
- Test: `tests/Feature/Crawler/CrawlControllerTest.php`

**Interfaces:**
- Consumes: `Crawl`, `CrawlMode`, `RunCrawlJob`, `SafeCrawlUrl`, `CrawlPolicy`.
- Produces routes (all names `app.project.crawls.*`): `index`, `create`, `store`, `show`, `pages.show`, `destroy`, `export`.
- `store` validates via `StoreCrawlRequest` (`start_url` → `SafeCrawlUrl`; `mode` in enum; booleans; `delay_ms` int 0–10000; `max_pages` nullable int 1–100000), creates the crawl (`queued`) scoped to `$project`, dispatches `RunCrawlJob`, redirects to `show`.

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Feature/Crawler/CrawlControllerTest.php
namespace Tests\Feature\Crawler;

use App\Jobs\RunCrawlJob;
use App\Models\Crawl;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CrawlControllerTest extends TestCase
{
    use RefreshDatabase;

    private function member(): array
    {
        $user = User::factory()->create();
        $project = Project::factory()->create();
        $project->users()->attach($user, ['role' => 'member', 'active' => true]);

        return [$user, $project];
    }

    public function test_store_creates_crawl_and_dispatches_job(): void
    {
        Queue::fake();
        [$user, $project] = $this->member();

        $this->actingAs($user)
            ->post(route('app.project.crawls.store', ['project' => $project->id]), [
                'start_url' => 'https://example.com',
                'mode' => 'full_site',
                'render_js' => false,
                'respect_robots' => true,
                'include_subdomains' => false,
                'delay_ms' => 0,
            ])
            ->assertRedirect();

        $crawl = Crawl::first();
        $this->assertNotNull($crawl);
        $this->assertSame($project->id, $crawl->project_id);
        Queue::assertPushed(RunCrawlJob::class);
    }

    public function test_store_rejects_private_url(): void
    {
        Queue::fake();
        [$user, $project] = $this->member();

        $this->actingAs($user)
            ->post(route('app.project.crawls.store', ['project' => $project->id]), [
                'start_url' => 'http://127.0.0.1',
                'mode' => 'full_site',
                'delay_ms' => 0,
            ])
            ->assertSessionHasErrors('start_url');

        Queue::assertNotPushed(RunCrawlJob::class);
    }

    public function test_cannot_view_another_projects_crawl(): void
    {
        [$user, $project] = $this->member();
        $other = Project::factory()->create();
        $crawl = Crawl::factory()->for($other)->create();

        $this->actingAs($user)
            ->get(route('app.project.crawls.show', ['project' => $project->id, 'crawl' => $crawl->id]))
            ->assertNotFound();
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `ddev php artisan test --filter=CrawlControllerTest`
Expected: FAIL (routes/controller missing).

- [ ] **Step 3: Implement the FormRequest**

```php
<?php
// app/Http/Requests/StoreCrawlRequest.php
namespace App\Http\Requests;

use App\Enums\CrawlMode;
use App\Rules\SafeCrawlUrl;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCrawlRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // route is already behind auth + ProjectBindingMiddleware
    }

    public function rules(): array
    {
        return [
            'start_url' => ['required', 'string', 'max:2048', new SafeCrawlUrl],
            'mode' => ['required', Rule::enum(CrawlMode::class)],
            'render_js' => ['boolean'],
            'respect_robots' => ['boolean'],
            'include_subdomains' => ['boolean'],
            'delay_ms' => ['required', 'integer', 'min:0', 'max:10000'],
            'max_pages' => ['nullable', 'integer', 'min:1', 'max:100000'],
        ];
    }
}
```

- [ ] **Step 4: Implement the CrawlController**

```php
<?php
// app/Http/Controllers/CrawlController.php
namespace App\Http\Controllers;

use App\Enums\CrawlMode;
use App\Http\Requests\StoreCrawlRequest;
use App\Jobs\RunCrawlJob;
use App\Models\Crawl;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CrawlController extends Controller
{
    public function index(Request $request)
    {
        $project = $request->get('project');

        return inertia('Crawls/Index', [
            'crawls' => $project->crawls()->latest()->get()->map(fn (Crawl $c) => [
                'id' => $c->id,
                'start_url' => $c->start_url,
                'mode' => $c->mode->value,
                'status' => $c->status->value,
                'pages_crawled' => $c->pages_crawled,
                'created_at' => $c->created_at->toISOString(),
            ]),
        ]);
    }

    public function create(Request $request)
    {
        return inertia('Crawls/Create', [
            'modes' => CrawlMode::options(),
        ]);
    }

    public function store(StoreCrawlRequest $request)
    {
        $project = $request->get('project');

        $crawl = $project->crawls()->create($request->validated() + ['status' => 'queued']);
        RunCrawlJob::dispatch($crawl);

        return redirect()
            ->route('app.project.crawls.show', ['project' => $project->id, 'crawl' => $crawl->id])
            ->with('success', __('crawler.started'));
    }

    public function show(Request $request, string $crawl)
    {
        $project = $request->get('project');
        $model = $project->crawls()->findOrFail($crawl);

        $pages = $model->pages()
            ->orderBy('depth')
            ->paginate(50)
            ->through(fn ($p) => [
                'id' => $p->id,
                'url' => $p->url,
                'status_code' => $p->status_code,
                'title' => $p->title,
                'is_indexable' => $p->is_indexable,
                'inlinks_count' => $p->inlinks_count,
                'depth' => $p->depth,
                'issues' => $p->issues ?? [],
            ]);

        return inertia('Crawls/Show', [
            'crawl' => [
                'id' => $model->id,
                'start_url' => $model->start_url,
                'mode' => $model->mode->value,
                'status' => $model->status->value,
                'pages_crawled' => $model->pages_crawled,
                'summary' => $model->summary ?? [],
                'error' => $model->error,
            ],
            'pages' => $pages,
        ]);
    }

    public function pageDetail(Request $request, string $crawl, string $page)
    {
        $project = $request->get('project');
        $model = $project->crawls()->findOrFail($crawl);
        $pageModel = $model->pages()->with('outLinks')->findOrFail($page);

        return inertia('Crawls/Page', [
            'crawlId' => $model->id,
            'page' => [
                'url' => $pageModel->url,
                'final_url' => $pageModel->final_url,
                'status_code' => $pageModel->status_code,
                'redirect_chain' => $pageModel->redirect_chain,
                'title' => $pageModel->title,
                'meta_description' => $pageModel->meta_description,
                'canonical' => $pageModel->canonical,
                'meta_robots' => $pageModel->meta_robots,
                'word_count' => $pageModel->word_count,
                'headings' => $pageModel->headings ?? [],
                'is_indexable' => $pageModel->is_indexable,
                'indexability_reason' => $pageModel->indexability_reason,
                'in_sitemap' => $pageModel->in_sitemap,
                'inlinks_count' => $pageModel->inlinks_count,
                'structured_data' => $pageModel->structured_data ?? [],
                'images_missing_alt' => $pageModel->images_missing_alt ?? [],
                'issues' => $pageModel->issues ?? [],
                'out_links' => $pageModel->outLinks->map(fn ($l) => [
                    'to_url' => $l->to_url, 'type' => $l->type, 'anchor' => $l->anchor,
                ]),
            ],
        ]);
    }

    public function destroy(Request $request, string $crawl)
    {
        $project = $request->get('project');
        $project->crawls()->findOrFail($crawl)->delete();

        return redirect()
            ->route('app.project.crawls.index', ['project' => $project->id])
            ->with('success', __('crawler.deleted'));
    }

    public function export(Request $request, string $crawl): StreamedResponse
    {
        $project = $request->get('project');
        $model = $project->crawls()->findOrFail($crawl);

        return response()->streamDownload(function () use ($model) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['url', 'status_code', 'title', 'is_indexable', 'inlinks_count', 'depth', 'issues']);
            $model->pages()->orderBy('depth')->chunk(200, function ($pages) use ($out) {
                foreach ($pages as $p) {
                    fputcsv($out, [$p->url, $p->status_code, $p->title, $p->is_indexable ? 1 : 0, $p->inlinks_count, $p->depth, implode('|', $p->issues ?? [])]);
                }
            });
            fclose($out);
        }, "crawl-{$model->id}.csv", ['Content-Type' => 'text/csv']);
    }
}
```

- [ ] **Step 5: Register the routes**

In `routes/web.php`, add the import near the other controllers:

```php
use App\Http\Controllers\CrawlController;
```

Inside the `/project/{project}` group (next to the Sites block), add:

```php
            // SEO Crawler
            Route::get('/crawls', [CrawlController::class, 'index'])->name('app.project.crawls.index');
            Route::get('/crawls/create', [CrawlController::class, 'create'])->name('app.project.crawls.create');
            Route::post('/crawls', [CrawlController::class, 'store'])->name('app.project.crawls.store');
            Route::get('/crawls/{crawl}', [CrawlController::class, 'show'])->name('app.project.crawls.show');
            Route::get('/crawls/{crawl}/pages/{page}', [CrawlController::class, 'pageDetail'])->name('app.project.crawls.pages.show');
            Route::get('/crawls/{crawl}/export', [CrawlController::class, 'export'])->name('app.project.crawls.export');
            Route::delete('/crawls/{crawl}', [CrawlController::class, 'destroy'])->name('app.project.crawls.destroy');
```

- [ ] **Step 6: Add translation keys**

Locate the app translation files (`ddev php artisan lang:publish` not needed — grep for an existing key: `grep -rl "site_created" lang resources`). In the same file(s), add under the appropriate namespace the `crawler.*` keys used above: `started`, `deleted`, `invalid_url`, `unresolvable_url`, `private_url`, `mode_full_site`, `mode_single_page`, `status_queued`, `status_running`, `status_completed`, `status_failed`, and `issue.<code>` for every `IssueCode` value. Provide German + English strings, e.g. `crawler.issue.missing_title` = "Fehlender Titel" / "Missing title".

- [ ] **Step 7: Run tests to verify they pass**

Run: `ddev php artisan test --filter=CrawlControllerTest`
Expected: PASS.

- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/CrawlController.php app/Http/Requests/StoreCrawlRequest.php routes/web.php lang resources/lang tests/Feature/Crawler/CrawlControllerTest.php
git commit -m "feat(crawler): add CrawlController, routes, request validation, CSV export"
```

---

## Task 18: Frontend (Index, Create, Show, Page) + Sidebar + types

**Files:**
- Create: `resources/js/Pages/Crawls/Index.tsx`, `Create.tsx`, `Show.tsx`, `Page.tsx`
- Modify: `resources/js/Components/Sidebar.tsx` (nav entry), `resources/js/types/index.d.ts` (Crawl types)
- Test: manual + `ddev npm run build`

**Interfaces:**
- Consumes: routes `app.project.crawls.*`; props shapes emitted by `CrawlController`.
- Produces: four Inertia pages following the existing `Sites/Index.tsx` conventions (AppLayout, `@/Components/ui` kit, `useTranslation`, `route()` object params).

- [ ] **Step 1: Add the Sidebar nav entry**

In `resources/js/Components/Sidebar.tsx`, inside the `insights` group's `items` array (where `sites` lives), add:

```tsx
      { key: 'crawls', icon: Search, routeName: 'app.project.crawls.index' },
```

Import `Search` from `lucide-react` in the existing import line. Add the i18n label key `common.nav.crawls` (German "SEO-Crawler", English "SEO Crawler") wherever `common.nav.sites` is defined.

- [ ] **Step 2: Add TypeScript types**

In `resources/js/types/index.d.ts`, add:

```ts
export type CrawlSummary = Record<string, number>;

export interface CrawlListItem {
  id: string;
  start_url: string;
  mode: string;
  status: 'queued' | 'running' | 'completed' | 'failed';
  pages_crawled: number;
  created_at: string;
}

export interface CrawlPageRow {
  id: string;
  url: string;
  status_code: number | null;
  title: string | null;
  is_indexable: boolean;
  inlinks_count: number;
  depth: number | null;
  issues: string[];
}
```

- [ ] **Step 3: Implement Crawls/Index.tsx**

```tsx
import AppLayout from '@/Layouts/AppLayout';
import { EmptyState, Flash, LinkButton, PageHeader, RowActions, TableCard } from '@/Components/ui';
import { confirmDelete } from '@/lib/confirm';
import { useTranslation } from '@/lib/i18n';
import { CrawlListItem, PageProps } from '@/types';
import { router, usePage } from '@inertiajs/react';
import { Plus, Search, Trash2 } from 'lucide-react';

export default function CrawlsIndex({ crawls }: { crawls: CrawlListItem[] }) {
  const { project } = usePage<PageProps>().props;
  const { t } = useTranslation();

  async function destroy(c: CrawlListItem) {
    if (!(await confirmDelete({ title: c.start_url }))) return;
    router.delete(route('app.project.crawls.destroy', { project: project!.id, crawl: c.id }));
  }

  const createBtn = (
    <LinkButton href={route('app.project.crawls.create', { project: project!.id })}>
      <Plus className="h-4 w-4" />
      {t('crawler.create')}
    </LinkButton>
  );

  return (
    <AppLayout title={t('crawler.title')}>
      <div className="px-8 py-8">
        <PageHeader title={t('crawler.title')} action={createBtn} />
        <Flash />
        {crawls.length === 0 ? (
          <EmptyState icon={Search} title={t('crawler.empty')} hint={t('crawler.empty_hint')} action={createBtn} />
        ) : (
          <TableCard>
            <table className="w-full text-sm">
              <thead>
                <tr className="text-left text-muted">
                  <th className="px-4 py-2">{t('crawler.col_url')}</th>
                  <th className="px-4 py-2">{t('crawler.col_status')}</th>
                  <th className="px-4 py-2">{t('crawler.col_pages')}</th>
                  <th className="px-4 py-2" />
                </tr>
              </thead>
              <tbody>
                {crawls.map((c) => (
                  <tr key={c.id} className="border-t border-line">
                    <td className="px-4 py-2">
                      <a className="text-accent hover:underline" href={route('app.project.crawls.show', { project: project!.id, crawl: c.id })}>
                        {c.start_url}
                      </a>
                    </td>
                    <td className="px-4 py-2">{t(`crawler.status_${c.status}`)}</td>
                    <td className="px-4 py-2">{c.pages_crawled}</td>
                    <td className="px-4 py-2 text-right">
                      <RowActions actions={[{ icon: Trash2, label: t('common.delete'), onClick: () => destroy(c), danger: true }]} />
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </TableCard>
        )}
      </div>
    </AppLayout>
  );
}
```

- [ ] **Step 4: Implement Crawls/Create.tsx**

```tsx
import AppLayout from '@/Layouts/AppLayout';
import { Button, Card, Flash, FormField, Input, PageHeader, Select, Toggle } from '@/Components/ui';
import { useTranslation } from '@/lib/i18n';
import { PageProps } from '@/types';
import { useForm, usePage } from '@inertiajs/react';

type Mode = { value: string; label: string };

export default function CrawlsCreate({ modes }: { modes: Mode[] }) {
  const { project } = usePage<PageProps>().props;
  const { t } = useTranslation();
  const { data, setData, post, processing, errors } = useForm({
    start_url: '',
    mode: 'full_site',
    render_js: false,
    respect_robots: true,
    include_subdomains: false,
    delay_ms: 0,
    max_pages: '' as number | '',
  });

  function submit(e: React.FormEvent) {
    e.preventDefault();
    post(route('app.project.crawls.store', { project: project!.id }));
  }

  return (
    <AppLayout title={t('crawler.create')}>
      <div className="mx-auto max-w-2xl px-8 py-8">
        <PageHeader title={t('crawler.create')} />
        <Flash />
        <Card>
          <form onSubmit={submit} className="space-y-5 p-6">
            <FormField label={t('crawler.start_url')} error={errors.start_url}>
              <Input type="url" value={data.start_url} onChange={(e) => setData('start_url', e.target.value)} placeholder="https://example.com" required />
            </FormField>
            <FormField label={t('crawler.mode')} error={errors.mode}>
              <Select value={data.mode} onChange={(e) => setData('mode', e.target.value)}>
                {modes.map((m) => <option key={m.value} value={m.value}>{m.label}</option>)}
              </Select>
            </FormField>
            <Toggle label={t('crawler.render_js')} hint={t('crawler.render_js_hint')} checked={data.render_js} onChange={(v) => setData('render_js', v)} />
            <Toggle label={t('crawler.respect_robots')} checked={data.respect_robots} onChange={(v) => setData('respect_robots', v)} />
            <Toggle label={t('crawler.include_subdomains')} checked={data.include_subdomains} onChange={(v) => setData('include_subdomains', v)} />
            <FormField label={t('crawler.delay_ms')} error={errors.delay_ms}>
              <Input type="number" min={0} max={10000} value={data.delay_ms} onChange={(e) => setData('delay_ms', Number(e.target.value))} />
            </FormField>
            <FormField label={t('crawler.max_pages')} hint={t('crawler.max_pages_hint')} error={errors.max_pages}>
              <Input type="number" min={1} value={data.max_pages} onChange={(e) => setData('max_pages', e.target.value === '' ? '' : Number(e.target.value))} />
            </FormField>
            <Button type="submit" disabled={processing}>{t('crawler.start')}</Button>
          </form>
        </Card>
      </div>
    </AppLayout>
  );
}
```

> Note: if `Toggle`/`Select`/`FormField` component names differ in the kit, grep `resources/js/Components/ui/index.ts` for the actual exports and use those (the Sites/Create.tsx page is the reference for form primitives).

- [ ] **Step 5: Implement Crawls/Show.tsx (summary cards + polling table)**

```tsx
import AppLayout from '@/Layouts/AppLayout';
import { Card, Flash, LinkButton, PageHeader } from '@/Components/ui';
import { useTranslation } from '@/lib/i18n';
import { CrawlPageRow, CrawlSummary, PageProps } from '@/types';
import { router, usePage } from '@inertiajs/react';
import { useEffect } from 'react';

type Crawl = { id: string; start_url: string; status: string; pages_crawled: number; summary: CrawlSummary; error: string | null };
type Paginated<T> = { data: T[]; links: unknown };

export default function CrawlsShow({ crawl, pages }: { crawl: Crawl; pages: Paginated<CrawlPageRow> }) {
  const { project } = usePage<PageProps>().props;
  const { t } = useTranslation();

  // Poll while the crawl is still running.
  useEffect(() => {
    if (crawl.status === 'running' || crawl.status === 'queued') {
      const id = setInterval(() => router.reload({ only: ['crawl', 'pages'] }), 3000);
      return () => clearInterval(id);
    }
  }, [crawl.status]);

  const exportUrl = route('app.project.crawls.export', { project: project!.id, crawl: crawl.id });

  return (
    <AppLayout title={crawl.start_url}>
      <div className="px-8 py-8">
        <PageHeader
          title={crawl.start_url}
          subtitle={`${t(`crawler.status_${crawl.status}`)} · ${crawl.pages_crawled} ${t('crawler.pages')}`}
          action={<LinkButton href={exportUrl}>{t('crawler.export_csv')}</LinkButton>}
        />
        <Flash />

        {crawl.error && <Card className="mb-4 border-danger p-4 text-danger">{crawl.error}</Card>}

        <div className="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-4">
          {Object.entries(crawl.summary).map(([code, count]) => (
            <Card key={code} className="p-4">
              <div className="text-2xl font-semibold text-foreground">{count}</div>
              <div className="text-xs text-muted">{t(`crawler.issue.${code}`)}</div>
            </Card>
          ))}
        </div>

        <Card>
          <table className="w-full text-sm">
            <thead>
              <tr className="text-left text-muted">
                <th className="px-4 py-2">{t('crawler.col_url')}</th>
                <th className="px-4 py-2">{t('crawler.col_status')}</th>
                <th className="px-4 py-2">{t('crawler.col_indexable')}</th>
                <th className="px-4 py-2">{t('crawler.col_inlinks')}</th>
                <th className="px-4 py-2">{t('crawler.col_issues')}</th>
              </tr>
            </thead>
            <tbody>
              {pages.data.map((p) => (
                <tr key={p.id} className="border-t border-line">
                  <td className="px-4 py-2">
                    <a className="text-accent hover:underline" href={route('app.project.crawls.pages.show', { project: project!.id, crawl: crawl.id, page: p.id })}>
                      {p.url}
                    </a>
                  </td>
                  <td className="px-4 py-2">{p.status_code ?? '—'}</td>
                  <td className="px-4 py-2">{p.is_indexable ? '✓' : '✗'}</td>
                  <td className="px-4 py-2">{p.inlinks_count}</td>
                  <td className="px-4 py-2">{p.issues.length}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </Card>
      </div>
    </AppLayout>
  );
}
```

- [ ] **Step 6: Implement Crawls/Page.tsx (detail)**

```tsx
import AppLayout from '@/Layouts/AppLayout';
import { Card, PageHeader } from '@/Components/ui';
import { useTranslation } from '@/lib/i18n';

type Heading = { level: number; text: string };
type PageData = {
  url: string; final_url: string | null; status_code: number | null;
  redirect_chain: string[] | null; title: string | null; meta_description: string | null;
  canonical: string | null; meta_robots: string | null; word_count: number | null;
  headings: Heading[]; is_indexable: boolean; indexability_reason: string | null;
  in_sitemap: boolean; inlinks_count: number; structured_data: string[];
  images_missing_alt: string[]; issues: string[];
  out_links: { to_url: string; type: string; anchor: string }[];
};

export default function CrawlsPage({ page }: { page: PageData }) {
  const { t } = useTranslation();

  return (
    <AppLayout title={page.url}>
      <div className="px-8 py-8">
        <PageHeader title={page.url} subtitle={`${page.status_code ?? '—'} · ${page.word_count ?? 0} ${t('crawler.words')}`} />

        <div className="grid gap-4 md:grid-cols-2">
          <Card className="p-4">
            <h3 className="mb-2 font-medium">{t('crawler.meta')}</h3>
            <dl className="space-y-1 text-sm">
              <div><dt className="text-muted">Title</dt><dd>{page.title ?? '—'}</dd></div>
              <div><dt className="text-muted">Description</dt><dd>{page.meta_description ?? '—'}</dd></div>
              <div><dt className="text-muted">Canonical</dt><dd>{page.canonical ?? '—'}</dd></div>
              <div><dt className="text-muted">Robots</dt><dd>{page.meta_robots ?? '—'}</dd></div>
              <div><dt className="text-muted">{t('crawler.col_indexable')}</dt><dd>{page.is_indexable ? '✓' : `✗ (${page.indexability_reason})`}</dd></div>
            </dl>
          </Card>

          <Card className="p-4">
            <h3 className="mb-2 font-medium">{t('crawler.headings')}</h3>
            <ul className="space-y-1 text-sm">
              {page.headings.map((h, i) => (
                <li key={i} style={{ paddingLeft: (h.level - 1) * 12 }}>H{h.level}: {h.text}</li>
              ))}
            </ul>
          </Card>

          <Card className="p-4">
            <h3 className="mb-2 font-medium">{t('crawler.issues')}</h3>
            <ul className="space-y-1 text-sm">
              {page.issues.length === 0 ? <li className="text-muted">—</li> : page.issues.map((c) => <li key={c}>{t(`crawler.issue.${c}`)}</li>)}
            </ul>
          </Card>

          <Card className="p-4">
            <h3 className="mb-2 font-medium">{t('crawler.images_missing_alt')} ({page.images_missing_alt.length})</h3>
            <ul className="space-y-1 text-sm">
              {page.images_missing_alt.map((src) => <li key={src} className="truncate">{src}</li>)}
            </ul>
          </Card>
        </div>
      </div>
    </AppLayout>
  );
}
```

- [ ] **Step 7: Add the frontend i18n keys**

Add every `crawler.*` key referenced above (`title`, `create`, `start`, `empty`, `empty_hint`, `start_url`, `mode`, `render_js`, `render_js_hint`, `respect_robots`, `include_subdomains`, `delay_ms`, `max_pages`, `max_pages_hint`, `status_*`, `pages`, `words`, `meta`, `headings`, `issues`, `images_missing_alt`, `export_csv`, `col_*`, `issue.*`) to the frontend translation files (same files touched in Task 17 Step 6), German + English.

- [ ] **Step 8: Build the frontend**

Run: `ddev npm run build`
Expected: TypeScript check + Vite build succeed with no errors.

- [ ] **Step 9: Commit**

```bash
git add resources/js/Pages/Crawls resources/js/Components/Sidebar.tsx resources/js/types/index.d.ts lang resources/lang
git commit -m "feat(crawler): add crawl UI (index, create, show, page) + sidebar nav"
```

---

## Task 19: Full-suite verification

**Files:** none (verification only).

- [ ] **Step 1: Run the whole backend suite**

Run: `ddev php artisan test`
Expected: all green (or only the `@group network` RunCrawl test skipped).

- [ ] **Step 2: Build the frontend**

Run: `ddev npm run build`
Expected: success.

- [ ] **Step 3: Manual smoke test**

Start `composer run dev`, log in, open a project, go to **SEO Crawler → New**, run a single-page crawl against a known public SSR page, confirm the Show page fills in and the page detail renders headings/issues.

- [ ] **Step 4: Final commit (if any fixups were needed)**

```bash
git add -A
git commit -m "chore(crawler): verification fixups"
```

---

## Self-Review (author checklist — completed)

- **Spec coverage:** Status&Links → Tasks 8, 12, 14; On-Page&Meta → Task 5; Heading-Struktur → Task 6; Indexierbarkeit → Task 7 + sitemap in Task 14; Bilder&Technik → Tasks 9, 10; robots.txt + Delay → Task 15; Domain-Grenze → Task 15 (CrawlInternalUrls); Seitenlimit optional → Tasks 2/15; Historie → Task 2 (per-run rows, no overwrite); UI kombiniert → Task 18; SSRF → Task 16; CSV-Export → Task 17. Broken-Link-Probing intentionally omitted (Out of Scope).
- **Placeholder scan:** none — every code/test step has concrete content; the two "Note" blocks (component-name grep, network-test skip) are guidance, not deferred work.
- **Type consistency:** `AnalyzerResult.data`/`issues`, `PageContext(url,statusCode,baseHost,robotsBlocked)`, `PageAnalyzer::analyze(string,PageContext)`, `CrawlPageObserver::recordResponse(...)`, `AggregateCrawlJob(Crawl)`, `RunCrawlJob(Crawl)`, route names `app.project.crawls.*` — consistent across tasks.
