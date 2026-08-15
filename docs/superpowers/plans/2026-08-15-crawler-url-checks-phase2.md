# Crawler Phase 2 — URL checks — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Flip the crawler's `url` issue category from planned to active — implement 8 pure-string URL-hygiene checks that run for every crawled URL (HTML pages and assets), so the URL tab on the crawl overview lists affected URLs and its per-check dropdown works.

**Architecture:** A static `UrlChecker::issues(string $url): array` returns `IssueCode` string values for URL-hygiene problems. It is called from `CrawlPageObserver::recordResponse()` for every page (both the HTML and non-HTML branches), merged into the page's `$issues`, and deduped by the existing `array_unique`. No DOM, no migration, no new severity, no frontend or lang changes.

**Tech Stack:** Laravel 13 / PHP 8.3, PHPUnit, Pint. (Frontend untouched — the URL tab already exists from Phase 0.)

**Spec:** `docs/superpowers/specs/2026-08-15-crawler-url-checks-phase2-design.md`

## Global Constraints

- **Bijection stays exact:** active catalogue codes === `IssueCode` cases. This phase adds 8 `IssueCode` cases and flips 8 `url` catalogue entries planned→active, so both grow **35 → 43**. Three url codes (`url_broken_bookmark`, `url_parameters`, `url_internal_search`) STAY planned.
- **`IssueCode::category()` and `::severity()` are exhaustive `match`** (no default arm). The 8 new cases get an arm in both — `category()` → `IssueCategory::Url`, `severity()` → `'notice'`.
- **The 8 url lang labels already exist** (Phase 0, greyed) — do NOT rename; no lang change.
- **No new crawl-time network requests.** URL analysis is pure-string on the already-crawled `url`.
- **Commands run via DDEV.** Backend gate: `ddev php artisan test`. Frontend gate: `ddev npm run build` (no frontend change expected, but the build must stay green). Pint clean: `ddev exec vendor/bin/pint --test`.

---

## File Structure

**Create**
- `app/Crawler/UrlChecker.php` — the static checker.
- `tests/Unit/Crawler/UrlCheckerTest.php`.
- `tests/Feature/Crawler/CrawlPageObserverUrlTest.php`.

**Modify**
- `app/Crawler/IssueCode.php` — 8 cases + `category()`/`severity()` arms.
- `app/Crawler/CheckCatalog.php` — flip 8 url entries planned→active (leave 3 planned).
- `app/Observers/CrawlPageObserver.php` — call `UrlChecker::issues($url)` in `recordResponse()`.
- `tests/Unit/Crawler/CheckCatalogTest.php` — url active count 8 (3 remain planned).
- `tests/Feature/Crawler/CrawlControllerTest.php` — url tab lists an affected page.

---

## Task 1: IssueCode cases + catalogue flip (url category active)

**Files:**
- Modify: `app/Crawler/IssueCode.php`
- Modify: `app/Crawler/CheckCatalog.php`
- Test: `tests/Unit/Crawler/CheckCatalogTest.php`

**Interfaces:**
- Produces: 8 new `IssueCode` cases (below). Task 2's `UrlChecker` emits these codes.

- [ ] **Step 1: Add the 8 `IssueCode` cases.** In `app/Crawler/IssueCode.php`, after the `security` block (after `case WrongContentType = 'wrong_content_type';`), add:

```php
    // url
    case UrlNonAscii = 'url_non_ascii';
    case UrlUnderscores = 'url_underscores';
    case UrlUppercase = 'url_uppercase';
    case UrlContainsSpace = 'url_contains_space';
    case UrlMultipleSlashes = 'url_multiple_slashes';
    case UrlRepetitivePath = 'url_repetitive_path';
    case UrlGaTrackingParams = 'url_ga_tracking_params';
    case UrlOver115Chars = 'url_over_115_chars';
```

- [ ] **Step 2: Add `severity()` arms.** In `severity()`, add all 8 new cases to the `'notice'` arm (append them to the existing notice group):

```php
            self::UrlNonAscii, self::UrlUnderscores, self::UrlUppercase, self::UrlContainsSpace,
            self::UrlMultipleSlashes, self::UrlRepetitivePath, self::UrlGaTrackingParams,
            self::UrlOver115Chars => 'notice',
```

(Merge into the existing `=> 'notice'` arm — either extend that arm's case list or add this as another arm returning `'notice'`. Do not create a default arm.)

- [ ] **Step 3: Add `category()` arms.** In `category()`, add all 8 new cases to a `Url` arm (insert before the `Other` arm):

```php
            self::UrlNonAscii, self::UrlUnderscores, self::UrlUppercase, self::UrlContainsSpace,
            self::UrlMultipleSlashes, self::UrlRepetitivePath, self::UrlGaTrackingParams,
            self::UrlOver115Chars => IssueCategory::Url,
```

- [ ] **Step 4: Flip the 8 catalogue entries.** In `app/Crawler/CheckCatalog.php`, in the `url` block, change `'status' => self::P` → `'status' => self::A` for exactly these 8 codes: `url_non_ascii`, `url_underscores`, `url_uppercase`, `url_multiple_slashes`, `url_repetitive_path`, `url_contains_space`, `url_ga_tracking_params`, `url_over_115_chars`. LEAVE these 3 as `self::P`: `url_internal_search`, `url_parameters`, `url_broken_bookmark`.

- [ ] **Step 5: Update `CheckCatalogTest`.** In `tests/Unit/Crawler/CheckCatalogTest.php`, extend `test_active_codes_for_category_filters` with url assertions:

```php
        $url = CheckCatalog::activeCodesForCategory('url');
        $this->assertContains('url_uppercase', $url);
        $this->assertNotContains('url_parameters', $url); // stays planned
        $this->assertCount(8, $url);
```

(The existing `test_bijection_cardinality_holds` already compares `count(IssueCode::cases())` to `count(activeCodes())` dynamically — it now passes at 43 with no edit.)

- [ ] **Step 6: Run tests + Pint.**

Run: `ddev php artisan test --filter='CheckCatalogTest|IssueCodeTest'`
Expected: PASS (no `UnhandledMatchError`; url active count 8; bijection holds).
Run: `ddev exec vendor/bin/pint --test app/Crawler/IssueCode.php app/Crawler/CheckCatalog.php`
Expected: PASS.

- [ ] **Step 7: Commit.**

```bash
git add app/Crawler/IssueCode.php app/Crawler/CheckCatalog.php tests/Unit/Crawler/CheckCatalogTest.php
git commit -m "feat(crawler): activate url catalogue checks + issue codes"
```

---

## Task 2: `UrlChecker` + observer wiring + tests

**Files:**
- Create: `app/Crawler/UrlChecker.php`
- Create: `tests/Unit/Crawler/UrlCheckerTest.php`
- Create: `tests/Feature/Crawler/CrawlPageObserverUrlTest.php`
- Modify: `app/Observers/CrawlPageObserver.php`
- Test: `tests/Feature/Crawler/CrawlControllerTest.php`

**Interfaces:**
- Consumes: the 8 url `IssueCode` cases (Task 1).
- Produces: `UrlChecker::issues(string $url): string[]` — deduped list of `IssueCode` values.

- [ ] **Step 1: Write `UrlChecker`.** Create `app/Crawler/UrlChecker.php`:

```php
<?php

namespace App\Crawler;

class UrlChecker
{
    private const TRACKING_PARAMS = [
        'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content',
        'gclid', 'fbclid', 'mc_cid', 'mc_eid',
    ];

    /**
     * Pure URL-hygiene checks on a single crawled URL.
     *
     * @return string[] IssueCode values (may be empty)
     */
    public static function issues(string $url): array
    {
        [$path, $query] = self::split($url);
        $issues = [];

        if (preg_match('/[^\x00-\x7F]/', $path)) {
            $issues[] = IssueCode::UrlNonAscii->value;
        }
        if (str_contains($path, '_')) {
            $issues[] = IssueCode::UrlUnderscores->value;
        }
        if (preg_match('/[A-Z]/', $path)) {
            $issues[] = IssueCode::UrlUppercase->value;
        }
        if (str_contains($path, ' ') || stripos($path, '%20') !== false) {
            $issues[] = IssueCode::UrlContainsSpace->value;
        }
        if (str_contains($path, '//')) {
            $issues[] = IssueCode::UrlMultipleSlashes->value;
        }
        if (self::hasRepetitiveSegments($path)) {
            $issues[] = IssueCode::UrlRepetitivePath->value;
        }
        if (self::hasTrackingParams($query)) {
            $issues[] = IssueCode::UrlGaTrackingParams->value;
        }
        if (mb_strlen($url) > 115) {
            $issues[] = IssueCode::UrlOver115Chars->value;
        }

        return $issues;
    }

    /**
     * Split into [path, query] robustly — parse_url() returns false on URLs with a
     * literal space or raw non-ASCII, which are exactly the URLs we flag. Strip
     * `scheme://host` with a regex, then separate the query.
     *
     * @return array{0: string, 1: string}
     */
    private static function split(string $url): array
    {
        $afterHost = preg_replace('#^[a-z][a-z0-9+.\-]*://[^/?#]*#i', '', $url) ?? $url;
        $path = preg_split('/[?#]/', $afterHost, 2)[0];
        $query = preg_match('/\?([^#]*)/', $afterHost, $m) ? $m[1] : '';

        return [$path, $query];
    }

    private static function hasRepetitiveSegments(string $path): bool
    {
        $segments = array_values(array_filter(explode('/', $path), fn ($s) => $s !== ''));
        for ($i = 1, $n = count($segments); $i < $n; $i++) {
            if ($segments[$i] === $segments[$i - 1]) {
                return true;
            }
        }

        return false;
    }

    private static function hasTrackingParams(string $query): bool
    {
        if ($query === '') {
            return false;
        }
        parse_str($query, $params);

        return array_intersect_key($params, array_flip(self::TRACKING_PARAMS)) !== [];
    }
}
```

- [ ] **Step 2: Write the unit test.** Create `tests/Unit/Crawler/UrlCheckerTest.php`:

```php
<?php

namespace Tests\Unit\Crawler;

use App\Crawler\UrlChecker;
use PHPUnit\Framework\TestCase;

class UrlCheckerTest extends TestCase
{
    public function test_clean_url_has_no_issues(): void
    {
        $this->assertSame([], UrlChecker::issues('https://example.com/blog/post-1'));
    }

    public function test_each_hygiene_check_fires(): void
    {
        $this->assertContains('url_non_ascii', UrlChecker::issues('https://example.com/café'));
        $this->assertContains('url_underscores', UrlChecker::issues('https://example.com/foo_bar'));
        $this->assertContains('url_uppercase', UrlChecker::issues('https://example.com/Foo'));
        $this->assertContains('url_contains_space', UrlChecker::issues('https://example.com/foo bar'));
        $this->assertContains('url_contains_space', UrlChecker::issues('https://example.com/foo%20bar'));
        $this->assertContains('url_multiple_slashes', UrlChecker::issues('https://example.com/foo//bar'));
        $this->assertContains('url_repetitive_path', UrlChecker::issues('https://example.com/blog/blog/post'));
    }

    public function test_ga_tracking_params(): void
    {
        $this->assertContains('url_ga_tracking_params', UrlChecker::issues('https://example.com/p?utm_source=x'));
        $this->assertContains('url_ga_tracking_params', UrlChecker::issues('https://example.com/p?gclid=abc'));
        $this->assertNotContains('url_ga_tracking_params', UrlChecker::issues('https://example.com/p?ref=x'));
    }

    public function test_hygiene_checks_read_path_only_not_query(): void
    {
        // Uppercase + underscore only in the query → clean path → not flagged.
        $issues = UrlChecker::issues('https://example.com/blog?ref=AbC_Def');
        $this->assertNotContains('url_uppercase', $issues);
        $this->assertNotContains('url_underscores', $issues);
    }

    public function test_repetitive_path_only_flags_adjacent_segments(): void
    {
        $this->assertNotContains('url_repetitive_path', UrlChecker::issues('https://example.com/a/b/a'));
    }

    public function test_length_boundary_at_115(): void
    {
        $base = 'https://example.com/';
        $at115 = $base.str_repeat('a', 115 - mb_strlen($base));
        $over = $base.str_repeat('a', 116 - mb_strlen($base));
        $this->assertNotContains('url_over_115_chars', UrlChecker::issues($at115));
        $this->assertContains('url_over_115_chars', UrlChecker::issues($over));
    }

    public function test_deferred_codes_are_never_emitted(): void
    {
        $issues = UrlChecker::issues('https://example.com/search?q=shoes');
        $this->assertNotContains('url_parameters', $issues);
        $this->assertNotContains('url_internal_search', $issues);
        $this->assertNotContains('url_broken_bookmark', $issues);
    }

    public function test_root_url_has_no_path_issues(): void
    {
        $this->assertSame([], UrlChecker::issues('https://example.com'));
        $this->assertSame([], UrlChecker::issues('https://example.com/'));
    }
}
```

- [ ] **Step 3: Run the unit test.**

Run: `ddev php artisan test --filter=UrlCheckerTest`
Expected: PASS (all cases; note the literal-space and café cases rely on the regex split, not `parse_url`).

- [ ] **Step 4: Wire into the observer.** In `app/Observers/CrawlPageObserver.php`:
  - Add the import: `use App\Crawler\UrlChecker;`.
  - In `recordResponse()`, immediately AFTER the `if ($wrongContentType) { ... }` block and BEFORE the `$page = $this->crawl->pages()->create(...)` call, add:

```php
        foreach (UrlChecker::issues($url) as $code) {
            $issues[] = $code;
        }
```

  (The existing `'issues' => array_values(array_unique($issues))` dedups.)

- [ ] **Step 5: Write the observer feature test.** Create `tests/Feature/Crawler/CrawlPageObserverUrlTest.php`:

```php
<?php

namespace Tests\Feature\Crawler;

use App\Crawler\PageAnalyzer;
use App\Models\Crawl;
use App\Models\Project;
use App\Observers\CrawlPageObserver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrawlPageObserverUrlTest extends TestCase
{
    use RefreshDatabase;

    private function observer(Crawl $crawl): CrawlPageObserver
    {
        return new CrawlPageObserver($crawl, new PageAnalyzer, 'example.com');
    }

    public function test_url_hygiene_issues_recorded_for_html_page(): void
    {
        $crawl = Crawl::factory()->for(Project::factory())->create();
        $page = $this->observer($crawl)->recordResponse(
            'https://example.com/Foo_Bar',
            200,
            ['Content-Type' => ['text/html']],
            '<html><head><title>t</title></head><body>x</body></html>',
            5.0,
        );

        $this->assertContains('url_uppercase', $page->issues);
        $this->assertContains('url_underscores', $page->issues);
    }

    public function test_url_hygiene_issues_recorded_for_non_html_asset(): void
    {
        $crawl = Crawl::factory()->for(Project::factory())->create();
        $page = $this->observer($crawl)->recordResponse(
            'https://example.com/My%20Image.png',
            200,
            ['Content-Type' => ['image/png']],
            'PNGDATA',
            5.0,
        );

        // Coverage extends to assets: the space is flagged even though no analyzer ran.
        $this->assertContains('url_contains_space', $page->issues);
    }
}
```

- [ ] **Step 6: Add the controller feature test.** In `tests/Feature/Crawler/CrawlControllerTest.php`, add:

```php
    public function test_url_category_lists_pages_with_a_url_issue(): void
    {
        [$user, $project] = $this->member();
        $crawl = Crawl::factory()->for($project)->create();
        CrawlPage::factory()->for($crawl)->create(['url' => 'https://example.com/Foo_Bar', 'issues' => ['url_uppercase', 'url_underscores']]);
        CrawlPage::factory()->for($crawl)->create(['url' => 'https://example.com/clean', 'issues' => []]);

        $this->actingAs($user)
            ->get(route('app.project.crawls.show', ['project' => $project->id, 'crawl' => $crawl->id]))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('catalog.url.count', 1));

        $this->actingAs($user)
            ->get(route('app.project.crawls.show', ['project' => $project->id, 'crawl' => $crawl->id, 'group' => 'url', 'issue' => 'url_uppercase']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('pages.data', 1)
                ->where('pages.data.0.url', 'https://example.com/Foo_Bar')
            );
    }
```

- [ ] **Step 7: Run tests + build + Pint.**

Run: `ddev php artisan test --filter='UrlCheckerTest|CrawlPageObserverUrlTest|CrawlControllerTest'`
Expected: PASS.
Run: `ddev npm run build`
Expected: green (no frontend change; the url tab already builds).
Run: `ddev exec vendor/bin/pint --test app/Crawler/UrlChecker.php app/Observers/CrawlPageObserver.php tests/Unit/Crawler/UrlCheckerTest.php tests/Feature/Crawler/CrawlPageObserverUrlTest.php tests/Feature/Crawler/CrawlControllerTest.php`
Expected: PASS.

- [ ] **Step 8: Commit.**

```bash
git add app/Crawler/UrlChecker.php app/Observers/CrawlPageObserver.php tests/Unit/Crawler/UrlCheckerTest.php tests/Feature/Crawler/CrawlPageObserverUrlTest.php tests/Feature/Crawler/CrawlControllerTest.php
git commit -m "feat(crawler): UrlChecker runs URL-hygiene checks on every crawled URL"
```

---

## Final verification (whole plan)

- [ ] `ddev php artisan test` — full suite green.
- [ ] `ddev exec vendor/bin/pint --test` — clean.
- [ ] `ddev npm run build` — green.

## Self-Review notes (author)

- **Spec coverage:** 8 active checks in `UrlChecker` (Task 2), enum/catalogue in Task 1, observer wiring + all-URL coverage (Task 2). 3 deferred codes stay planned (asserted in Task 1 Step 5 + Task 2 unit test). ✓
- **Type consistency:** the 8 snake_case strings match across enum values, catalogue codes, lang keys, and `UrlChecker` emissions. ✓
- **Bijection:** +8 cases, +8 active → 43 = 43; the existing dynamic cardinality test covers it. ✓
- **parse_url robustness:** deliberately avoided for path extraction because it fails on literal-space/raw-non-ASCII URLs — the regex split in `UrlChecker::split()` handles them, and the unit test exercises both. ✓
