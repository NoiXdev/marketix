# Crawler — Resource view, Increment 1 — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Capture per-page resource references (`<img>`/`<script>`/`<link>`/`<source>`) in a new `crawl_resources` table via a `ResourceExtractor`, differentiate JS/CSS/Font content categories, and add a **Resources** tab listing unique resources (type, size/status for internal, reference count). No external probing (Increment 2).

**Architecture:** `ResourceExtractor` analyzer → `$data['resources']` → observer persists to `crawl_resources` (mirrors out-links). `ResourceClassifier` gains js/css/font. `CrawlController::show()` serves a grouped resources aggregation under `?view=resources`; `Crawls/Show.tsx` renders a Resources tab.

**Tech Stack:** Laravel 13 / PHP 8.3, Inertia + React/TS, Symfony DomCrawler, PHPUnit, Pint.

**Spec:** `docs/superpowers/specs/2026-08-15-crawler-resources-increment1-design.md`

## Global Constraints

- **No new `IssueCode`/`CheckCatalog` checks** — this is a listing feature, not a check. The bijection is untouched.
- **No new crawl-time network requests** — extraction reads already-fetched HTML; internal size/status come from already-crawled `crawl_pages`; external resources are listed without size/status.
- Multi-tenant scoping unchanged (`$project->crawls()->findOrFail`).
- Run via DDEV. Backend gate `ddev php artisan test`; frontend gate `ddev npm run build`; Pint clean `ddev exec vendor/bin/pint --test`.

---

## Task 1: `crawl_resources` data model

**Files:** Create `database/migrations/2026_08_15_000006_create_crawl_resources_table.php`, `app/Models/CrawlResource.php`, `database/factories/CrawlResourceFactory.php`; Modify `app/Models/Crawl.php`, `app/Models/CrawlPage.php`; Test `tests/Feature/Crawler/CrawlResourceModelTest.php`.

**Interfaces:** Produces the `crawl_resources` table, `CrawlResource` model, `Crawl::resources()` + `CrawlPage::resources()` relations, and a factory. Tasks 2 & 4 consume them.

- [ ] **Step 1: Migration.** Create `database/migrations/2026_08_15_000006_create_crawl_resources_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crawl_resources', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('crawl_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('from_page_id')->constrained('crawl_pages')->cascadeOnDelete();
            $table->text('url');
            $table->string('type')->default('other'); // javascript | css | font | image | other
            $table->boolean('is_internal')->default(true);
            $table->timestamps();
            $table->index(['crawl_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crawl_resources');
    }
};
```

- [ ] **Step 2: Model.** Create `app/Models/CrawlResource.php`:

```php
<?php

namespace App\Models;

use Database\Factories\CrawlResourceFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrawlResource extends Model
{
    /** @use HasFactory<CrawlResourceFactory> */
    use HasFactory, HasUlids;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_internal' => 'boolean'];
    }

    public function fromPage(): BelongsTo
    {
        return $this->belongsTo(CrawlPage::class, 'from_page_id');
    }
}
```

- [ ] **Step 3: Factory.** Create `database/factories/CrawlResourceFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\Crawl;
use App\Models\CrawlPage;
use App\Models\CrawlResource;
use Illuminate\Database\Eloquent\Factories\Factory;

class CrawlResourceFactory extends Factory
{
    protected $model = CrawlResource::class;

    public function definition(): array
    {
        return [
            'crawl_id' => Crawl::factory(),
            'from_page_id' => CrawlPage::factory(),
            'url' => 'https://example.com/assets/'.$this->faker->slug().'.js',
            'type' => 'javascript',
            'is_internal' => true,
        ];
    }
}
```

- [ ] **Step 4: Relations.** In `app/Models/Crawl.php`, add (mirroring `links()`):

```php
    public function resources(): HasMany
    {
        return $this->hasMany(CrawlResource::class);
    }
```

  In `app/Models/CrawlPage.php`, add (mirroring `outLinks()`):

```php
    public function resources(): HasMany
    {
        return $this->hasMany(CrawlResource::class, 'from_page_id');
    }
```

- [ ] **Step 5: Model test.** Create `tests/Feature/Crawler/CrawlResourceModelTest.php`:

```php
<?php

namespace Tests\Feature\Crawler;

use App\Models\Crawl;
use App\Models\CrawlPage;
use App\Models\CrawlResource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrawlResourceModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_resource_relations_and_cast(): void
    {
        $crawl = Crawl::factory()->create();
        $page = CrawlPage::factory()->for($crawl)->create();
        $res = CrawlResource::factory()->create([
            'crawl_id' => $crawl->id, 'from_page_id' => $page->id,
            'url' => 'https://example.com/app.css', 'type' => 'css', 'is_internal' => true,
        ]);

        $this->assertTrue($crawl->resources()->whereKey($res->id)->exists());
        $this->assertTrue($page->resources()->whereKey($res->id)->exists());
        $this->assertIsBool($res->refresh()->is_internal);
    }
}
```

- [ ] **Step 6: Run + Pint.**

Run: `ddev php artisan test --filter=CrawlResourceModelTest` → PASS.
Run: `ddev exec vendor/bin/pint --test app/Models/CrawlResource.php app/Models/Crawl.php app/Models/CrawlPage.php database/factories/CrawlResourceFactory.php database/migrations/2026_08_15_000006_create_crawl_resources_table.php tests/Feature/Crawler/CrawlResourceModelTest.php` → PASS.

- [ ] **Step 7: Commit.** `git add app/Models/CrawlResource.php app/Models/Crawl.php app/Models/CrawlPage.php database/factories/CrawlResourceFactory.php database/migrations/2026_08_15_000006_create_crawl_resources_table.php tests/Feature/Crawler/CrawlResourceModelTest.php && git commit -m "feat(crawler): crawl_resources table + CrawlResource model"`

---

## Task 2: `ResourceExtractor` + observer persistence

**Files:** Create `app/Crawler/Analyzers/ResourceExtractor.php`, `tests/Unit/Crawler/ResourceExtractorTest.php`; Modify `app/Crawler/PageAnalyzer.php`, `app/Observers/CrawlPageObserver.php`; Test `tests/Feature/Crawler/CrawlPageObserverResourceTest.php` (create).

**Interfaces:** Consumes `CrawlPage::resources()` (Task 1). Produces `$data['resources']` (list of `['url','type','is_internal']`, deduped per page), persisted to `crawl_resources` by the observer.

- [ ] **Step 1: Write `ResourceExtractor`.** Create `app/Crawler/Analyzers/ResourceExtractor.php`:

```php
<?php

namespace App\Crawler\Analyzers;

use App\Crawler\AnalyzerResult;
use App\Crawler\PageContext;
use Symfony\Component\DomCrawler\Crawler;

class ResourceExtractor implements Analyzer
{
    private const FONT_EXT = ['woff', 'woff2', 'ttf', 'otf', 'eot'];

    public function analyze(Crawler $dom, PageContext $ctx): AnalyzerResult
    {
        $r = new AnalyzerResult;
        $byUrl = [];

        $add = function (?string $raw, string $type) use (&$byUrl, $ctx) {
            $abs = $this->resolve(trim((string) $raw), $ctx->url);
            if ($abs === null || isset($byUrl[$abs])) {
                return;
            }
            if ($type !== 'font' && in_array($this->ext($abs), self::FONT_EXT, true)) {
                $type = 'font';
            }
            $host = strtolower(parse_url($abs, PHP_URL_HOST) ?? '');
            $internal = $host === $ctx->baseHost || str_ends_with($host, '.'.$ctx->baseHost);
            $byUrl[$abs] = ['url' => $abs, 'type' => $type, 'is_internal' => $internal];
        };

        $dom->filter('script[src]')->each(fn (Crawler $n) => $add($n->attr('src'), 'javascript'));
        $dom->filter('img[src]')->each(fn (Crawler $n) => $add($n->attr('src'), 'image'));
        $dom->filter('img[data-src]')->each(fn (Crawler $n) => $add($n->attr('data-src'), 'image'));
        $dom->filter('source[src]')->each(fn (Crawler $n) => $add($n->attr('src'), 'image'));
        $dom->filter('link[rel]')->each(function (Crawler $n) use ($add) {
            $rel = strtolower($n->attr('rel') ?? '');
            $as = strtolower($n->attr('as') ?? '');
            if (str_contains($rel, 'stylesheet') || $as === 'style') {
                $add($n->attr('href'), 'css');
            } elseif (str_contains($rel, 'preload') && $as === 'font') {
                $add($n->attr('href'), 'font');
            } elseif (str_contains($rel, 'icon')) {
                $add($n->attr('href'), 'image');
            }
        });

        $r->add('resources', array_values($byUrl));

        return $r;
    }

    private function ext(string $url): string
    {
        return strtolower(pathinfo(parse_url($url, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));
    }

    private function resolve(string $href, string $base): ?string
    {
        if ($href === '' || str_starts_with($href, '#') || preg_match('/^(data:|mailto:|tel:|javascript:)/i', $href)) {
            return null;
        }
        if (preg_match('#^https?://#i', $href)) {
            return $href;
        }
        if (str_starts_with($href, '//')) {
            return (parse_url($base, PHP_URL_SCHEME) ?: 'https').':'.$href;
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

- [ ] **Step 2: Register in `PageAnalyzer`.** In `app/Crawler/PageAnalyzer.php`, add `use App\Crawler\Analyzers\ResourceExtractor;` and append `new ResourceExtractor,` to the `analyzers()` array.

- [ ] **Step 3: Persist in the observer.** In `app/Observers/CrawlPageObserver.php::recordResponse()`:
  - Where `$links = []` and `$data = []` are initialised before the branch, also add `$resources = [];`.
  - In the HTML branch, after `unset($analysis->data['links']);`, add:

```php
            $resources = $analysis->data['resources'] ?? [];
            unset($analysis->data['resources']);
```

  - After the existing `foreach ($links as $link) { $page->outLinks()->create([...]); }` loop, add:

```php
        foreach ($resources as $res) {
            $page->resources()->create([
                'crawl_id' => $this->crawl->id,
                'url' => $res['url'],
                'type' => $res['type'],
                'is_internal' => $res['is_internal'],
            ]);
        }
```

- [ ] **Step 4: Write the unit test.** Create `tests/Unit/Crawler/ResourceExtractorTest.php`:

```php
<?php

namespace Tests\Unit\Crawler;

use App\Crawler\Analyzers\ResourceExtractor;
use App\Crawler\PageContext;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DomCrawler\Crawler;

class ResourceExtractorTest extends TestCase
{
    /** @return array<int, array{url:string,type:string,is_internal:bool}> */
    private function extract(string $body): array
    {
        $html = '<html><head></head><body>'.$body.'</body></html>';

        return (new ResourceExtractor)->analyze(new Crawler($html), new PageContext('https://x.test/page', 200, 'x.test'))->data['resources'];
    }

    public function test_extracts_types_and_internal_flag(): void
    {
        $res = collect($this->extract(
            '<script src="/app.js"></script>'
            .'<link rel="stylesheet" href="/app.css">'
            .'<img src="/logo.png">'
            .'<link rel="preload" as="font" href="/f.woff2">'
            .'<script src="https://cdn.test/lib.js"></script>'
        ))->keyBy('url');

        $this->assertSame('javascript', $res['https://x.test/app.js']['type']);
        $this->assertTrue($res['https://x.test/app.js']['is_internal']);
        $this->assertSame('css', $res['https://x.test/app.css']['type']);
        $this->assertSame('image', $res['https://x.test/logo.png']['type']);
        $this->assertSame('font', $res['https://x.test/f.woff2']['type']);
        $this->assertFalse($res['https://cdn.test/lib.js']['is_internal']);
    }

    public function test_dedupes_within_page_and_skips_data_uris(): void
    {
        $res = $this->extract('<img src="/a.png"><img src="/a.png"><img src="data:image/png;base64,xxxx">');
        $urls = array_column($res, 'url');
        $this->assertSame(['https://x.test/a.png'], $urls);
    }
}
```

- [ ] **Step 5: Write the observer test.** Create `tests/Feature/Crawler/CrawlPageObserverResourceTest.php`:

```php
<?php

namespace Tests\Feature\Crawler;

use App\Crawler\PageAnalyzer;
use App\Models\Crawl;
use App\Models\Project;
use App\Observers\CrawlPageObserver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrawlPageObserverResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_persists_resource_references(): void
    {
        $crawl = Crawl::factory()->for(Project::factory())->create();
        $observer = new CrawlPageObserver($crawl, new PageAnalyzer, 'example.com');
        $page = $observer->recordResponse(
            'https://example.com/',
            200,
            ['Content-Type' => ['text/html']],
            '<html><head><link rel="stylesheet" href="/app.css"><title>t</title></head><body><script src="/app.js"></script><img src="/a.png">'.str_repeat('w ', 150).'</body></html>',
            5.0,
        );

        $types = $page->resources()->pluck('type', 'url');
        $this->assertSame('css', $types['https://example.com/app.css']);
        $this->assertSame('javascript', $types['https://example.com/app.js']);
        $this->assertSame('image', $types['https://example.com/a.png']);
    }
}
```

- [ ] **Step 6: Run + Pint.**

Run: `ddev php artisan test --filter='ResourceExtractorTest|CrawlPageObserverResourceTest|PageAnalyzerTest'` → PASS.
Run: `ddev exec vendor/bin/pint --test app/Crawler/Analyzers/ResourceExtractor.php app/Crawler/PageAnalyzer.php app/Observers/CrawlPageObserver.php tests/Unit/Crawler/ResourceExtractorTest.php tests/Feature/Crawler/CrawlPageObserverResourceTest.php` → PASS.

- [ ] **Step 7: Commit.** `git add app/Crawler/Analyzers/ResourceExtractor.php app/Crawler/PageAnalyzer.php app/Observers/CrawlPageObserver.php tests/Unit/Crawler/ResourceExtractorTest.php tests/Feature/Crawler/CrawlPageObserverResourceTest.php && git commit -m "feat(crawler): ResourceExtractor persists per-page resource references"`

---

## Task 3: `ResourceClassifier` JS/CSS/Font + labels

**Files:** Modify `app/Crawler/ResourceClassifier.php`, `lang/en/crawler.php`, `lang/de/crawler.php`; Test `tests/Unit/Crawler/ResourceClassifierTest.php`.

**Interfaces:** Adds `ResourceClassifier::JAVASCRIPT`/`CSS`/`FONT` constants + categorisation; adds `crawler.category.{javascript,css,font}` labels reused by the All-URLs filter and Task 4's resource badges. `sizeIssue()` needs NO change (its `default` arm already covers the new categories at the 2 MB tier).

- [ ] **Step 1: Extend `ResourceClassifier`.** In `app/Crawler/ResourceClassifier.php`, add constants after `MEDIA`:

```php
    public const JAVASCRIPT = 'javascript';

    public const CSS = 'css';

    public const FONT = 'font';
```

  and extend `categorize()`'s match (before the `default`):

```php
            $type === 'application/javascript', $type === 'text/javascript', $type === 'application/x-javascript', $type === 'application/ecmascript' => self::JAVASCRIPT,
            $type === 'text/css' => self::CSS,
            str_starts_with($type, 'font/'), str_starts_with($type, 'application/font-'), str_starts_with($type, 'application/x-font-'), $type === 'application/vnd.ms-fontobject' => self::FONT,
```

- [ ] **Step 2: Labels.** In `lang/en/crawler.php`'s `'category'` array, add `'javascript' => 'JavaScript', 'css' => 'CSS', 'font' => 'Font',`. In `lang/de/crawler.php`'s `'category'` array, add `'javascript' => 'JavaScript', 'css' => 'CSS', 'font' => 'Schriftart',`.

- [ ] **Step 3: Update `ResourceClassifierTest`.** Add assertions:

```php
    public function test_classifies_js_css_font(): void
    {
        $this->assertSame(ResourceClassifier::JAVASCRIPT, ResourceClassifier::categorize('application/javascript'));
        $this->assertSame(ResourceClassifier::JAVASCRIPT, ResourceClassifier::categorize('text/javascript; charset=utf-8'));
        $this->assertSame(ResourceClassifier::CSS, ResourceClassifier::categorize('text/css'));
        $this->assertSame(ResourceClassifier::FONT, ResourceClassifier::categorize('font/woff2'));
        $this->assertSame(ResourceClassifier::FONT, ResourceClassifier::categorize('application/vnd.ms-fontobject'));
        // still flags a large js/css/font via the non-image tier.
        $this->assertSame(IssueCode::LargeResource, ResourceClassifier::sizeIssue(ResourceClassifier::CSS, 3_000_000));
    }
```

  (Add `use App\Crawler\IssueCode;` to the test if not present. Keep existing html/image/pdf/media/other assertions.)

- [ ] **Step 4: Run + Pint.**

Run: `ddev php artisan test --filter=ResourceClassifierTest` → PASS.
Run: `ddev exec vendor/bin/pint --test app/Crawler/ResourceClassifier.php lang/en/crawler.php lang/de/crawler.php tests/Unit/Crawler/ResourceClassifierTest.php` → PASS.

- [ ] **Step 5: Commit.** `git add app/Crawler/ResourceClassifier.php lang/en/crawler.php lang/de/crawler.php tests/Unit/Crawler/ResourceClassifierTest.php && git commit -m "feat(crawler): classify javascript/css/font resource types"`

---

## Task 4: Resources view — controller aggregation + `Show.tsx` tab

**Files:** Modify `app/Http/Controllers/CrawlController.php`, `resources/js/Pages/Crawls/Show.tsx`, `lang/en/crawler.php`, `lang/de/crawler.php`, `resources/js/types/index.d.ts`; Test `tests/Feature/Crawler/CrawlControllerTest.php`.

**Interfaces:** Consumes `Crawl::resources()` (Task 1), the crawled `crawl_pages` (size/status), and the category labels (Task 3).

- [ ] **Step 1: Controller `?view=resources` branch.** In `app/Http/Controllers/CrawlController.php::show()`, read `$view = $request->query('view');` and `$resourceType = $request->query('resource_type');`. Build the resources payload ONLY when requested (keep the default page light):

```php
        $resources = null;
        $resourceSummary = [];
        if ($view === 'resources') {
            $resourceSummary = $model->resources()
                ->selectRaw('type, count(distinct url) as c')
                ->groupBy('type')
                ->pluck('c', 'type');

            $rq = $model->resources()
                ->selectRaw('url, min(type) as type, max(is_internal) as is_internal, count(distinct from_page_id) as ref_count')
                ->groupBy('url');
            if (is_string($resourceType) && $resourceType !== '') {
                $rq->where('type', $resourceType);
            }
            $paginator = $rq->orderByDesc('ref_count')->paginate(50)->withQueryString();

            $meta = $model->pages()
                ->whereIn('url', $paginator->pluck('url')->all())
                ->get(['url', 'status_code', 'size_bytes'])
                ->keyBy('url');
            $paginator->getCollection()->transform(fn ($row) => [
                'url' => $row->url,
                'type' => $row->type,
                'is_internal' => (bool) $row->is_internal,
                'ref_count' => (int) $row->ref_count,
                'status_code' => $row->is_internal ? ($meta[$row->url]->status_code ?? null) : null,
                'size_bytes' => $row->is_internal ? ($meta[$row->url]->size_bytes ?? null) : null,
            ]);
            $resources = $paginator;
        }
```

  Add to the `inertia('Crawls/Show', [...])` payload: `'resources' => $resources, 'resourceSummary' => $resourceSummary,` and extend `'filters'` with `'view' => $view === 'resources' ? 'resources' : null, 'resource_type' => is_string($resourceType) && $resourceType !== '' ? $resourceType : null,`.

- [ ] **Step 2: Types.** In `resources/js/types/index.d.ts`, export:

```ts
export interface CrawlResourceRow {
  url: string;
  type: string;
  is_internal: boolean;
  ref_count: number;
  status_code: number | null;
  size_bytes: number | null;
}
```

- [ ] **Step 3: `Show.tsx` — props, tab, render.** In `resources/js/Pages/Crawls/Show.tsx`:
  - Extend `Filters` with `view: string | null; resource_type: string | null;` and add props `resources: Paginated<CrawlResourceRow> | null` and `resourceSummary: Record<string, number>` (import `CrawlResourceRow`). Add a small `formatBytes(n: number|null)` helper (or reuse an existing one; if none, inline: returns "—" for null else KB/MB).
  - Change `activeTab`: `const activeTab = filters.view === 'resources' ? 'resources' : (filters.group ?? (filters.category ? 'all' : 'overview'));`
  - In the tab bar, after the "All URLs" `TabButton`, add:

```tsx
          <TabButton active={activeTab === 'resources'} onClick={() => go({ view: 'resources' })} label={t('crawler.tab_resources')} />
```

  - Add a render block (after the `activeTab === 'all'` block):

```tsx
        {activeTab === 'resources' && resources && (
          <>
            <div className="mb-3 flex flex-wrap items-center gap-2">
              {Object.entries(resourceSummary).map(([type, count]) => (
                <Badge key={type}>{t(`crawler.category.${type}`)}: {count}</Badge>
              ))}
            </div>
            <div className="mb-3 flex items-center gap-2">
              <span className="text-sm text-muted">{t('crawler.filter_type')}</span>
              <Select value={filters.resource_type ?? ''} onChange={(e) => go({ view: 'resources', resource_type: e.target.value || null })} className="w-48">
                <option value="">{t('crawler.filter_all')}</option>
                {['javascript', 'css', 'font', 'image', 'other'].map((ty) => (
                  <option key={ty} value={ty}>{t(`crawler.category.${ty}`)}</option>
                ))}
              </Select>
            </div>
            <TableCard
              columns={[
                { label: t('crawler.col_url') },
                { label: t('crawler.col_type') },
                { label: t('crawler.col_size') },
                { label: t('crawler.col_status') },
                { label: t('crawler.col_references') },
              ]}
            >
              <tbody className="divide-y divide-line">
                {resources.data.map((res) => (
                  <tr key={res.url} className="hover:bg-elevated">
                    <td className="px-4 py-3">
                      <span className="break-all text-foreground">{res.url}</span>
                      <span className="ml-2 text-xs text-muted">{res.is_internal ? t('crawler.resource_internal') : t('crawler.resource_external')}</span>
                    </td>
                    <td className="px-4 py-3"><Badge>{t(`crawler.category.${res.type}`)}</Badge></td>
                    <td className="px-4 py-3 text-muted">{formatBytes(res.size_bytes)}</td>
                    <td className="px-4 py-3 text-muted">{res.status_code ?? '—'}</td>
                    <td className="px-4 py-3 text-muted">{res.ref_count}</td>
                  </tr>
                ))}
              </tbody>
            </TableCard>
            <div className="mt-2"><Pagination links={resources.links} /></div>
          </>
        )}
```

  - Widen the `useEffect` reload `only:` list to include `'resources'` so the running-crawl auto-refresh keeps the resources tab current (optional but consistent).

- [ ] **Step 4: Lang keys.** In BOTH `lang/en/crawler.php` and `lang/de/crawler.php`, add: `tab_resources` (EN "Resources" / DE "Ressourcen"), `col_size` (EN "Size" / DE "Größe"), `col_references` (EN "References" / DE "Referenzen"), `resource_internal` (EN "internal" / DE "intern"), `resource_external` (EN "external" / DE "extern"). (`col_url`, `col_type`, `col_status`, `filter_type`, `filter_all` already exist.)

- [ ] **Step 5: Feature test.** In `tests/Feature/Crawler/CrawlControllerTest.php`, add:

```php
    public function test_resources_view_lists_grouped_resources(): void
    {
        [$user, $project] = $this->member();
        $crawl = Crawl::factory()->for($project)->create();
        $p1 = CrawlPage::factory()->for($crawl)->create(['url' => 'https://example.com/a']);
        $p2 = CrawlPage::factory()->for($crawl)->create(['url' => 'https://example.com/b']);
        // Internal CSS referenced by BOTH pages + crawled as its own page (size/status known).
        CrawlPage::factory()->for($crawl)->create(['url' => 'https://example.com/app.css', 'status_code' => 200, 'size_bytes' => 4096, 'content_category' => 'css']);
        \App\Models\CrawlResource::factory()->create(['crawl_id' => $crawl->id, 'from_page_id' => $p1->id, 'url' => 'https://example.com/app.css', 'type' => 'css', 'is_internal' => true]);
        \App\Models\CrawlResource::factory()->create(['crawl_id' => $crawl->id, 'from_page_id' => $p2->id, 'url' => 'https://example.com/app.css', 'type' => 'css', 'is_internal' => true]);
        // External JS referenced once (no crawled page → null size/status).
        \App\Models\CrawlResource::factory()->create(['crawl_id' => $crawl->id, 'from_page_id' => $p1->id, 'url' => 'https://cdn.test/lib.js', 'type' => 'javascript', 'is_internal' => false]);

        $this->actingAs($user)
            ->get(route('app.project.crawls.show', ['project' => $project->id, 'crawl' => $crawl->id, 'view' => 'resources']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('filters.view', 'resources')
                ->where('resources.data', fn ($rows) => collect($rows)->firstWhere('url', 'https://example.com/app.css')['ref_count'] === 2
                    && collect($rows)->firstWhere('url', 'https://example.com/app.css')['size_bytes'] === 4096
                    && collect($rows)->firstWhere('url', 'https://cdn.test/lib.js')['size_bytes'] === null)
                ->where('resourceSummary.css', 1)
            );

        // Type filter narrows to javascript only.
        $this->actingAs($user)
            ->get(route('app.project.crawls.show', ['project' => $project->id, 'crawl' => $crawl->id, 'view' => 'resources', 'resource_type' => 'javascript']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('resources.data', 1)
                ->where('resources.data.0.url', 'https://cdn.test/lib.js')
            );
    }
```

  (The `?view=resources` closure-`where` mechanics may need adapting to the installed AssertableInertia version — the intent is: `app.css` has ref_count 2 + size 4096, the external js has null size, and `resourceSummary.css` is 1. Adapt the assertion form if needed while preserving that intent.)

- [ ] **Step 6: Run tests + build + Pint.**

Run: `ddev php artisan test --filter=CrawlControllerTest` → PASS.
Run: `ddev npm run build` → green (tsc + vite; the new tab + types compile).
Run: `ddev exec vendor/bin/pint --test app/Http/Controllers/CrawlController.php lang/en/crawler.php lang/de/crawler.php tests/Feature/Crawler/CrawlControllerTest.php` → PASS.

- [ ] **Step 7: Commit.** `git add app/Http/Controllers/CrawlController.php resources/js/Pages/Crawls/Show.tsx resources/js/types/index.d.ts lang/en/crawler.php lang/de/crawler.php tests/Feature/Crawler/CrawlControllerTest.php && git commit -m "feat(crawler): Resources tab listing grouped resources with type/size/refs"`

---

## Final verification (whole plan)

- [ ] `ddev php artisan test` — full suite green.
- [ ] `ddev exec vendor/bin/pint --test` — clean.
- [ ] `ddev npm run build` — green.

## Self-Review notes (author)

- **Spec coverage:** data model (Task 1), extractor + persistence (Task 2), type classification + labels (Task 3), controller aggregation + Resources tab (Task 4). ✓
- **No checks/bijection impact:** no `IssueCode`/`CheckCatalog` changes — pure listing feature. ✓
- **Dedup + ref_count:** extractor dedups per page (by URL); controller `count(distinct from_page_id)` → ref_count = distinct referencing pages. ✓
- **Internal size/status** filled from the matching crawled `crawl_page` via a per-result-page `whereIn` map (no N+1); external → null (Increment 2 probes). ✓
- **Light default load:** resources payload computed only for `?view=resources`. ✓
- **Label reuse:** resource type badges reuse `crawler.category.{javascript,css,font,image,other}` from Task 3. ✓
- **`sizeIssue` unchanged:** its `default` arm already covers js/css/font at the 2 MB tier (verified). ✓
