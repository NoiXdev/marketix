# Crawler Phase 1 — Security checks — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Flip the crawler's `security` issue category from planned to active — implement all 13 security checks so the Security tab on the crawl overview lists affected URLs and its per-check dropdown works.

**Architecture:** Response headers are already handed to `CrawlPageObserver::recordResponse()`; we persist a 6-key subset in a new `crawl_pages.security_headers` JSON column, route them (plus the page scheme) into `PageContext`, and add one `SecurityAnalyzer` covering header- and DOM-based checks. `wrong_content_type` runs in the observer (body-sniff) because mis-declared HTML skips the analyzer pipeline. A new `info` severity carries the positive `https_urls` signal: filterable but excluded from problem counts.

**Tech Stack:** Laravel 13 / PHP 8.3, spatie/crawler v9, Symfony DomCrawler, Inertia + React 19 + TypeScript, PHPUnit, Pint.

**Spec:** `docs/superpowers/specs/2026-08-15-crawler-security-phase1-design.md`

## Global Constraints

- **Bijection stays exact:** active catalogue codes === `IssueCode` cases. This phase adds 13 `IssueCode` cases and flips the 13 `security` catalogue entries planned→active, so both sets grow 22 → **35**.
- **`IssueCode::category()` and `::severity()` are exhaustive `match`** (no default arm). Every new case gets an arm in both — a missing arm is a compile-time/`UnhandledMatchError` signal.
- **New `info` severity** (`error | warning | notice | info`). `info` codes are filterable and per-code-counted, but excluded from the category problem badge, the overview summary tiles, and red/yellow colouring. The exclusion lives behind a single predicate `CheckCatalog::isProblemCode(string): bool`.
- **Every catalogue code has a lang label** in `lang/en/crawler.php` and `lang/de/crawler.php`. The 13 security labels already exist (added in Phase 0); do not rename them.
- **No new crawl-time network requests** — every check reads the response already fetched (headers + body).
- **Commands run via DDEV.** Backend gate: `ddev php artisan test` (crawler namespace, then full suite). Frontend gate: `ddev npm run build` (lint is broken — do not use it). Pint must be clean: `ddev exec vendor/bin/pint --test`.

---

## File Structure

**Create**
- `database/migrations/2026_08_15_000001_add_security_headers_to_crawl_pages.php` — nullable `json security_headers`.
- `app/Crawler/Analyzers/SecurityAnalyzer.php` — the 12 header/DOM checks.
- `tests/Unit/Crawler/SecurityAnalyzerTest.php`.

**Modify**
- `app/Crawler/IssueCode.php` — 13 cases + `category()`/`severity()` arms.
- `app/Crawler/CheckCatalog.php` — flip 13 security entries to active; `https_urls` severity → `info`; add `isProblemCode()`.
- `app/Crawler/PageContext.php` — add `securityHeaders`, `scheme`.
- `app/Crawler/PageAnalyzer.php` — register `SecurityAnalyzer`.
- `app/Observers/CrawlPageObserver.php` — normalise + store headers; pass headers/scheme to `PageContext`; `wrong_content_type` sniff.
- `app/Models/CrawlPage.php` — `security_headers` array cast.
- `app/Http/Controllers/CrawlController.php` — exclude `info` codes from `perCategory`.
- `app/Jobs/AggregateCrawlJob.php` — exclude `info` codes from `$summary`.
- `resources/js/Pages/Crawls/Show.tsx` — `CatalogCheck.severity` union gains `'info'`.
- `resources/js/Pages/Crawls/Page.tsx` — severity maps gain `info` (neutral).
- `lang/en/crawler.php`, `lang/de/crawler.php` — add `severity_info`.
- `tests/Unit/Crawler/CheckCatalogTest.php`, `tests/Feature/Crawler/CrawlControllerTest.php`.

---

## Task 1: `info` severity + catalogue flip + enum cases + count exclusion

**Files:**
- Modify: `app/Crawler/IssueCode.php`
- Modify: `app/Crawler/CheckCatalog.php`
- Modify: `app/Http/Controllers/CrawlController.php:110-122`
- Modify: `app/Jobs/AggregateCrawlJob.php:146-148`
- Test: `tests/Unit/Crawler/CheckCatalogTest.php`
- Test: `tests/Feature/Crawler/CrawlControllerTest.php`

**Interfaces:**
- Produces: 13 new `IssueCode` cases (see below); `CheckCatalog::isProblemCode(string $code): bool` (false only for `info`-severity catalogue codes, true for anything else incl. unknown). Task 3 emits these codes; Task 4 renders them.

- [ ] **Step 1: Add the 13 `IssueCode` cases.** In `app/Crawler/IssueCode.php`, after the `resources` block (after `OversizedResource`), add a `security` block:

```php
    // security
    case MissingCspHeader = 'missing_csp_header';
    case MissingXFrameOptions = 'missing_x_frame_options';
    case MissingXContentTypeOptions = 'missing_x_content_type_options';
    case MissingHstsHeader = 'missing_hsts_header';
    case UnsafeCrossOriginLinks = 'unsafe_cross_origin_links';
    case MissingReferrerPolicy = 'missing_referrer_policy';
    case HttpUrls = 'http_urls';
    case HttpsUrls = 'https_urls';
    case MixedContent = 'mixed_content';
    case FormUrlInsecure = 'form_url_insecure';
    case FormOnHttp = 'form_on_http';
    case ProtocolRelativeResourceLinks = 'protocol_relative_resource_links';
    case WrongContentType = 'wrong_content_type';
```

- [ ] **Step 2: Add `severity()` arms.** In `severity()`, extend the `match`: add `HttpsUrls` returning `'info'`, and slot the rest by severity (matching the check table). Replace the `match` body's arms so it reads:

```php
        return match ($this) {
            self::ServerError, self::ClientError, self::MissingTitle, self::MissingH1,
            self::OversizedResource => 'error',
            self::RedirectChain, self::MultipleH1, self::HeadingOrderSkip, self::Noindex,
            self::CanonicalMismatch, self::RobotsBlocked, self::DuplicateTitle,
            self::DuplicateMetaDescription, self::OrphanPage, self::MissingMetaDescription,
            self::LargeResource, self::BrokenLink,
            self::MissingXFrameOptions, self::MissingXContentTypeOptions, self::MissingHstsHeader,
            self::UnsafeCrossOriginLinks, self::HttpUrls, self::MixedContent,
            self::FormUrlInsecure, self::FormOnHttp, self::WrongContentType => 'warning',
            self::TitleTooLong, self::ThinContent, self::MissingAltText,
            self::MissingStructuredData, self::NotInSitemap,
            self::MissingCspHeader, self::MissingReferrerPolicy,
            self::ProtocolRelativeResourceLinks => 'notice',
            self::HttpsUrls => 'info',
        };
```

- [ ] **Step 3: Add `category()` arms.** In `category()`, add all 13 security cases to a `Security` arm. Insert before the `Other` arm:

```php
            self::MissingCspHeader, self::MissingXFrameOptions, self::MissingXContentTypeOptions,
            self::MissingHstsHeader, self::UnsafeCrossOriginLinks, self::MissingReferrerPolicy,
            self::HttpUrls, self::HttpsUrls, self::MixedContent, self::FormUrlInsecure,
            self::FormOnHttp, self::ProtocolRelativeResourceLinks, self::WrongContentType => IssueCategory::Security,
```

- [ ] **Step 4: Flip catalogue + set `https_urls` to info.** In `app/Crawler/CheckCatalog.php`, in the `// security` block (lines 22-34), change every `'status' => self::P` to `'status' => self::A`, and change the `https_urls` entry's `'severity' => 'notice'` to `'severity' => 'info'`. Leave all other severities as-is (they already match the check table).

- [ ] **Step 5: Add `isProblemCode()`.** In `CheckCatalog`, after `categoryOf()`:

```php
    /** True unless the code is an informational (`info`) signal that should not count as a problem. */
    public static function isProblemCode(string $code): bool
    {
        foreach (self::all() as $entry) {
            if ($entry['code'] === $code) {
                return $entry['severity'] !== 'info';
            }
        }

        return true;
    }
```

- [ ] **Step 6: Exclude `info` from the controller's category count.** In `app/Http/Controllers/CrawlController.php`, in the per-page loop (around line 112-118), gate the `seenCategories` write on `isProblemCode` (leave `perCode` counting all):

```php
            foreach (array_unique($issues ?? []) as $code) {
                $perCode[$code] = ($perCode[$code] ?? 0) + 1;
                $cat = CheckCatalog::categoryOf($code);
                if ($cat !== null && CheckCatalog::isProblemCode($code)) {
                    $seenCategories[$cat] = true;
                }
            }
```

- [ ] **Step 7: Exclude `info` from the overview summary.** In `app/Jobs/AggregateCrawlJob.php`, add `use App\Crawler\CheckCatalog;` and change the summary accumulation (lines 146-148):

```php
                foreach ($page->issues as $code) {
                    if (! CheckCatalog::isProblemCode($code)) {
                        continue;
                    }
                    $summary[$code] = ($summary[$code] ?? 0) + 1;
                }
```

- [ ] **Step 8: Update `CheckCatalogTest`.** In `tests/Unit/Crawler/CheckCatalogTest.php`:
  - In `test_every_entry_is_well_formed`, add `'info'` to the allowed severities: `['error', 'warning', 'notice', 'info']`.
  - Replace `test_active_codes_for_category_filters`'s security assertion. New body:

```php
    public function test_active_codes_for_category_filters(): void
    {
        $this->assertContains('missing_title', CheckCatalog::activeCodesForCategory('page_title'));
        $security = CheckCatalog::activeCodesForCategory('security');
        $this->assertContains('mixed_content', $security);
        $this->assertContains('https_urls', $security);
        $this->assertCount(13, $security);
    }
```

  - Add two tests:

```php
    public function test_bijection_cardinality_holds(): void
    {
        $this->assertCount(count(IssueCode::cases()), CheckCatalog::activeCodes());
    }

    public function test_catalogue_severity_matches_issue_code_for_security(): void
    {
        foreach (CheckCatalog::activeCodesForCategory('security') as $code) {
            $entry = collect(CheckCatalog::all())->firstWhere('code', $code);
            $this->assertSame(IssueCode::from($code)->severity(), $entry['severity'], $code);
        }
    }
```

- [ ] **Step 9: Add a controller feature test for `info` exclusion.** In `tests/Feature/Crawler/CrawlControllerTest.php`, add:

```php
    public function test_info_severity_code_is_filterable_but_not_counted_as_a_problem(): void
    {
        [$user, $project] = $this->member();
        $crawl = Crawl::factory()->for($project)->create();
        CrawlPage::factory()->for($crawl)->create(['url' => 'https://example.com/secure', 'issues' => ['https_urls']]);

        $this->actingAs($user)
            ->get(route('app.project.crawls.show', ['project' => $project->id, 'crawl' => $crawl->id]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                // https_urls does not inflate the security problem badge …
                ->where('catalog.security.count', 0)
                // … but is per-code counted for the dropdown.
                ->where('catalog.security.checks', fn ($checks) => collect($checks)
                    ->firstWhere('code', 'https_urls')['count'] === 1)
            );

        // …and is a working filter that lists the page.
        $this->actingAs($user)
            ->get(route('app.project.crawls.show', ['project' => $project->id, 'crawl' => $crawl->id, 'group' => 'security', 'issue' => 'https_urls']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('pages.data', 1)
                ->where('pages.data.0.url', 'https://example.com/secure')
            );
    }
```

- [ ] **Step 10: Run tests + Pint.**

Run: `ddev php artisan test --filter='CheckCatalogTest|CrawlControllerTest'`
Expected: PASS (incl. the 3 new/updated catalogue tests and the new controller test).
Run: `ddev exec vendor/bin/pint --test app/Crawler/IssueCode.php app/Crawler/CheckCatalog.php app/Http/Controllers/CrawlController.php app/Jobs/AggregateCrawlJob.php`
Expected: PASS.

- [ ] **Step 11: Commit.**

```bash
git add app/Crawler/IssueCode.php app/Crawler/CheckCatalog.php app/Http/Controllers/CrawlController.php app/Jobs/AggregateCrawlJob.php tests/Unit/Crawler/CheckCatalogTest.php tests/Feature/Crawler/CrawlControllerTest.php
git commit -m "feat(crawler): activate security catalogue + info severity model"
```

---

## Task 2: `security_headers` column + header/scheme routing + `wrong_content_type`

**Files:**
- Create: `database/migrations/2026_08_15_000001_add_security_headers_to_crawl_pages.php`
- Modify: `app/Models/CrawlPage.php:19-30`
- Modify: `app/Crawler/PageContext.php`
- Modify: `app/Observers/CrawlPageObserver.php:60-112`
- Test: `tests/Feature/Crawler/CrawlPageObserverSecurityTest.php` (create)

**Interfaces:**
- Consumes: `IssueCode::WrongContentType` (Task 1).
- Produces: `PageContext` with public `array $securityHeaders` (normalised lower-case header name → first value) and `string $scheme`; a persisted `crawl_pages.security_headers` JSON column. Task 3's `SecurityAnalyzer` reads `$ctx->securityHeaders` and `$ctx->scheme`.

- [ ] **Step 1: Write the migration.** Create `database/migrations/2026_08_15_000001_add_security_headers_to_crawl_pages.php`:

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
            $table->json('security_headers')->nullable()->after('issues');
        });
    }

    public function down(): void
    {
        Schema::table('crawl_pages', function (Blueprint $table) {
            $table->dropColumn('security_headers');
        });
    }
};
```

- [ ] **Step 2: Add the model cast.** In `app/Models/CrawlPage.php`, add to the `casts()` array: `'security_headers' => 'array',`. (Model uses `$guarded = ['id']`, so no `$fillable` change.)

- [ ] **Step 3: Extend `PageContext`.** Replace `app/Crawler/PageContext.php` constructor with:

```php
<?php

namespace App\Crawler;

class PageContext
{
    /**
     * @param  array<string, string>  $securityHeaders  normalised lower-case header name => first value
     */
    public function __construct(
        public string $url,
        public int $statusCode,
        public string $baseHost,
        public bool $robotsBlocked = false,
        public array $securityHeaders = [],
        public string $scheme = 'https',
    ) {}
}
```

- [ ] **Step 4: Capture headers + scheme + `wrong_content_type` in the observer.** In `app/Observers/CrawlPageObserver.php::recordResponse()`, make these edits.

  After `$contentType = ...;` (line 63), add header normalisation + subset extraction:

```php
        $lower = array_change_key_case($headers, CASE_LOWER);
        $securityHeaders = [];
        foreach (['content-security-policy', 'x-frame-options', 'x-content-type-options', 'strict-transport-security', 'referrer-policy', 'content-type'] as $key) {
            if (isset($lower[$key][0])) {
                $securityHeaders[$key] = $lower[$key][0];
            }
        }
```

  After `[$chain, $finalUrl] = $this->redirects($url, $headers);` (line 74), derive the scheme from the final URL (falls back to the requested URL, then https):

```php
        $scheme = strtolower(parse_url($finalUrl ?: $url, PHP_URL_SCHEME) ?: 'https');
```

  Immediately after computing `$size` (line 65) — i.e. before the `if ($status >= 500)` block — add the HTML sniff so it runs for both branches:

```php
        $sniffHtml = (bool) preg_match('#^\s*(<!doctype html|<html[\s>])#i', ltrim($body, "\xEF\xBB\xBF"));
        $declaredMedia = strtolower(trim(explode(';', (string) $contentType)[0]));
        $wrongContentType = $sniffHtml && ! in_array($declaredMedia, ['text/html', 'application/xhtml+xml'], true);
```

  In the `if ($category === ResourceClassifier::HTML)` branch, pass the new args into the `PageContext`:

```php
            $ctx = new PageContext($url, $status, $this->baseHost, false, $securityHeaders, $scheme);
```

  After the HTML/else branch (before the `$page = $this->crawl->pages()->create(...)` call), append the sniff issue:

```php
        if ($wrongContentType) {
            $issues[] = IssueCode::WrongContentType->value;
        }
```

  In the `create(array_merge($data, [ ... ]))` array, add:

```php
            'security_headers' => $securityHeaders ?: null,
```

- [ ] **Step 5: Write the observer test.** Create `tests/Feature/Crawler/CrawlPageObserverSecurityTest.php`:

```php
<?php

namespace Tests\Feature\Crawler;

use App\Crawler\PageAnalyzer;
use App\Models\Crawl;
use App\Models\Project;
use App\Observers\CrawlPageObserver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrawlPageObserverSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function observer(Crawl $crawl): CrawlPageObserver
    {
        return new CrawlPageObserver($crawl, new PageAnalyzer, 'example.com');
    }

    public function test_stores_security_headers_subset_with_lowercased_keys(): void
    {
        $crawl = Crawl::factory()->for(Project::factory())->create();
        $page = $this->observer($crawl)->recordResponse(
            'https://example.com/',
            200,
            ['Content-Type' => ['text/html'], 'Strict-Transport-Security' => ['max-age=63072000'], 'X-Random' => ['ignored']],
            '<!doctype html><html><head><title>t</title></head><body>x</body></html>',
            10.0,
        );

        $this->assertSame('max-age=63072000', $page->security_headers['strict-transport-security']);
        $this->assertArrayNotHasKey('x-random', $page->security_headers);
    }

    public function test_flags_html_served_as_wrong_content_type(): void
    {
        $crawl = Crawl::factory()->for(Project::factory())->create();
        $page = $this->observer($crawl)->recordResponse(
            'https://example.com/page',
            200,
            ['Content-Type' => ['text/plain']],
            "\xEF\xBB\xBF<!DOCTYPE html><html><body>hi</body></html>",
            10.0,
        );

        $this->assertContains('wrong_content_type', $page->issues);
    }

    public function test_does_not_flag_a_real_text_html_page(): void
    {
        $crawl = Crawl::factory()->for(Project::factory())->create();
        $page = $this->observer($crawl)->recordResponse(
            'https://example.com/ok',
            200,
            ['Content-Type' => ['text/html; charset=utf-8']],
            '<!doctype html><html><head><title>t</title></head><body>hi</body></html>',
            10.0,
        );

        $this->assertNotContains('wrong_content_type', $page->issues);
    }
}
```

- [ ] **Step 6: Migrate + run tests + Pint.**

Run: `ddev php artisan test --filter=CrawlPageObserverSecurityTest`
Expected: PASS (RefreshDatabase applies the new migration).
Run: `ddev exec vendor/bin/pint --test app/Observers/CrawlPageObserver.php app/Crawler/PageContext.php app/Models/CrawlPage.php database/migrations/2026_08_15_000001_add_security_headers_to_crawl_pages.php`
Expected: PASS.

- [ ] **Step 7: Commit.**

```bash
git add database/migrations/2026_08_15_000001_add_security_headers_to_crawl_pages.php app/Models/CrawlPage.php app/Crawler/PageContext.php app/Observers/CrawlPageObserver.php tests/Feature/Crawler/CrawlPageObserverSecurityTest.php
git commit -m "feat(crawler): capture response headers + wrong_content_type sniff"
```

---

## Task 3: `SecurityAnalyzer` (12 header/DOM checks)

**Files:**
- Create: `app/Crawler/Analyzers/SecurityAnalyzer.php`
- Modify: `app/Crawler/PageAnalyzer.php:17-27`
- Test: `tests/Unit/Crawler/SecurityAnalyzerTest.php`

**Interfaces:**
- Consumes: `PageContext{securityHeaders, scheme, baseHost}` (Task 2); the 12 non-`wrong_content_type` `IssueCode` security cases (Task 1).
- Produces: security `IssueCode`s in the merged `AnalyzerResult`. `PageAnalyzer` already dedups by code (each code emitted at most once per page).

- [ ] **Step 1: Write the analyzer.** Create `app/Crawler/Analyzers/SecurityAnalyzer.php`:

```php
<?php

namespace App\Crawler\Analyzers;

use App\Crawler\AnalyzerResult;
use App\Crawler\IssueCode;
use App\Crawler\PageContext;
use Symfony\Component\DomCrawler\Crawler;

class SecurityAnalyzer implements Analyzer
{
    /** Subresource selectors → the attribute holding the URL. */
    private const RESOURCE_SELECTORS = [
        'img[src]' => 'src',
        'script[src]' => 'src',
        'link[rel="stylesheet"][href]' => 'href',
        'iframe[src]' => 'src',
        'audio[src]' => 'src',
        'video[src]' => 'src',
        'source[src]' => 'src',
        'object[data]' => 'data',
    ];

    public function analyze(Crawler $dom, PageContext $ctx): AnalyzerResult
    {
        $r = new AnalyzerResult;
        $h = $ctx->securityHeaders;
        $isHttps = $ctx->scheme === 'https';

        // Scheme (https_urls is an informational `info` signal).
        $r->issue($isHttps ? IssueCode::HttpsUrls : IssueCode::HttpUrls);

        // Header checks.
        if ($isHttps && ! isset($h['strict-transport-security'])) {
            $r->issue(IssueCode::MissingHstsHeader);
        }
        if (! isset($h['content-security-policy'])) {
            $r->issue(IssueCode::MissingCspHeader);
        }
        if (! isset($h['x-content-type-options'])) {
            $r->issue(IssueCode::MissingXContentTypeOptions);
        }
        $cspHasFrameAncestors = stripos($h['content-security-policy'] ?? '', 'frame-ancestors') !== false;
        if (! isset($h['x-frame-options']) && ! $cspHasFrameAncestors) {
            $r->issue(IssueCode::MissingXFrameOptions);
        }
        if (! isset($h['referrer-policy']) && $dom->filter('meta[name="referrer"]')->count() === 0) {
            $r->issue(IssueCode::MissingReferrerPolicy);
        }

        // Subresources: mixed content + protocol-relative.
        foreach ($this->resourceUrls($dom) as $url) {
            if (str_starts_with($url, '//')) {
                $r->issue(IssueCode::ProtocolRelativeResourceLinks);
            }
            if ($isHttps && preg_match('#^http://#i', $url)) {
                $r->issue(IssueCode::MixedContent);
            }
        }

        // Unsafe cross-origin target=_blank links.
        $dom->filter('a[href][target="_blank"]')->each(function (Crawler $a) use ($r, $ctx) {
            $href = trim($a->attr('href') ?? '');
            if (! preg_match('#^https?://#i', $href)) {
                return; // relative → same origin
            }
            $host = parse_url($href, PHP_URL_HOST) ?? '';
            if ($host === '' || $host === $ctx->baseHost) {
                return;
            }
            $rel = strtolower($a->attr('rel') ?? '');
            if (! str_contains($rel, 'noopener') && ! str_contains($rel, 'noreferrer')) {
                $r->issue(IssueCode::UnsafeCrossOriginLinks);
            }
        });

        // Forms.
        if (! $isHttps && $dom->filter('form')->count() > 0) {
            $r->issue(IssueCode::FormOnHttp);
        }
        $dom->filter('form[action]')->each(function (Crawler $f) use ($r) {
            if (preg_match('#^http://#i', trim($f->attr('action') ?? ''))) {
                $r->issue(IssueCode::FormUrlInsecure);
            }
        });

        return $r;
    }

    /** @return string[] */
    private function resourceUrls(Crawler $dom): array
    {
        $urls = [];
        foreach (self::RESOURCE_SELECTORS as $selector => $attr) {
            $dom->filter($selector)->each(function (Crawler $n) use (&$urls, $attr) {
                $v = trim($n->attr($attr) ?? '');
                if ($v !== '') {
                    $urls[] = $v;
                }
            });
        }

        return $urls;
    }
}
```

- [ ] **Step 2: Register the analyzer.** In `app/Crawler/PageAnalyzer.php`, add `use App\Crawler\Analyzers\SecurityAnalyzer;` and add `new SecurityAnalyzer,` to the `analyzers()` array (append after `StructuredDataAnalyzer`).

- [ ] **Step 3: Write the analyzer test.** Create `tests/Unit/Crawler/SecurityAnalyzerTest.php`:

```php
<?php

namespace Tests\Unit\Crawler;

use App\Crawler\Analyzers\SecurityAnalyzer;
use App\Crawler\PageContext;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DomCrawler\Crawler;

class SecurityAnalyzerTest extends TestCase
{
    /** @param array<string,string> $headers @return string[] */
    private function run(string $html, array $headers = [], string $scheme = 'https', string $url = 'https://example.com/'): array
    {
        $ctx = new PageContext($url, 200, 'example.com', false, $headers, $scheme);
        $result = (new SecurityAnalyzer)->analyze(new Crawler($html), $ctx);

        return array_map(fn ($i) => $i->value, $result->issues);
    }

    public function test_secure_page_with_all_headers_emits_only_https_urls(): void
    {
        $headers = [
            'strict-transport-security' => 'max-age=1',
            'content-security-policy' => "default-src 'self'",
            'x-frame-options' => 'DENY',
            'x-content-type-options' => 'nosniff',
            'referrer-policy' => 'no-referrer',
        ];
        $codes = $this->run('<html><body>ok</body></html>', $headers);

        $this->assertSame(['https_urls'], $codes);
    }

    public function test_missing_headers_are_flagged(): void
    {
        $codes = $this->run('<html><body>ok</body></html>');

        $this->assertContains('missing_hsts_header', $codes);
        $this->assertContains('missing_csp_header', $codes);
        $this->assertContains('missing_x_content_type_options', $codes);
        $this->assertContains('missing_x_frame_options', $codes);
        $this->assertContains('missing_referrer_policy', $codes);
    }

    public function test_csp_frame_ancestors_satisfies_x_frame_options(): void
    {
        $codes = $this->run('<html><body>ok</body></html>', ['content-security-policy' => "frame-ancestors 'none'"]);

        $this->assertNotContains('missing_x_frame_options', $codes);
    }

    public function test_meta_referrer_satisfies_referrer_policy(): void
    {
        $codes = $this->run('<html><head><meta name="referrer" content="no-referrer"></head><body>ok</body></html>');

        $this->assertNotContains('missing_referrer_policy', $codes);
    }

    public function test_http_page_emits_http_urls_not_https(): void
    {
        $codes = $this->run('<html><body>ok</body></html>', [], 'http', 'http://example.com/');

        $this->assertContains('http_urls', $codes);
        $this->assertNotContains('https_urls', $codes);
        $this->assertNotContains('missing_hsts_header', $codes); // HSTS only meaningful on https
    }

    public function test_mixed_content_and_protocol_relative_resources(): void
    {
        $html = '<html><body><img src="http://cdn.test/a.png"><script src="//cdn.test/b.js"></script></body></html>';
        $codes = $this->run($html);

        $this->assertContains('mixed_content', $codes);
        $this->assertContains('protocol_relative_resource_links', $codes);
    }

    public function test_unsafe_cross_origin_link(): void
    {
        $unsafe = $this->run('<html><body><a href="https://other.test/x" target="_blank">x</a></body></html>');
        $this->assertContains('unsafe_cross_origin_links', $unsafe);

        $safe = $this->run('<html><body><a href="https://other.test/x" target="_blank" rel="noopener">x</a></body></html>');
        $this->assertNotContains('unsafe_cross_origin_links', $safe);

        $sameHost = $this->run('<html><body><a href="https://example.com/x" target="_blank">x</a></body></html>');
        $this->assertNotContains('unsafe_cross_origin_links', $sameHost);
    }

    public function test_insecure_forms(): void
    {
        $onHttp = $this->run('<html><body><form action="/submit"></form></body></html>', [], 'http', 'http://example.com/');
        $this->assertContains('form_on_http', $onHttp);

        $insecureAction = $this->run('<html><body><form action="http://example.com/submit"></form></body></html>');
        $this->assertContains('form_url_insecure', $insecureAction);
    }
}
```

- [ ] **Step 4: Run tests + Pint.**

Run: `ddev php artisan test --filter=SecurityAnalyzerTest`
Expected: PASS.
Run: `ddev exec vendor/bin/pint --test app/Crawler/Analyzers/SecurityAnalyzer.php app/Crawler/PageAnalyzer.php`
Expected: PASS.

- [ ] **Step 5: Commit.**

```bash
git add app/Crawler/Analyzers/SecurityAnalyzer.php app/Crawler/PageAnalyzer.php tests/Unit/Crawler/SecurityAnalyzerTest.php
git commit -m "feat(crawler): SecurityAnalyzer for header + DOM security checks"
```

---

## Task 4: Frontend `info` rendering + end-to-end feature test

**Files:**
- Modify: `resources/js/Pages/Crawls/Show.tsx:28-33`
- Modify: `resources/js/Pages/Crawls/Page.tsx:34,41-51,394,417`
- Modify: `lang/en/crawler.php:37`, `lang/de/crawler.php:37`
- Test: `tests/Feature/Crawler/CrawlControllerTest.php`

**Interfaces:**
- Consumes: `info` severity emitted through the catalogue (Task 1) and the `SecurityAnalyzer` (Task 3).

- [ ] **Step 1: Widen the catalogue-check severity type.** In `resources/js/Pages/Crawls/Show.tsx`, change `CatalogCheck.severity` to include `'info'`:

```tsx
  severity: 'error' | 'warning' | 'notice' | 'info';
```

- [ ] **Step 2: Render `info` neutrally in the page detail.** In `resources/js/Pages/Crawls/Page.tsx`:
  - Line 34: widen the `issue_severities` type to `Record<string, 'error' | 'warning' | 'notice' | 'info'>`.
  - Lines 41-45 (`severityVariant`): change the type to `Record<'error' | 'warning' | 'notice' | 'info', 'danger' | 'warning' | 'neutral'>` and add `info: 'neutral',`.
  - Lines 47-51 (`severityDot`): change the type to `Record<'error' | 'warning' | 'notice' | 'info', string>` and add `info: 'bg-neutral-foreground',`.
  - Line 394: widen the cast to `severityDot[tb.severity as 'error' | 'warning' | 'notice' | 'info']`.
  - Line 417: `severityVariant` is indexed by `page.issue_severities[tab] ?? 'notice'`; no cast change needed once the map includes `info`.

- [ ] **Step 3: Add the `severity_info` lang key.** In `lang/en/crawler.php` after `'severity_notice' => 'Notice',` add `'severity_info' => 'Info',`. In `lang/de/crawler.php` after `'severity_notice' => 'Hinweis',` add `'severity_info' => 'Info',`.

- [ ] **Step 4: Add the end-to-end feature test.** In `tests/Feature/Crawler/CrawlControllerTest.php`, add:

```php
    public function test_security_category_lists_pages_with_a_security_issue(): void
    {
        [$user, $project] = $this->member();
        $crawl = Crawl::factory()->for($project)->create();
        CrawlPage::factory()->for($crawl)->create(['url' => 'https://example.com/mixed', 'issues' => ['mixed_content', 'https_urls']]);
        CrawlPage::factory()->for($crawl)->create(['url' => 'https://example.com/clean', 'issues' => ['https_urls']]);

        // Category badge counts only the page with a real problem (mixed_content), not the info-only page.
        $this->actingAs($user)
            ->get(route('app.project.crawls.show', ['project' => $project->id, 'crawl' => $crawl->id]))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('catalog.security.count', 1));

        // The security group filter lists the affected page.
        $this->actingAs($user)
            ->get(route('app.project.crawls.show', ['project' => $project->id, 'crawl' => $crawl->id, 'group' => 'security', 'issue' => 'mixed_content']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('pages.data', 1)
                ->where('pages.data.0.url', 'https://example.com/mixed')
            );
    }
```

- [ ] **Step 5: Run the frontend build + full backend gate.**

Run: `ddev npm run build`
Expected: tsc + vite succeed (no type error on the widened unions).
Run: `ddev php artisan test --filter='CrawlControllerTest'`
Expected: PASS (incl. the new end-to-end test).
Run: `ddev exec vendor/bin/pint --test lang/en/crawler.php lang/de/crawler.php`
Expected: PASS.

- [ ] **Step 6: Commit.**

```bash
git add resources/js/Pages/Crawls/Show.tsx resources/js/Pages/Crawls/Page.tsx lang/en/crawler.php lang/de/crawler.php tests/Feature/Crawler/CrawlControllerTest.php
git commit -m "feat(crawler): render info severity + security-tab end-to-end test"
```

---

## Final verification (whole plan)

- [ ] `ddev php artisan test` — full suite green (was 650 + new tests).
- [ ] `ddev exec vendor/bin/pint --test` — clean.
- [ ] `ddev npm run build` — green.
- [ ] Manual sanity (optional): run a crawl against an HTTPS site with a missing CSP; confirm the Security tab lists the pages and the `missing_csp_header` dropdown filter works, and that an all-secure page appears under `https_urls` without inflating the Security badge.

## Self-Review notes (author)

- **Spec coverage:** all 13 checks mapped to a task (12 in SecurityAnalyzer/Task 3, `wrong_content_type` in observer/Task 2); header storage (Task 2); `info` model (Task 1); frontend (Task 4). ✓
- **Type consistency:** `IssueCode` case names ↔ string values ↔ catalogue codes ↔ lang keys all use the same 13 snake_case strings. `severity()` ('info' for HttpsUrls) matches catalogue severity (asserted in Task 1 Step 8). ✓
- **Bijection:** +13 cases, +13 active → 35 = 35, guarded by `test_bijection_cardinality_holds`. ✓
