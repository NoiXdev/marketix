# Crawler — Resource view, Increment 2 — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Populate `status_code` + `size_bytes` for resources in the Resources view — internal from the crawled pages (normalised match), external via an SSRF-safe probe (HEAD `Content-Length`, GET fallback) — during `AggregateCrawlJob`; the controller then reads status/size straight from `crawl_resources`.

**Architecture:** New `ResourceProbe` service (mirrors `LinkStatusChecker`'s safety); `AggregateCrawlJob::checkResources()` fills the two new `crawl_resources` columns; `CrawlController` drops the Increment-1 `crawl_pages` join. No new checks, no new severity, no frontend change.

**Tech Stack:** Laravel 13 / PHP 8.3, Guzzle/Http client, PHPUnit, Pint.

**Spec:** `docs/superpowers/specs/2026-08-15-crawler-resources-increment2-design.md`

## Global Constraints

- **No new `IssueCode`/`CheckCatalog`/checks/severity** — bijection untouched.
- **SSRF:** only public/resolvable hosts probed (`UrlSafety::hostIsSafe`), identical to `LinkStatusChecker`.
- Runs in `AggregateCrawlJob` (`timeout=0`). Multi-tenant scoping unchanged.
- Run via DDEV. Backend gate `ddev php artisan test`; frontend gate `ddev npm run build` (no FE change); Pint clean `ddev exec vendor/bin/pint --test`.

---

## Task 1: `status_code`/`size_bytes` columns + `ResourceProbe`

**Files:** Create `database/migrations/2026_08_15_000007_add_status_size_to_crawl_resources.php`, `app/Crawler/ResourceProbe.php`, `tests/Unit/Crawler/ResourceProbeTest.php`.

**Interfaces:** Produces the two nullable columns and `ResourceProbe::probe(string $url): array{status: ?int, size: ?int}`. Task 2 consumes both.

- [ ] **Step 1: Migration.** Create `database/migrations/2026_08_15_000007_add_status_size_to_crawl_resources.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crawl_resources', function (Blueprint $table) {
            $table->unsignedSmallInteger('status_code')->nullable()->after('is_internal');
            $table->unsignedInteger('size_bytes')->nullable()->after('status_code');
        });
    }

    public function down(): void
    {
        Schema::table('crawl_resources', function (Blueprint $table) {
            $table->dropColumn(['status_code', 'size_bytes']);
        });
    }
};
```

- [ ] **Step 2: Write `ResourceProbe`.** Create `app/Crawler/ResourceProbe.php`:

```php
<?php

namespace App\Crawler;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Probes an external resource (image/JS/CSS/font the crawler does not fetch as a page)
 * for its HTTP status and byte size. Safety mirrors LinkStatusChecker: only public,
 * resolvable hosts are probed; anything else returns null/null (no SSRF, no false data).
 */
class ResourceProbe
{
    private const TIMEOUT = 8;

    private const CONNECT_TIMEOUT = 4;

    /** @return array{status: ?int, size: ?int} */
    public function probe(string $url): array
    {
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        $host = parse_url($url, PHP_URL_HOST);

        if (! in_array($scheme, ['http', 'https'], true) || ! is_string($host) || ! UrlSafety::hostIsSafe($host)) {
            return ['status' => null, 'size' => null];
        }

        try {
            $head = Http::timeout(self::TIMEOUT)->connectTimeout(self::CONNECT_TIMEOUT)->head($url);
            $status = $head->status();
            $size = $this->contentLength($head);

            // HEAD rejected, or no usable Content-Length → fall back to GET.
            if (in_array($status, [405, 501], true) || $size === null) {
                $get = Http::timeout(self::TIMEOUT)->connectTimeout(self::CONNECT_TIMEOUT)->get($url);
                $status = $get->status();
                $size = $this->contentLength($get) ?? strlen($get->body());
            }

            return ['status' => $status, 'size' => $size];
        } catch (\Throwable) {
            return ['status' => null, 'size' => null];
        }
    }

    private function contentLength(Response $response): ?int
    {
        $len = $response->header('Content-Length');

        return is_string($len) && $len !== '' && ctype_digit($len) ? (int) $len : null;
    }
}
```

- [ ] **Step 3: Write the unit test.** Create `tests/Unit/Crawler/ResourceProbeTest.php`:

```php
<?php

namespace Tests\Unit\Crawler;

use App\Crawler\ResourceProbe;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ResourceProbeTest extends TestCase
{
    public function test_head_with_content_length(): void
    {
        Http::fake(['*' => Http::response('', 200, ['Content-Length' => '2048'])]);
        $this->assertSame(['status' => 200, 'size' => 2048], (new ResourceProbe)->probe('https://cdn.test/a.js'));
    }

    public function test_head_405_falls_back_to_get(): void
    {
        Http::fakeSequence()
            ->push('', 405)
            ->push('', 200, ['Content-Length' => '999']);
        $this->assertSame(['status' => 200, 'size' => 999], (new ResourceProbe)->probe('https://cdn.test/b.css'));
    }

    public function test_no_content_length_uses_get_body_length(): void
    {
        Http::fakeSequence()
            ->push('', 200)                 // HEAD: 200 but no Content-Length
            ->push('abcd', 200);            // GET: no Content-Length → body length 4
        $this->assertSame(['status' => 200, 'size' => 4], (new ResourceProbe)->probe('https://cdn.test/c.png'));
    }

    public function test_unsafe_host_is_not_probed(): void
    {
        Http::fake();
        $this->assertSame(['status' => null, 'size' => null], (new ResourceProbe)->probe('http://127.0.0.1/x.js'));
        Http::assertNothingSent();
    }
}
```

- [ ] **Step 4: Run + Pint.**

Run: `ddev php artisan test --filter=ResourceProbeTest` → PASS.
Run: `ddev exec vendor/bin/pint --test app/Crawler/ResourceProbe.php database/migrations/2026_08_15_000007_add_status_size_to_crawl_resources.php tests/Unit/Crawler/ResourceProbeTest.php` → PASS.

- [ ] **Step 5: Commit.** `git add app/Crawler/ResourceProbe.php database/migrations/2026_08_15_000007_add_status_size_to_crawl_resources.php tests/Unit/Crawler/ResourceProbeTest.php && git commit -m "feat(crawler): ResourceProbe + status/size columns on crawl_resources"`

---

## Task 2: `AggregateCrawlJob::checkResources()`

**Files:** Modify `app/Jobs/AggregateCrawlJob.php`; Test `tests/Feature/Crawler/AggregateCrawlJobTest.php`.

**Interfaces:** Consumes `ResourceProbe` (Task 1) + the new columns. Fills `crawl_resources.status_code`/`size_bytes` per URL. Task 3's controller reads them.

- [ ] **Step 1: Add the probe cap + wire the call.** In `app/Jobs/AggregateCrawlJob.php`, add near `private const MAX_LINK_PROBES = 2000;`:

```php
    /** Safety cap on how many external resources we probe per crawl. */
    private const MAX_RESOURCE_PROBES = 2000;
```

  In `handle()`, right after `$brokenPageIds = $this->checkBrokenLinks($norm);`, add:

```php
        $this->checkResources($norm);
```

  Add `use App\Crawler\ResourceProbe;` at the top.

- [ ] **Step 2: Add `checkResources()`.** Add this method next to `checkBrokenLinks()`:

```php
    /**
     * Fill status_code + size_bytes on every crawl_resources row. Internal resources
     * take the status/size of the crawled page they resolve to (normalised match);
     * external resources are probed (SSRF-safe, deduped, capped). Internal resources
     * that were not crawled, and probes over the cap, are left null.
     *
     * @param  callable(?string): string  $norm
     */
    protected function checkResources(callable $norm): void
    {
        $meta = [];
        foreach ($this->crawl->pages()->select(['url', 'final_url', 'status_code', 'size_bytes'])->cursor() as $p) {
            $m = ['status' => $p->status_code, 'size' => $p->size_bytes];
            $meta[$norm($p->url)] = $m;
            if ($norm($p->final_url) !== '') {
                $meta[$norm($p->final_url)] = $m;
            }
        }

        $probe = app(ResourceProbe::class);
        $probes = 0;

        foreach ($this->crawl->resources()->select(['url', 'is_internal'])->distinct()->get() as $res) {
            $key = $norm($res->url);

            if ($res->is_internal && isset($meta[$key])) {
                $status = $meta[$key]['status'];
                $size = $meta[$key]['size'];
            } elseif (! $res->is_internal && $probes < self::MAX_RESOURCE_PROBES) {
                ['status' => $status, 'size' => $size] = $probe->probe($res->url);
                $probes++;
            } else {
                continue; // internal-not-crawled, or over cap → leave null
            }

            if ($status !== null || $size !== null) {
                $this->crawl->resources()->where('url', $res->url)->update([
                    'status_code' => $status,
                    'size_bytes' => $size,
                ]);
            }
        }

        if ($probes >= self::MAX_RESOURCE_PROBES) {
            Log::warning('Crawl resource check hit the probe cap; some external resources were not probed.', [
                'crawl_id' => $this->crawl->id,
                'cap' => self::MAX_RESOURCE_PROBES,
            ]);
        }
    }
```

- [ ] **Step 3: Add the feature test.** In `tests/Feature/Crawler/AggregateCrawlJobTest.php`, add (ensure `use Illuminate\Support\Facades\Http;` and `use App\Models\CrawlResource;` are imported):

```php
    public function test_checkresources_fills_internal_from_pages_and_probes_external(): void
    {
        Http::fake(['cdn.test/*' => Http::response('', 200, ['Content-Length' => '5000'])]);

        $crawl = Crawl::factory()->create();
        $home = CrawlPage::factory()->for($crawl)->create(['url' => 'https://x.test/', 'issues' => []]);
        // A crawled CSS page (internal resource resolves here — note trailing-slash normalisation).
        CrawlPage::factory()->for($crawl)->create(['url' => 'https://x.test/app.css', 'status_code' => 200, 'size_bytes' => 4096, 'content_category' => 'css', 'issues' => []]);

        $internal = CrawlResource::factory()->create(['crawl_id' => $crawl->id, 'from_page_id' => $home->id, 'url' => 'https://x.test/app.css/', 'type' => 'css', 'is_internal' => true]);
        $external = CrawlResource::factory()->create(['crawl_id' => $crawl->id, 'from_page_id' => $home->id, 'url' => 'https://cdn.test/lib.js', 'type' => 'javascript', 'is_internal' => false]);
        $uncrawled = CrawlResource::factory()->create(['crawl_id' => $crawl->id, 'from_page_id' => $home->id, 'url' => 'https://x.test/missing.css', 'type' => 'css', 'is_internal' => true]);

        (new AggregateCrawlJob($crawl))->handle(app(SitemapReader::class));

        $this->assertSame(200, $internal->refresh()->status_code);
        $this->assertSame(4096, $internal->size_bytes); // matched via trailing-slash-normalised URL
        $this->assertSame(200, $external->refresh()->status_code);
        $this->assertSame(5000, $external->size_bytes);
        $this->assertNull($uncrawled->refresh()->status_code); // internal but never crawled
    }
```

- [ ] **Step 4: Run + Pint.**

Run: `ddev php artisan test --filter=AggregateCrawlJobTest` → PASS (existing cases + the new one).
Run: `ddev exec vendor/bin/pint --test app/Jobs/AggregateCrawlJob.php tests/Feature/Crawler/AggregateCrawlJobTest.php` → PASS.

- [ ] **Step 5: Commit.** `git add app/Jobs/AggregateCrawlJob.php tests/Feature/Crawler/AggregateCrawlJobTest.php && git commit -m "feat(crawler): checkResources fills internal size/status + probes external"`

---

## Task 3: controller reads status/size from `crawl_resources`

**Files:** Modify `app/Http/Controllers/CrawlController.php`; Test `tests/Feature/Crawler/CrawlControllerTest.php`.

**Interfaces:** Consumes the populated `crawl_resources.status_code`/`size_bytes` (Task 2).

- [ ] **Step 1: Read status/size from the grouped query.** In `app/Http/Controllers/CrawlController.php::show()`'s `?view=resources` block, replace the grouped-query + `crawl_pages` `whereIn` meta join with a query that selects the two columns directly:

```php
            $rq = $model->resources()
                ->selectRaw('url, min(type) as type, max(is_internal) as is_internal, count(distinct from_page_id) as ref_count, min(status_code) as status_code, min(size_bytes) as size_bytes')
                ->groupBy('url');
            if (is_string($resourceType) && $resourceType !== '') {
                $rq->where('type', $resourceType);
            }
            $paginator = $rq->orderByDesc('ref_count')->paginate(50)->withQueryString();
            $paginator->getCollection()->transform(fn ($row) => [
                'url' => $row->url,
                'type' => $row->type,
                'is_internal' => (bool) $row->is_internal,
                'ref_count' => (int) $row->ref_count,
                'status_code' => $row->status_code !== null ? (int) $row->status_code : null,
                'size_bytes' => $row->size_bytes !== null ? (int) $row->size_bytes : null,
            ]);
            $resources = $paginator;
```

  (Delete the Increment-1 `$meta = $model->pages()->whereIn('url', …)…` block entirely; the `resourceSummary` block above it is unchanged.)

- [ ] **Step 2: Update the Increment-1 controller test.** In `tests/Feature/Crawler/CrawlControllerTest.php`, `test_resources_view_lists_grouped_resources` previously relied on the `crawl_pages` join to supply the internal resource's size/status. Update it so the internal resource's size/status come from the `crawl_resources` rows (seed the columns on the `CrawlResource` factory rows), keeping the same asserted intent:

```php
    public function test_resources_view_lists_grouped_resources(): void
    {
        [$user, $project] = $this->member();
        $crawl = Crawl::factory()->for($project)->create();
        $p1 = CrawlPage::factory()->for($crawl)->create(['url' => 'https://example.com/a']);
        $p2 = CrawlPage::factory()->for($crawl)->create(['url' => 'https://example.com/b']);
        // Internal CSS referenced by BOTH pages, with status/size stored on the resource rows.
        \App\Models\CrawlResource::factory()->create(['crawl_id' => $crawl->id, 'from_page_id' => $p1->id, 'url' => 'https://example.com/app.css', 'type' => 'css', 'is_internal' => true, 'status_code' => 200, 'size_bytes' => 4096]);
        \App\Models\CrawlResource::factory()->create(['crawl_id' => $crawl->id, 'from_page_id' => $p2->id, 'url' => 'https://example.com/app.css', 'type' => 'css', 'is_internal' => true, 'status_code' => 200, 'size_bytes' => 4096]);
        // External JS referenced once, status/size stored (as the probe would).
        \App\Models\CrawlResource::factory()->create(['crawl_id' => $crawl->id, 'from_page_id' => $p1->id, 'url' => 'https://cdn.test/lib.js', 'type' => 'javascript', 'is_internal' => false, 'status_code' => 200, 'size_bytes' => 12000]);

        $this->actingAs($user)
            ->get(route('app.project.crawls.show', ['project' => $project->id, 'crawl' => $crawl->id, 'view' => 'resources']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('filters.view', 'resources')
                ->where('resources.data', fn ($rows) => collect($rows)->firstWhere('url', 'https://example.com/app.css')['ref_count'] === 2
                    && collect($rows)->firstWhere('url', 'https://example.com/app.css')['size_bytes'] === 4096
                    && collect($rows)->firstWhere('url', 'https://cdn.test/lib.js')['size_bytes'] === 12000)
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

  (Adapt the `AssertableInertia` closure form to the installed version if needed — the intent: app.css ref_count 2 + size 4096, the external js size 12000, resourceSummary.css 1, and the type filter narrows to the js.)

- [ ] **Step 3: Run tests + build + Pint.**

Run: `ddev php artisan test --filter=CrawlControllerTest` → PASS.
Run: `ddev npm run build` → green (no FE change; the Size/Status columns now show real values, incl. external).
Run: `ddev exec vendor/bin/pint --test app/Http/Controllers/CrawlController.php tests/Feature/Crawler/CrawlControllerTest.php` → PASS.

- [ ] **Step 4: Commit.** `git add app/Http/Controllers/CrawlController.php tests/Feature/Crawler/CrawlControllerTest.php && git commit -m "feat(crawler): resources view reads status/size from crawl_resources"`

---

## Final verification (whole plan)

- [ ] `ddev php artisan test` — full suite green.
- [ ] `ddev exec vendor/bin/pint --test` — clean.
- [ ] `ddev npm run build` — green.

## Self-Review notes (author)

- **Spec coverage:** columns + `ResourceProbe` (Task 1), `checkResources` internal-map + external-probe + cap (Task 2), controller read-from-`crawl_resources` dropping the join (Task 3). ✓
- **SSRF:** `ResourceProbe` gates on `UrlSafety::hostIsSafe` before any HTTP; unsafe-host test asserts `Http::assertNothingSent`. ✓
- **Normalise-minor fixed:** internal resources match crawled pages via the aggregate's `$norm` (trailing-slash trim) — the feature test proves a trailing-slash URL still matches. ✓
- **No bijection/severity/frontend impact.** `min(status_code)`/`min(size_bytes)` are safe strict-`GROUP BY` aggregates (consistent per URL). ✓
- **Idempotent + capped:** distinct pass dedupes; external probes bounded by `MAX_RESOURCE_PROBES`; failures/over-cap leave null. ✓
