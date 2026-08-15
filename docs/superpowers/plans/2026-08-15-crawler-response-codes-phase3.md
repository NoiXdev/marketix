# Crawler Phase 3 — Response-code refinements — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Activate 5 `response_codes` checks (single-redirect vs multi-hop chain, HTTP/meta refresh, redirect loop, no-response) via a pure `RedirectClassifier`, and correct `redirect_chain` to mean a real 2+ hop chain.

**Architecture:** A pure `RedirectClassifier` with two static methods — `issues()` for a successful response, `failureIssues()` for a failed fetch — holds all response-code logic. `CrawlPageObserver` delegates to it in `recordResponse()` and `crawlFailed()`. No migration, no new severity, no frontend/lang changes.

**Tech Stack:** Laravel 13 / PHP 8.3, Guzzle, PHPUnit, Pint.

**Spec:** `docs/superpowers/specs/2026-08-15-crawler-response-codes-phase3-design.md`

## Global Constraints

- **Bijection stays exact:** active catalogue codes === `IssueCode` cases. This phase adds 5 `IssueCode` cases and flips 5 `response_codes` catalogue entries planned→active, so both grow **43 → 48**. The 4 other planned response_codes codes (`external_server_error_5xx`, `internal_js_redirect`, `internal_success_2xx`, `internal_blocked_resource`) STAY planned.
- **`IssueCode::category()` and `::severity()` are exhaustive `match`** (no default arm). The 5 new cases get an arm in both: `category()` → `IssueCategory::ResponseCodes`; severities — `internal_redirect_3xx` → `notice`, `internal_http_refresh_redirect`/`internal_meta_refresh_redirect` → `warning`, `internal_redirect_loop`/`internal_no_response` → `error`.
- **`redirect_chain` stays active** (severity `warning`); only its trigger changes (≥1 hop → ≥2 hops). The stored `redirect_chain` DB column is unchanged.
- **The 5 response_codes lang labels already exist** (Phase 0, greyed) — do NOT rename; no lang change.
- **No new crawl-time network requests.** Everything reads already-captured response/redirect data.
- **Commands run via DDEV.** Backend gate `ddev php artisan test`; frontend gate `ddev npm run build` (no change expected); Pint clean `ddev exec vendor/bin/pint --test`.

---

## File Structure

**Create**
- `app/Crawler/RedirectClassifier.php`
- `tests/Unit/Crawler/RedirectClassifierTest.php`
- `tests/Feature/Crawler/CrawlPageObserverRedirectTest.php`

**Modify**
- `app/Crawler/IssueCode.php` — 5 cases + `category()`/`severity()` arms.
- `app/Crawler/CheckCatalog.php` — flip 5 response_codes entries planned→active.
- `app/Observers/CrawlPageObserver.php` — delegate to `RedirectClassifier` in `recordResponse` + `crawlFailed`; remove the inline redirect_chain.
- `tests/Unit/Crawler/CheckCatalogTest.php` — response_codes active count 9.
- `tests/Feature/Crawler/CrawlControllerTest.php` — response_codes tab lists an affected page.

---

## Task 1: IssueCode cases + catalogue flip (response_codes)

**Files:**
- Modify: `app/Crawler/IssueCode.php`
- Modify: `app/Crawler/CheckCatalog.php`
- Test: `tests/Unit/Crawler/CheckCatalogTest.php`

**Interfaces:**
- Produces: 5 new `IssueCode` cases (below). Task 2's `RedirectClassifier` emits these.

- [ ] **Step 1: Add the 5 `IssueCode` cases.** In `app/Crawler/IssueCode.php`, after the `url` block, add:

```php
    // response code refinements
    case InternalRedirect3xx = 'internal_redirect_3xx';
    case InternalHttpRefreshRedirect = 'internal_http_refresh_redirect';
    case InternalMetaRefreshRedirect = 'internal_meta_refresh_redirect';
    case InternalRedirectLoop = 'internal_redirect_loop';
    case InternalNoResponse = 'internal_no_response';
```

- [ ] **Step 2: Add `severity()` arms.** In `severity()`, place each new case in the matching arm:
  - add `self::InternalRedirect3xx` to the `'notice'` arm,
  - add `self::InternalHttpRefreshRedirect, self::InternalMetaRefreshRedirect` to the `'warning'` arm,
  - add `self::InternalRedirectLoop, self::InternalNoResponse` to the `'error'` arm.

  (Extend the existing arms' case lists. Do not add a default arm.)

- [ ] **Step 3: Add `category()` arms.** In `category()`, extend the existing `IssueCategory::ResponseCodes` arm (currently `self::ClientError, self::ServerError, self::RedirectChain, self::RobotsBlocked`) to also include:

```php
            self::InternalRedirect3xx, self::InternalHttpRefreshRedirect, self::InternalMetaRefreshRedirect,
            self::InternalRedirectLoop, self::InternalNoResponse,
```

- [ ] **Step 4: Flip the 5 catalogue entries.** In `app/Crawler/CheckCatalog.php`, in the `response_codes` block, change `'status' => self::P` → `'status' => self::A` for exactly these 5 codes: `internal_redirect_3xx`, `internal_http_refresh_redirect`, `internal_meta_refresh_redirect`, `internal_redirect_loop`, `internal_no_response`. LEAVE these 4 as `self::P`: `external_server_error_5xx`, `internal_js_redirect`, `internal_success_2xx`, `internal_blocked_resource`.

- [ ] **Step 5: Update `CheckCatalogTest`.** In `tests/Unit/Crawler/CheckCatalogTest.php`, extend `test_active_codes_for_category_filters` with response_codes assertions:

```php
        $responseCodes = CheckCatalog::activeCodesForCategory('response_codes');
        $this->assertContains('internal_redirect_3xx', $responseCodes);
        $this->assertNotContains('internal_success_2xx', $responseCodes); // stays planned
        $this->assertCount(9, $responseCodes);
```

(The existing `test_bijection_cardinality_holds` compares counts dynamically — passes at 48 with no edit.)

- [ ] **Step 6: Run tests + Pint.**

Run: `ddev php artisan test --filter='CheckCatalogTest|IssueCodeTest'`
Expected: PASS (no `UnhandledMatchError`; response_codes active count 9; bijection holds).
Run: `ddev exec vendor/bin/pint --test app/Crawler/IssueCode.php app/Crawler/CheckCatalog.php`
Expected: PASS.

- [ ] **Step 7: Commit.**

```bash
git add app/Crawler/IssueCode.php app/Crawler/CheckCatalog.php tests/Unit/Crawler/CheckCatalogTest.php
git commit -m "feat(crawler): activate response-code refinement checks + issue codes"
```

---

## Task 2: `RedirectClassifier` + observer wiring

**Files:**
- Create: `app/Crawler/RedirectClassifier.php`
- Create: `tests/Unit/Crawler/RedirectClassifierTest.php`
- Create: `tests/Feature/Crawler/CrawlPageObserverRedirectTest.php`
- Modify: `app/Observers/CrawlPageObserver.php`
- Test: `tests/Feature/Crawler/CrawlControllerTest.php`

**Interfaces:**
- Consumes: the 5 response_codes `IssueCode` cases (Task 1), plus `ClientError`/`ServerError`/`RedirectChain` (existing).
- Produces: `RedirectClassifier::issues(array $chain, array $lowerHeaders, string $body, bool $isHtml): string[]` and `RedirectClassifier::failureIssues(?int $status, bool $tooManyRedirects): string[]`.

- [ ] **Step 1: Write `RedirectClassifier`.** Create `app/Crawler/RedirectClassifier.php`:

```php
<?php

namespace App\Crawler;

class RedirectClassifier
{
    /**
     * Issues for a successful (crawled) response.
     *
     * @param  string[]  $chain  [$url, ...redirect targets], or [] when there was no redirect
     * @param  array<string, string[]>  $lowerHeaders  headers with lower-cased keys
     * @return string[]  IssueCode values
     */
    public static function issues(array $chain, array $lowerHeaders, string $body, bool $isHtml): array
    {
        $issues = [];
        $hops = max(0, count($chain) - 1);

        if ($hops >= 2) {
            $issues[] = IssueCode::RedirectChain->value;
        } elseif ($hops === 1) {
            $issues[] = IssueCode::InternalRedirect3xx->value;
        }

        if (count(array_unique($chain)) !== count($chain)) {
            $issues[] = IssueCode::InternalRedirectLoop->value;
        }

        if (isset($lowerHeaders['refresh'])) {
            $issues[] = IssueCode::InternalHttpRefreshRedirect->value;
        }

        if ($isHtml && preg_match('/<meta[^>]+http-equiv\s*=\s*["\']?\s*refresh/i', $body)) {
            $issues[] = IssueCode::InternalMetaRefreshRedirect->value;
        }

        return $issues;
    }

    /**
     * Issues for a failed fetch (crawlFailed). Mutually exclusive, in precedence order.
     *
     * @return string[]  IssueCode values
     */
    public static function failureIssues(?int $status, bool $tooManyRedirects): array
    {
        if ($tooManyRedirects) {
            return [IssueCode::InternalRedirectLoop->value];
        }
        if ($status === null) {
            return [IssueCode::InternalNoResponse->value];
        }
        if ($status >= 400 && $status < 500) {
            return [IssueCode::ClientError->value];
        }

        return [IssueCode::ServerError->value];
    }
}
```

- [ ] **Step 2: Write the unit test.** Create `tests/Unit/Crawler/RedirectClassifierTest.php`:

```php
<?php

namespace Tests\Unit\Crawler;

use App\Crawler\RedirectClassifier;
use PHPUnit\Framework\TestCase;

class RedirectClassifierTest extends TestCase
{
    public function test_single_redirect_is_3xx_not_chain(): void
    {
        $codes = RedirectClassifier::issues(['https://example.com/a', 'https://example.com/b'], [], '', false);
        $this->assertContains('internal_redirect_3xx', $codes);
        $this->assertNotContains('redirect_chain', $codes);
    }

    public function test_two_hops_is_chain_not_3xx(): void
    {
        $codes = RedirectClassifier::issues(['https://example.com/a', 'https://example.com/b', 'https://example.com/c'], [], '', false);
        $this->assertContains('redirect_chain', $codes);
        $this->assertNotContains('internal_redirect_3xx', $codes);
    }

    public function test_no_redirect_emits_neither(): void
    {
        $codes = RedirectClassifier::issues([], [], '', false);
        $this->assertNotContains('redirect_chain', $codes);
        $this->assertNotContains('internal_redirect_3xx', $codes);
    }

    public function test_repeated_url_in_chain_is_a_loop(): void
    {
        $codes = RedirectClassifier::issues(['https://example.com/a', 'https://example.com/b', 'https://example.com/a'], [], '', false);
        $this->assertContains('internal_redirect_loop', $codes);
    }

    public function test_http_refresh_header(): void
    {
        $codes = RedirectClassifier::issues([], ['refresh' => ['0;url=/x']], '', false);
        $this->assertContains('internal_http_refresh_redirect', $codes);
    }

    public function test_meta_refresh_in_html_only(): void
    {
        $html = '<html><head><meta http-equiv="refresh" content="0;url=/x"></head></html>';
        $this->assertContains('internal_meta_refresh_redirect', RedirectClassifier::issues([], [], $html, true));
        // Not flagged when the body is not treated as HTML …
        $this->assertNotContains('internal_meta_refresh_redirect', RedirectClassifier::issues([], [], $html, false));
        // … and not flagged when absent.
        $this->assertNotContains('internal_meta_refresh_redirect', RedirectClassifier::issues([], [], '<html><body>x</body></html>', true));
    }

    public function test_failure_issues(): void
    {
        $this->assertSame(['internal_no_response'], RedirectClassifier::failureIssues(null, false));
        $this->assertSame(['internal_redirect_loop'], RedirectClassifier::failureIssues(null, true)); // loop wins over null
        $this->assertSame(['client_error'], RedirectClassifier::failureIssues(404, false));
        $this->assertSame(['server_error'], RedirectClassifier::failureIssues(503, false));
    }
}
```

- [ ] **Step 3: Run the unit test.**

Run: `ddev php artisan test --filter=RedirectClassifierTest`
Expected: PASS.

- [ ] **Step 4: Wire into `recordResponse`.** In `app/Observers/CrawlPageObserver.php`:
  - Add imports: `use App\Crawler\RedirectClassifier;` and `use GuzzleHttp\Exception\TooManyRedirectsException;`.
  - In `recordResponse()`, DELETE the inline redirect-chain block:

```php
        if (count($chain) > 1) {
            $issues[] = IssueCode::RedirectChain->value;
        }
```

  - After the `if ($wrongContentType) { ... }` block and BEFORE the `$page = $this->crawl->pages()->create(...)` call (alongside the Phase-2 `UrlChecker` merge), add:

```php
        foreach (RedirectClassifier::issues($chain, $lower, $body, $category === ResourceClassifier::HTML) as $code) {
            $issues[] = $code;
        }
```

  (`$lower` is the already-computed lower-cased header array; `$chain` comes from `$this->redirects(...)`; the existing `array_values(array_unique($issues))` dedups.)

- [ ] **Step 5: Refine `crawlFailed`.** In `app/Observers/CrawlPageObserver.php::crawlFailed()`, replace the `$page = $this->crawl->pages()->create([...])` call's `issues` computation. The method body becomes:

```php
        $status = $requestException->getResponse()?->getStatusCode();
        $tooMany = $requestException instanceof TooManyRedirectsException;
        $page = $this->crawl->pages()->create([
            'url' => $url,
            'status_code' => $status,
            'issues' => RedirectClassifier::failureIssues($status, $tooMany),
        ]);
        $this->crawl->increment('pages_crawled');
        unset($page);
```

- [ ] **Step 6: Write the observer feature test.** Create `tests/Feature/Crawler/CrawlPageObserverRedirectTest.php`:

```php
<?php

namespace Tests\Feature\Crawler;

use App\Crawler\PageAnalyzer;
use App\Models\Crawl;
use App\Models\Project;
use App\Observers\CrawlPageObserver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrawlPageObserverRedirectTest extends TestCase
{
    use RefreshDatabase;

    private function observer(Crawl $crawl): CrawlPageObserver
    {
        return new CrawlPageObserver($crawl, new PageAnalyzer, 'example.com');
    }

    private function html(string $head = ''): string
    {
        return "<html><head><title>t</title>{$head}</head><body>x</body></html>";
    }

    public function test_single_redirect_records_internal_redirect_3xx(): void
    {
        $crawl = Crawl::factory()->for(Project::factory())->create();
        $page = $this->observer($crawl)->recordResponse(
            'https://example.com/start',
            200,
            ['Content-Type' => ['text/html'], 'X-Guzzle-Redirect-History' => ['https://example.com/final']],
            $this->html(),
            5.0,
        );

        $this->assertContains('internal_redirect_3xx', $page->issues);
        $this->assertNotContains('redirect_chain', $page->issues);
    }

    public function test_two_hop_chain_records_redirect_chain(): void
    {
        $crawl = Crawl::factory()->for(Project::factory())->create();
        $page = $this->observer($crawl)->recordResponse(
            'https://example.com/start',
            200,
            ['Content-Type' => ['text/html'], 'X-Guzzle-Redirect-History' => ['https://example.com/mid,https://example.com/final']],
            $this->html(),
            5.0,
        );

        $this->assertContains('redirect_chain', $page->issues);
        $this->assertNotContains('internal_redirect_3xx', $page->issues);
    }

    public function test_http_refresh_header_recorded(): void
    {
        $crawl = Crawl::factory()->for(Project::factory())->create();
        $page = $this->observer($crawl)->recordResponse(
            'https://example.com/r',
            200,
            ['Content-Type' => ['text/html'], 'Refresh' => ['0;url=/x']],
            $this->html(),
            5.0,
        );

        $this->assertContains('internal_http_refresh_redirect', $page->issues);
    }

    public function test_meta_refresh_recorded(): void
    {
        $crawl = Crawl::factory()->for(Project::factory())->create();
        $page = $this->observer($crawl)->recordResponse(
            'https://example.com/m',
            200,
            ['Content-Type' => ['text/html']],
            $this->html('<meta http-equiv="refresh" content="0;url=/x">'),
            5.0,
        );

        $this->assertContains('internal_meta_refresh_redirect', $page->issues);
    }
}
```

- [ ] **Step 7: Add the controller feature test.** In `tests/Feature/Crawler/CrawlControllerTest.php`, add:

```php
    public function test_response_codes_category_lists_a_refresh_page(): void
    {
        [$user, $project] = $this->member();
        $crawl = Crawl::factory()->for($project)->create();
        CrawlPage::factory()->for($crawl)->create(['url' => 'https://example.com/m', 'issues' => ['internal_meta_refresh_redirect']]);
        CrawlPage::factory()->for($crawl)->create(['url' => 'https://example.com/clean', 'issues' => []]);

        $this->actingAs($user)
            ->get(route('app.project.crawls.show', ['project' => $project->id, 'crawl' => $crawl->id, 'group' => 'response_codes', 'issue' => 'internal_meta_refresh_redirect']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('pages.data', 1)
                ->where('pages.data.0.url', 'https://example.com/m')
            );
    }
```

- [ ] **Step 8: Run tests + build + Pint.**

Run: `ddev php artisan test --filter='RedirectClassifierTest|CrawlPageObserverRedirectTest|CrawlControllerTest'`
Expected: PASS.
Run: `ddev npm run build`
Expected: green (no frontend change).
Run: `ddev exec vendor/bin/pint --test app/Crawler/RedirectClassifier.php app/Observers/CrawlPageObserver.php tests/Unit/Crawler/RedirectClassifierTest.php tests/Feature/Crawler/CrawlPageObserverRedirectTest.php tests/Feature/Crawler/CrawlControllerTest.php`
Expected: PASS.

- [ ] **Step 9: Commit.**

```bash
git add app/Crawler/RedirectClassifier.php app/Observers/CrawlPageObserver.php tests/Unit/Crawler/RedirectClassifierTest.php tests/Feature/Crawler/CrawlPageObserverRedirectTest.php tests/Feature/Crawler/CrawlControllerTest.php
git commit -m "feat(crawler): RedirectClassifier refines redirect + failure response codes"
```

---

## Final verification (whole plan)

- [ ] `ddev php artisan test` — full suite green.
- [ ] `ddev exec vendor/bin/pint --test` — clean.
- [ ] `ddev npm run build` — green.

## Self-Review notes (author)

- **Spec coverage:** 5 active checks — redirect_3xx/chain/loop/http_refresh/meta_refresh in `RedirectClassifier::issues` (Task 2), no_response + loop in `failureIssues` (Task 2), enum/catalogue in Task 1. redirect_chain threshold change is the `issues()` `$hops >= 2` branch. ✓
- **Type consistency:** the 5 snake_case strings match across enum values, catalogue codes, lang keys, and classifier emissions. Severities in the plan match the catalogue (notice/warning/warning/error/error). ✓
- **Bijection:** +5 cases, +5 active → 48 = 48 (dynamic cardinality test). ✓
- **crawlFailed coverage:** the failure decision is unit-tested via `failureIssues` (pure); `crawlFailed`'s 3-line delegation to it is reviewed by inspection (constructing a `CrawlProgress` + `RequestException` for an integration test adds fragility with no logic gain). ✓
- **redirect_chain regression:** no existing test pins the old ≥1-hop threshold (verified), so the change is safe; the new thresholds are covered by both the unit and observer tests. ✓
