# Crawler Phase 6 — Canonicals / Pagination — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Activate all 21 planned `canonicals` + `pagination` checks — a per-page `CanonicalAnalyzer` and `PaginationAnalyzer`, plus 6 cross-page checks in `AggregateCrawlJob`.

**Architecture:** Two new per-page analyzers emit the per-page issues; `PaginationAnalyzer` also stores resolved `pagination_next`/`pagination_prev`. `AggregateCrawlJob` builds a URL→{status,indexable,next,prev} map and evaluates the 6 cross-page checks. No new severity, no frontend/lang.

**Tech Stack:** Laravel 13 / PHP 8.3, Symfony DomCrawler, PHPUnit, Pint.

**Spec:** `docs/superpowers/specs/2026-08-15-crawler-canonicals-pagination-phase6-design.md`

## Global Constraints

- **Bijection stays exact:** active catalogue codes === `IssueCode` cases. This phase adds 21 `IssueCode` cases and flips 21 catalogue entries planned→active, so both grow **71 → 92**.
- **`IssueCode::category()` and `::severity()` are exhaustive `match`** (no default arm). The 21 new cases get an arm in both. Categories: 11 → `Canonicals`, 10 → `Pagination`. Severities: `error` → `pagination_loop`; `warning` → `multiple_canonical`, `multiple_conflicting_canonical`, `non_indexable_canonical`, `canonical_invalid_attribute`, `canonical_outside_head`, `multiple_pagination_urls`, `pagination_url_not_in_anchor`, `pagination_non_200`, `pagination_unlinked`, `pagination_non_indexable`, `pagination_sequence_error`; `notice` → `has_canonical`, `canonical_self_referencing`, `missing_canonical`, `canonical_is_relative`, `canonical_not_linked`, `canonical_fragment_url`, `has_pagination`, `pagination_first_page`, `paginated_2plus`. These match the catalogue and the generalized severity-match test.
- **The 21 lang labels already exist** (Phase 0) — no rename, no lang change.
- **No new crawl-time network requests.** Cross-page checks reuse already-crawled page data.
- **Commands via DDEV.** Backend gate `ddev php artisan test`; frontend gate `ddev npm run build` (no change expected); Pint clean `ddev exec vendor/bin/pint --test`.

---

## File Structure

**Create**
- `app/Crawler/Analyzers/CanonicalAnalyzer.php`
- `app/Crawler/Analyzers/PaginationAnalyzer.php`
- `database/migrations/2026_08_15_000004_add_pagination_urls_to_crawl_pages.php`
- `tests/Unit/Crawler/CanonicalAnalyzerTest.php`
- `tests/Unit/Crawler/PaginationAnalyzerTest.php`

**Modify**
- `app/Crawler/IssueCode.php`, `app/Crawler/CheckCatalog.php`, `app/Crawler/PageAnalyzer.php`, `app/Jobs/AggregateCrawlJob.php`, `tests/Unit/Crawler/CheckCatalogTest.php`, `tests/Feature/Crawler/AggregateCrawlJobTest.php`, `tests/Feature/Crawler/CrawlControllerTest.php`.

---

## Task 1: IssueCode cases + catalogue flip (canonicals/pagination)

**Files:** Modify `app/Crawler/IssueCode.php`, `app/Crawler/CheckCatalog.php`; Test `tests/Unit/Crawler/CheckCatalogTest.php`.

**Interfaces:** Produces 21 new `IssueCode` cases. Tasks 2-4 emit these.

- [ ] **Step 1: Add the 21 cases.** In `app/Crawler/IssueCode.php`, after the h1/h2 block, add:

```php
    // canonicals
    case HasCanonical = 'has_canonical';
    case CanonicalSelfReferencing = 'canonical_self_referencing';
    case MissingCanonical = 'missing_canonical';
    case MultipleCanonical = 'multiple_canonical';
    case MultipleConflictingCanonical = 'multiple_conflicting_canonical';
    case NonIndexableCanonical = 'non_indexable_canonical';
    case CanonicalIsRelative = 'canonical_is_relative';
    case CanonicalNotLinked = 'canonical_not_linked';
    case CanonicalInvalidAttribute = 'canonical_invalid_attribute';
    case CanonicalFragmentUrl = 'canonical_fragment_url';
    case CanonicalOutsideHead = 'canonical_outside_head';
    // pagination
    case HasPagination = 'has_pagination';
    case PaginationFirstPage = 'pagination_first_page';
    case Paginated2plus = 'paginated_2plus';
    case PaginationUrlNotInAnchor = 'pagination_url_not_in_anchor';
    case PaginationNon200 = 'pagination_non_200';
    case PaginationUnlinked = 'pagination_unlinked';
    case PaginationNonIndexable = 'pagination_non_indexable';
    case MultiplePaginationUrls = 'multiple_pagination_urls';
    case PaginationLoop = 'pagination_loop';
    case PaginationSequenceError = 'pagination_sequence_error';
```

- [ ] **Step 2: `severity()` arms.** Add to the `'error'` arm: `self::PaginationLoop`. Add to `'warning'`: `self::MultipleCanonical, self::MultipleConflictingCanonical, self::NonIndexableCanonical, self::CanonicalInvalidAttribute, self::CanonicalOutsideHead, self::MultiplePaginationUrls, self::PaginationUrlNotInAnchor, self::PaginationNon200, self::PaginationUnlinked, self::PaginationNonIndexable, self::PaginationSequenceError`. Add to `'notice'`: `self::HasCanonical, self::CanonicalSelfReferencing, self::MissingCanonical, self::CanonicalIsRelative, self::CanonicalNotLinked, self::CanonicalFragmentUrl, self::HasPagination, self::PaginationFirstPage, self::Paginated2plus`. (Extend existing arms; no default.)

- [ ] **Step 3: `category()` arms.** Extend the existing `IssueCategory::Canonicals` arm (currently `self::CanonicalMismatch`) with the 11 canonical cases. Add a new arm: the 10 pagination cases `=> IssueCategory::Pagination,`.

- [ ] **Step 4: Flip the 21 catalogue entries.** In `app/Crawler/CheckCatalog.php`, change `'status' => self::P` → `'status' => self::A` for all 11 planned `canonicals` codes and all 10 `pagination` codes. (`canonical_mismatch` is already active — leave it.)

- [ ] **Step 5: Update `CheckCatalogTest`.** Extend `test_active_codes_for_category_filters`:

```php
        $this->assertCount(12, CheckCatalog::activeCodesForCategory('canonicals'));
        $this->assertCount(10, CheckCatalog::activeCodesForCategory('pagination'));
```

(The dynamic bijection test passes at 92; the generalized severity-match test covers the new codes.)

- [ ] **Step 6: Run + Pint.**

Run: `ddev php artisan test --filter='CheckCatalogTest|IssueCodeTest'` → PASS (no `UnhandledMatchError`; counts; bijection 92; severity-match).
Run: `ddev exec vendor/bin/pint --test app/Crawler/IssueCode.php app/Crawler/CheckCatalog.php` → PASS.

- [ ] **Step 7: Commit.** `git add app/Crawler/IssueCode.php app/Crawler/CheckCatalog.php tests/Unit/Crawler/CheckCatalogTest.php && git commit -m "feat(crawler): activate canonicals/pagination catalogue checks + issue codes"`

---

## Task 2: `CanonicalAnalyzer` (per-page canonical checks)

**Files:** Create `app/Crawler/Analyzers/CanonicalAnalyzer.php`, `tests/Unit/Crawler/CanonicalAnalyzerTest.php`; Modify `app/Crawler/PageAnalyzer.php`.

**Interfaces:** Consumes the 9 per-page canonical `IssueCode` cases (Task 1). `MissingCanonical`, `HasCanonical`, `CanonicalSelfReferencing`, `MultipleCanonical`, `MultipleConflictingCanonical`, `CanonicalIsRelative`, `CanonicalFragmentUrl`, `CanonicalOutsideHead`, `CanonicalInvalidAttribute`. (The cross-page `NonIndexableCanonical`/`CanonicalNotLinked` are Task 4.)

- [ ] **Step 1: Write `CanonicalAnalyzer`.** Create `app/Crawler/Analyzers/CanonicalAnalyzer.php`:

```php
<?php

namespace App\Crawler\Analyzers;

use App\Crawler\AnalyzerResult;
use App\Crawler\IssueCode;
use App\Crawler\PageContext;
use Symfony\Component\DomCrawler\Crawler;

class CanonicalAnalyzer implements Analyzer
{
    public function analyze(Crawler $dom, PageContext $ctx): AnalyzerResult
    {
        $r = new AnalyzerResult;

        $nodes = $dom->filter('link[rel="canonical"]');
        $count = $nodes->count();

        if ($count === 0) {
            $r->issue(IssueCode::MissingCanonical);

            return $r;
        }

        $r->issue(IssueCode::HasCanonical);

        $hrefs = [];
        $hasInvalid = false;
        $nodes->each(function (Crawler $n) use (&$hrefs, &$hasInvalid) {
            $href = $n->attr('href');
            if ($href === null || trim($href) === '') {
                $hasInvalid = true;
            } else {
                $hrefs[] = trim($href);
            }
        });

        if ($hasInvalid) {
            $r->issue(IssueCode::CanonicalInvalidAttribute);
        }

        if ($count > 1) {
            $r->issue(IssueCode::MultipleCanonical);
            $distinct = array_unique(array_map(fn ($h) => $this->normPath($h), $hrefs));
            if (count($distinct) >= 2) {
                $r->issue(IssueCode::MultipleConflictingCanonical);
            }
        }

        $first = $hrefs[0] ?? null;
        if ($first !== null) {
            if (! $this->differs($first, $ctx->url)) {
                $r->issue(IssueCode::CanonicalSelfReferencing);
            }
            if (! preg_match('#^https?://#i', $first)) {
                $r->issue(IssueCode::CanonicalIsRelative);
            }
            if (str_contains($first, '#')) {
                $r->issue(IssueCode::CanonicalFragmentUrl);
            }
        }

        // Outside <head>: a canonical link exists that is not under <head>.
        $all = $dom->filterXPath('//link[@rel="canonical"]')->count();
        $inHead = $dom->filterXPath('//head//link[@rel="canonical"]')->count();
        if ($all > $inHead) {
            $r->issue(IssueCode::CanonicalOutsideHead);
        }

        return $r;
    }

    private function normPath(string $u): string
    {
        return rtrim(parse_url($u, PHP_URL_PATH) ?? '/', '/') ?: '/';
    }

    private function differs(string $canonical, string $url): bool
    {
        return $this->normPath($canonical) !== $this->normPath($url);
    }
}
```

- [ ] **Step 2: Register in `PageAnalyzer`.** In `app/Crawler/PageAnalyzer.php`, add `use App\Crawler\Analyzers\CanonicalAnalyzer;` and append `new CanonicalAnalyzer,` to the `analyzers()` array.

- [ ] **Step 3: Write the unit test.** Create `tests/Unit/Crawler/CanonicalAnalyzerTest.php`:

```php
<?php

namespace Tests\Unit\Crawler;

use App\Crawler\Analyzers\CanonicalAnalyzer;
use App\Crawler\PageContext;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DomCrawler\Crawler;

class CanonicalAnalyzerTest extends TestCase
{
    /** @return string[] */
    private function run(string $head, string $url = 'https://x.test/page', string $body = ''): array
    {
        $html = "<html><head>{$head}</head><body>{$body}</body></html>";
        $result = (new CanonicalAnalyzer)->analyze(new Crawler($html), new PageContext($url, 200, 'x.test'));

        return array_map(fn ($i) => $i->value, $result->issues);
    }

    public function test_missing_canonical(): void
    {
        $this->assertSame(['missing_canonical'], $this->run(''));
    }

    public function test_self_referencing_and_has(): void
    {
        $codes = $this->run('<link rel="canonical" href="https://x.test/page">');
        $this->assertContains('has_canonical', $codes);
        $this->assertContains('canonical_self_referencing', $codes);
    }

    public function test_multiple_and_conflicting(): void
    {
        $codes = $this->run('<link rel="canonical" href="https://x.test/a"><link rel="canonical" href="https://x.test/b">');
        $this->assertContains('multiple_canonical', $codes);
        $this->assertContains('multiple_conflicting_canonical', $codes);
    }

    public function test_relative_fragment_invalid(): void
    {
        $this->assertContains('canonical_is_relative', $this->run('<link rel="canonical" href="/page">'));
        $this->assertContains('canonical_fragment_url', $this->run('<link rel="canonical" href="https://x.test/page#a">'));
        $this->assertContains('canonical_invalid_attribute', $this->run('<link rel="canonical" href="">'));
    }

    public function test_outside_head(): void
    {
        $codes = $this->run('', 'https://x.test/page', '<link rel="canonical" href="https://x.test/page">');
        $this->assertContains('canonical_outside_head', $codes);
    }
}
```

- [ ] **Step 4: Run + Pint.**

Run: `ddev php artisan test --filter='CanonicalAnalyzerTest|PageAnalyzerTest'` → PASS.
Run: `ddev exec vendor/bin/pint --test app/Crawler/Analyzers/CanonicalAnalyzer.php app/Crawler/PageAnalyzer.php tests/Unit/Crawler/CanonicalAnalyzerTest.php` → PASS.

- [ ] **Step 5: Commit.** `git add app/Crawler/Analyzers/CanonicalAnalyzer.php app/Crawler/PageAnalyzer.php tests/Unit/Crawler/CanonicalAnalyzerTest.php && git commit -m "feat(crawler): CanonicalAnalyzer for per-page canonical checks"`

---

## Task 3: `PaginationAnalyzer` + pagination columns

**Files:** Create `app/Crawler/Analyzers/PaginationAnalyzer.php`, migration, `tests/Unit/Crawler/PaginationAnalyzerTest.php`; Modify `app/Crawler/PageAnalyzer.php`.

**Interfaces:** Consumes the 6 per-page pagination `IssueCode` cases (Task 1). Produces resolved `$data['pagination_next']`/`$data['pagination_prev']` (persisted to the new columns). Task 4's aggregate consumes these columns.

- [ ] **Step 1: Migration.** Create `database/migrations/2026_08_15_000004_add_pagination_urls_to_crawl_pages.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crawl_pages', function (Blueprint $table) {
            $table->text('pagination_next')->nullable()->after('h1');
            $table->text('pagination_prev')->nullable()->after('pagination_next');
        });
    }

    public function down(): void
    {
        Schema::table('crawl_pages', function (Blueprint $table) {
            $table->dropColumn(['pagination_next', 'pagination_prev']);
        });
    }
};
```

- [ ] **Step 2: Write `PaginationAnalyzer`.** Create `app/Crawler/Analyzers/PaginationAnalyzer.php`:

```php
<?php

namespace App\Crawler\Analyzers;

use App\Crawler\AnalyzerResult;
use App\Crawler\IssueCode;
use App\Crawler\PageContext;
use Symfony\Component\DomCrawler\Crawler;

class PaginationAnalyzer implements Analyzer
{
    public function analyze(Crawler $dom, PageContext $ctx): AnalyzerResult
    {
        $r = new AnalyzerResult;

        $nextNodes = $dom->filter('link[rel="next"]');
        $prevNodes = $dom->filter('link[rel="prev"]');

        $next = $this->firstResolved($nextNodes, $ctx->url);
        $prev = $this->firstResolved($prevNodes, $ctx->url);
        $r->add('pagination_next', $next);
        $r->add('pagination_prev', $prev);

        $hasNext = $nextNodes->count() > 0;
        $hasPrev = $prevNodes->count() > 0;
        if (! $hasNext && ! $hasPrev) {
            return $r;
        }

        $r->issue(IssueCode::HasPagination);
        if ($hasNext && ! $hasPrev) {
            $r->issue(IssueCode::PaginationFirstPage);
        }
        if ($hasPrev) {
            $r->issue(IssueCode::Paginated2plus);
        }
        if ($nextNodes->count() > 1 || $prevNodes->count() > 1) {
            $r->issue(IssueCode::MultiplePaginationUrls);
        }

        // Resolved anchor targets on the page.
        $anchors = [];
        $dom->filter('a[href]')->each(function (Crawler $a) use (&$anchors, $ctx) {
            $abs = $this->resolve(trim($a->attr('href') ?? ''), $ctx->url);
            if ($abs !== null) {
                $anchors[$this->norm($abs)] = true;
            }
        });
        foreach (array_filter([$next, $prev]) as $target) {
            if (! isset($anchors[$this->norm($target)])) {
                $r->issue(IssueCode::PaginationUrlNotInAnchor);
                break;
            }
        }

        // Self-loop.
        foreach (array_filter([$next, $prev]) as $target) {
            if ($this->norm($target) === $this->norm($ctx->url)) {
                $r->issue(IssueCode::PaginationLoop);
                break;
            }
        }

        return $r;
    }

    private function firstResolved(Crawler $nodes, string $base): ?string
    {
        if ($nodes->count() === 0) {
            return null;
        }
        $href = trim($nodes->first()->attr('href') ?? '');

        return $href === '' ? null : $this->resolve($href, $base);
    }

    private function resolve(string $href, string $base): ?string
    {
        if ($href === '' || str_starts_with($href, '#')) {
            return null;
        }
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

    private function norm(string $u): string
    {
        return rtrim($u, '/');
    }
}
```

- [ ] **Step 3: Register in `PageAnalyzer`.** Add `use App\Crawler\Analyzers\PaginationAnalyzer;` and append `new PaginationAnalyzer,` to the `analyzers()` array (after `CanonicalAnalyzer` from Task 2).

- [ ] **Step 4: Write the unit test.** Create `tests/Unit/Crawler/PaginationAnalyzerTest.php`:

```php
<?php

namespace Tests\Unit\Crawler;

use App\Crawler\Analyzers\PaginationAnalyzer;
use App\Crawler\PageContext;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DomCrawler\Crawler;

class PaginationAnalyzerTest extends TestCase
{
    private function analyze(string $head, string $body = '', string $url = 'https://x.test/page-2'): \App\Crawler\AnalyzerResult
    {
        $html = "<html><head>{$head}</head><body>{$body}</body></html>";

        return (new PaginationAnalyzer)->analyze(new Crawler($html), new PageContext($url, 200, 'x.test'));
    }

    /** @return string[] */
    private function codes(\App\Crawler\AnalyzerResult $r): array
    {
        return array_map(fn ($i) => $i->value, $r->issues);
    }

    public function test_none(): void
    {
        $r = $this->analyze('');
        $this->assertSame([], $r->issues);
        $this->assertNull($r->data['pagination_next']);
    }

    public function test_first_page_and_stores_next(): void
    {
        $r = $this->analyze('<link rel="next" href="https://x.test/page-2">', '<a href="https://x.test/page-2">next</a>', 'https://x.test/page-1');
        $codes = $this->codes($r);
        $this->assertContains('has_pagination', $codes);
        $this->assertContains('pagination_first_page', $codes);
        $this->assertSame('https://x.test/page-2', $r->data['pagination_next']);
    }

    public function test_paginated_2plus_and_multiple(): void
    {
        $codes = $this->codes($this->analyze('<link rel="prev" href="https://x.test/p1"><link rel="next" href="https://x.test/p3"><link rel="next" href="https://x.test/p4">', '<a href="https://x.test/p1">a</a><a href="https://x.test/p3">b</a><a href="https://x.test/p4">c</a>'));
        $this->assertContains('paginated_2plus', $codes);
        $this->assertContains('multiple_pagination_urls', $codes);
    }

    public function test_url_not_in_anchor(): void
    {
        // next link present but no matching <a>.
        $codes = $this->codes($this->analyze('<link rel="next" href="https://x.test/page-3">'));
        $this->assertContains('pagination_url_not_in_anchor', $codes);
    }

    public function test_loop(): void
    {
        $codes = $this->codes($this->analyze('<link rel="next" href="https://x.test/page-2">', '<a href="https://x.test/page-2">a</a>', 'https://x.test/page-2'));
        $this->assertContains('pagination_loop', $codes);
    }
}
```

- [ ] **Step 5: Run + Pint.**

Run: `ddev php artisan test --filter='PaginationAnalyzerTest|PageAnalyzerTest'` → PASS (RefreshDatabase not needed for the unit test; the migration is exercised in Task 4).
Run: `ddev exec vendor/bin/pint --test app/Crawler/Analyzers/PaginationAnalyzer.php app/Crawler/PageAnalyzer.php database/migrations/2026_08_15_000004_add_pagination_urls_to_crawl_pages.php tests/Unit/Crawler/PaginationAnalyzerTest.php` → PASS.

- [ ] **Step 6: Commit.** `git add app/Crawler/Analyzers/PaginationAnalyzer.php app/Crawler/PageAnalyzer.php database/migrations/2026_08_15_000004_add_pagination_urls_to_crawl_pages.php tests/Unit/Crawler/PaginationAnalyzerTest.php && git commit -m "feat(crawler): PaginationAnalyzer + pagination_next/prev columns"`

---

## Task 4: cross-page canonical/pagination checks (aggregate)

**Files:** Modify `app/Jobs/AggregateCrawlJob.php`; Test `tests/Feature/Crawler/AggregateCrawlJobTest.php`, `tests/Feature/Crawler/CrawlControllerTest.php`.

**Interfaces:** Consumes the `canonical` / `pagination_next` / `pagination_prev` / `status_code` / `is_indexable` columns and the 6 cross-page `IssueCode` cases (Task 1).

- [ ] **Step 1: Extend the identities select + build the URL map.** In `app/Jobs/AggregateCrawlJob.php::handle()`:
  - Add `'canonical', 'pagination_next', 'pagination_prev', 'status_code', 'is_indexable'` to the `$identities` `->select([...])`.
  - After `$pageIdByUrl` is built, add a `$pageByUrl` map:

```php
        $pageByUrl = [];
        foreach ($identities as $p) {
            $entry = [
                'status' => $p->status_code,
                'indexable' => (bool) $p->is_indexable,
                'next' => $p->pagination_next !== null ? $norm($p->pagination_next) : null,
                'prev' => $p->pagination_prev !== null ? $norm($p->pagination_prev) : null,
            ];
            $pageByUrl[$norm($p->url)] = $entry;
            if ($norm($p->final_url) !== '') {
                $pageByUrl[$norm($p->final_url)] = $entry;
            }
        }
```

- [ ] **Step 2: Thread `$pageByUrl` into the chunk closure and evaluate the checks.** Add `$pageByUrl` to the `chunkById(500, function (Collection $pages) use (...))` `use (...)` list. After the existing duplicate blocks in the per-page loop, add:

```php
                // Cross-page canonical checks.
                if ($page->canonical !== null && trim($page->canonical) !== '') {
                    $canon = $this->resolveUrl($page->canonical, $page->url);
                    if ($canon !== null) {
                        $key = $norm($canon);
                        if (isset($pageByUrl[$key])) {
                            if ($pageByUrl[$key]['indexable'] === false) {
                                $issues[IssueCode::NonIndexableCanonical->value] = true;
                            }
                        } else {
                            $issues[IssueCode::CanonicalNotLinked->value] = true;
                        }
                    }
                }

                // Cross-page pagination checks (next/prev are stored pre-resolved).
                foreach (array_filter([$page->pagination_next, $page->pagination_prev]) as $target) {
                    $key = $norm($target);
                    if (isset($pageByUrl[$key])) {
                        if ($pageByUrl[$key]['status'] !== null && $pageByUrl[$key]['status'] !== 200) {
                            $issues[IssueCode::PaginationNon200->value] = true;
                        }
                        if ($pageByUrl[$key]['indexable'] === false) {
                            $issues[IssueCode::PaginationNonIndexable->value] = true;
                        }
                    } else {
                        $issues[IssueCode::PaginationUnlinked->value] = true;
                    }
                }
                if ($page->pagination_next !== null) {
                    $nextKey = $norm($page->pagination_next);
                    if (isset($pageByUrl[$nextKey]) && $pageByUrl[$nextKey]['prev'] !== $norm($page->url)) {
                        $issues[IssueCode::PaginationSequenceError->value] = true;
                    }
                }
```

- [ ] **Step 3: Add the `resolveUrl` helper.** Add this private method to `AggregateCrawlJob` (mirrors `LinkExtractor::resolve`):

```php
    private function resolveUrl(string $href, string $base): ?string
    {
        $href = trim($href);
        if ($href === '' || str_starts_with($href, '#')) {
            return null;
        }
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
```

- [ ] **Step 4: Add the aggregate test.** In `tests/Feature/Crawler/AggregateCrawlJobTest.php`, add:

```php
    public function test_flags_cross_page_canonical_and_pagination_issues(): void
    {
        $crawl = Crawl::factory()->create();
        // Canonical → a crawled, non-indexable page.
        $noindex = CrawlPage::factory()->for($crawl)->create(['url' => 'https://x.test/noindex', 'is_indexable' => false, 'status_code' => 200, 'issues' => []]);
        $c1 = CrawlPage::factory()->for($crawl)->create(['url' => 'https://x.test/c1', 'canonical' => 'https://x.test/noindex', 'issues' => []]);
        // Canonical → an uncrawled URL.
        $c2 = CrawlPage::factory()->for($crawl)->create(['url' => 'https://x.test/c2', 'canonical' => 'https://x.test/ghost', 'issues' => []]);
        // Pagination next → a 404 crawled page + broken reciprocity.
        $p404 = CrawlPage::factory()->for($crawl)->create(['url' => 'https://x.test/p2', 'status_code' => 404, 'is_indexable' => true, 'pagination_prev' => 'https://x.test/other', 'issues' => []]);
        $p1 = CrawlPage::factory()->for($crawl)->create(['url' => 'https://x.test/p1', 'status_code' => 200, 'pagination_next' => 'https://x.test/p2', 'issues' => []]);
        // Pagination next → uncrawled.
        $p3 = CrawlPage::factory()->for($crawl)->create(['url' => 'https://x.test/p3', 'pagination_next' => 'https://x.test/ghost2', 'issues' => []]);

        (new AggregateCrawlJob($crawl))->handle(app(SitemapReader::class));

        $this->assertContains('non_indexable_canonical', $c1->refresh()->issues);
        $this->assertContains('canonical_not_linked', $c2->refresh()->issues);
        $this->assertContains('pagination_non_200', $p1->refresh()->issues);
        $this->assertContains('pagination_sequence_error', $p1->issues); // p2.prev != p1
        $this->assertContains('pagination_unlinked', $p3->refresh()->issues);
    }
```

- [ ] **Step 5: Add the controller feature test.** In `tests/Feature/Crawler/CrawlControllerTest.php`, add:

```php
    public function test_canonicals_category_lists_a_page_with_a_canonical_issue(): void
    {
        [$user, $project] = $this->member();
        $crawl = Crawl::factory()->for($project)->create();
        CrawlPage::factory()->for($crawl)->create(['url' => 'https://example.com/x', 'issues' => ['multiple_canonical']]);
        CrawlPage::factory()->for($crawl)->create(['url' => 'https://example.com/clean', 'issues' => []]);

        $this->actingAs($user)
            ->get(route('app.project.crawls.show', ['project' => $project->id, 'crawl' => $crawl->id, 'group' => 'canonicals', 'issue' => 'multiple_canonical']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('pages.data', 1)
                ->where('pages.data.0.url', 'https://example.com/x')
            );
    }
```

- [ ] **Step 6: Run tests + build + Pint.**

Run: `ddev php artisan test --filter='AggregateCrawlJobTest|CrawlControllerTest'` → PASS.
Run: `ddev npm run build` → green (no frontend change).
Run: `ddev exec vendor/bin/pint --test app/Jobs/AggregateCrawlJob.php tests/Feature/Crawler/AggregateCrawlJobTest.php tests/Feature/Crawler/CrawlControllerTest.php` → PASS.

- [ ] **Step 7: Commit.** `git add app/Jobs/AggregateCrawlJob.php tests/Feature/Crawler/AggregateCrawlJobTest.php tests/Feature/Crawler/CrawlControllerTest.php && git commit -m "feat(crawler): cross-page canonical + pagination checks"`

---

## Final verification (whole plan)

- [ ] `ddev php artisan test` — full suite green.
- [ ] `ddev exec vendor/bin/pint --test` — clean.
- [ ] `ddev npm run build` — green.

## Self-Review notes (author)

- **Spec coverage:** 9 per-page canonical (Task 2), 6 per-page pagination + storage (Task 3), enum/catalogue (Task 1), 6 cross-page (Task 4). ✓
- **Type consistency:** the 21 snake_case strings match across enum values, catalogue codes, lang keys, and analyzer/aggregate emissions; severities in Task 1 match the catalogue (generalized severity-match test enforces it; verified all prior active codes already agree). ✓
- **Bijection:** +21 cases, +21 active → 92 = 92 (dynamic cardinality test). ✓
- **Shared file:** Tasks 2 & 3 both register in `PageAnalyzer::analyzers()` — sequential and additive (T3 appends after T2's line), no conflict. ✓
- **Resolution consistency:** pagination next/prev stored pre-resolved by the analyzer; canonical resolved in the aggregate via `resolveUrl`; both matched against crawled URLs with the aggregate's `$norm` (trailing-slash trim). ✓
- **Cross-page semantics:** uncrawled/external targets → `canonical_not_linked` / `pagination_unlinked` (expected, documented). Sequence-error compares the next target's stored `prev` to the page's own URL. ✓
