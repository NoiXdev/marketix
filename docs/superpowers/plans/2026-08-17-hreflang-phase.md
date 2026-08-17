# Hreflang checks — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a `hreflang` check category (12 checks): per-page `HreflangAnalyzer` (HTML `<link>` + HTTP `Link:` header) + cross-page cluster checks in `AggregateCrawlJob`, with off-host targets status-probed only.

**Architecture:** Mirrors Phase 6 (Canonicals/Pagination). A new `crawl_pages.hreflang` JSON column stores each page's resolved annotations; per-page checks run in the analyzer, cross-page checks in the aggregate job using a cluster map built from that column.

**Tech Stack:** Laravel 13 / PHP 8.3, spatie/crawler v9 (same-host), React/TS + Inertia, PHPUnit, Pint.

**Spec:** docs/superpowers/specs/2026-08-17-hreflang-phase-design.md

## Global Constraints

- 12 new codes, all `status = active`, category `hreflang`. The catalogue⇔`IssueCode` **bijection** test and the **catalogue-severity == `IssueCode::severity()`** test must stay green — so the three sources (enum cases, `severity()` arms, `CheckCatalog` entries) must agree exactly on codes AND severities.
- Severities (must match in both `IssueCode::severity()` and `CheckCatalog`):
  - **warning:** `hreflang_incorrect_codes`, `hreflang_multiple_entries`, `hreflang_outside_head`, `hreflang_not_using_canonical`, `hreflang_non_200`, `hreflang_missing_return_link`, `hreflang_non_canonical_return_link`, `hreflang_inconsistent_language`, `hreflang_noindex_return_link`
  - **notice:** `hreflang_missing_self_reference`, `hreflang_missing_x_default`, `hreflang_unlinked`
- Crawler stays same-host: off-host hreflang targets get a status probe only (reuse `LinkStatusChecker`), never fetched/parsed.
- Tests must use resolvable public hosts (example.com), NOT RFC-2606 TLDs — `UrlSafety::hostIsSafe` does real DNS before any HTTP that `Http::fake` can't intercept.
- Gates: `ddev php artisan test` green; `ddev npm run build` green; `ddev exec vendor/bin/pint --test` clean. Lint is broken — build is the FE gate.

---

## Task 1: enum + category + catalogue + column + PageContext

**Files:** Modify `app/Crawler/IssueCode.php`, `app/Crawler/IssueCategory.php`, `app/Crawler/CheckCatalog.php`, `app/Crawler/PageContext.php`, `app/Models/CrawlPage.php`; Create `database/migrations/2026_08_17_000001_add_hreflang_to_crawl_pages.php`.

**Interfaces produced:** 12 `IssueCode` cases, `IssueCategory::Hreflang`, 12 active catalogue entries, `crawl_pages.hreflang` column + cast, `PageContext::$linkHeader`.

- [ ] **Step 1: `IssueCategory` — add the case.** After `case Pagination = 'pagination';` add:
```php
case Hreflang = 'hreflang';
```

- [ ] **Step 2: `IssueCode` — add 12 cases.** Add a `// hreflang` group of cases:
```php
case HreflangIncorrectCodes = 'hreflang_incorrect_codes';
case HreflangMultipleEntries = 'hreflang_multiple_entries';
case HreflangOutsideHead = 'hreflang_outside_head';
case HreflangMissingSelfReference = 'hreflang_missing_self_reference';
case HreflangMissingXDefault = 'hreflang_missing_x_default';
case HreflangNotUsingCanonical = 'hreflang_not_using_canonical';
case HreflangNon200 = 'hreflang_non_200';
case HreflangMissingReturnLink = 'hreflang_missing_return_link';
case HreflangNonCanonicalReturnLink = 'hreflang_non_canonical_return_link';
case HreflangInconsistentLanguage = 'hreflang_inconsistent_language';
case HreflangNoindexReturnLink = 'hreflang_noindex_return_link';
case HreflangUnlinked = 'hreflang_unlinked';
```

- [ ] **Step 3: `IssueCode::severity()` — add arms.** Append to the existing `'warning'` arm: `self::HreflangIncorrectCodes, self::HreflangMultipleEntries, self::HreflangOutsideHead, self::HreflangNotUsingCanonical, self::HreflangNon200, self::HreflangMissingReturnLink, self::HreflangNonCanonicalReturnLink, self::HreflangInconsistentLanguage, self::HreflangNoindexReturnLink`. Append to the `'notice'` arm: `self::HreflangMissingSelfReference, self::HreflangMissingXDefault, self::HreflangUnlinked`.

- [ ] **Step 4: `IssueCode::category()` — add arms.** In the `category()` match, add an arm returning `IssueCategory::Hreflang` for all 12 new cases (list them comma-separated). Follow the exact pattern the method already uses for Canonicals/Pagination.

- [ ] **Step 5: `CheckCatalog::all()` — add 12 entries.** After the pagination block, add a `// hreflang` block with one entry per code, severities matching Step 3:
```php
['code' => 'hreflang_incorrect_codes', 'category' => 'hreflang', 'severity' => 'warning', 'status' => self::A],
['code' => 'hreflang_multiple_entries', 'category' => 'hreflang', 'severity' => 'warning', 'status' => self::A],
['code' => 'hreflang_outside_head', 'category' => 'hreflang', 'severity' => 'warning', 'status' => self::A],
['code' => 'hreflang_not_using_canonical', 'category' => 'hreflang', 'severity' => 'warning', 'status' => self::A],
['code' => 'hreflang_non_200', 'category' => 'hreflang', 'severity' => 'warning', 'status' => self::A],
['code' => 'hreflang_missing_return_link', 'category' => 'hreflang', 'severity' => 'warning', 'status' => self::A],
['code' => 'hreflang_non_canonical_return_link', 'category' => 'hreflang', 'severity' => 'warning', 'status' => self::A],
['code' => 'hreflang_inconsistent_language', 'category' => 'hreflang', 'severity' => 'warning', 'status' => self::A],
['code' => 'hreflang_noindex_return_link', 'category' => 'hreflang', 'severity' => 'warning', 'status' => self::A],
['code' => 'hreflang_missing_self_reference', 'category' => 'hreflang', 'severity' => 'notice', 'status' => self::A],
['code' => 'hreflang_missing_x_default', 'category' => 'hreflang', 'severity' => 'notice', 'status' => self::A],
['code' => 'hreflang_unlinked', 'category' => 'hreflang', 'severity' => 'notice', 'status' => self::A],
```

- [ ] **Step 6: Migration.** Create `database/migrations/2026_08_17_000001_add_hreflang_to_crawl_pages.php` adding a nullable JSON `hreflang` column to `crawl_pages` (mirror `2026_08_15_000004_add_pagination_urls_to_crawl_pages.php`'s structure), with a matching `down()`.

- [ ] **Step 7: Cast.** In `app/Models/CrawlPage.php` `casts()`, add `'hreflang' => 'array',`.

- [ ] **Step 8: `PageContext`.** Add a constructor-promoted `public ?string $linkHeader = null` parameter (after `$scheme`), with a doc note that it's the raw HTTP `Link:` header value.

- [ ] **Step 9: Run tests + Pint + commit.**
Run: `ddev php artisan test --filter="CheckCatalog|IssueCode"` → PASS (bijection + severity invariants hold with the 12 new codes).
Run: `ddev exec vendor/bin/pint app/Crawler tests database`.
```bash
git add app/Crawler/IssueCode.php app/Crawler/IssueCategory.php app/Crawler/CheckCatalog.php app/Crawler/PageContext.php app/Models/CrawlPage.php database/migrations/2026_08_17_000001_add_hreflang_to_crawl_pages.php
git commit -m "feat(crawler): hreflang catalogue codes, category, column + PageContext link header"
```
(End commit body with `Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>`.)

---

## Task 2: `HreflangCodes` validator

**Files:** Create `app/Crawler/HreflangCodes.php`, `tests/Unit/Crawler/HreflangCodesTest.php`.

**Interfaces produced:** `HreflangCodes::isValid(string $code): bool` — consumed by `HreflangAnalyzer` (Task 3).

- [ ] **Step 1: Write the test first** (`tests/Unit/Crawler/HreflangCodesTest.php`):
```php
public function test_valid_codes(): void
{
    foreach (['en', 'de', 'en-GB', 'de-DE', 'zh-Hans', 'zh-Hant-HK', 'es-419', 'x-default', 'pt-BR'] as $c) {
        $this->assertTrue(HreflangCodes::isValid($c), $c);
    }
}

public function test_invalid_codes(): void
{
    foreach (['', 'en-UK', 'zz', 'de_DE', 'EN', 'english', 'en-gb', 'x_default', '123'] as $c) {
        $this->assertFalse(HreflangCodes::isValid($c), $c);
    }
}
```
(Note: `en-UK` is invalid because the ISO 3166-1 alpha-2 code is `GB`; region must be uppercase; language must be lowercase.)

- [ ] **Step 2: Run it — RED.** `ddev php artisan test --filter=HreflangCodesTest` → fails (class missing).

- [ ] **Step 3: Implement `HreflangCodes`.** Create the class with two private `const` arrays: `LANGUAGES` (the ISO 639-1 two-letter language codes) and `REGIONS` (the ISO 3166-1 alpha-2 two-letter country codes) — include the full standard lists (well-known, ~184 and ~249 entries). `isValid`:
```php
public static function isValid(string $code): bool
{
    if ($code === 'x-default') return true;
    $parts = explode('-', $code);
    $lang = $parts[0] ?? '';
    if (! in_array($lang, self::LANGUAGES, true)) return false; // lowercase 2-letter, exact match
    $i = 1;
    // optional script subtag: 4 letters, Titlecase (lenient — any 4 alpha with leading uppercase)
    if (isset($parts[$i]) && preg_match('/^[A-Z][a-z]{3}$/', $parts[$i])) $i++;
    // optional region: ISO 3166-1 alpha-2 (uppercase) OR UN M49 numeric (3 digits)
    if (isset($parts[$i])) {
        $region = $parts[$i];
        if (! in_array($region, self::REGIONS, true) && ! preg_match('/^[0-9]{3}$/', $region)) return false;
        $i++;
    }
    return ! isset($parts[$i]); // no leftover subtags
}
```
Ensure the `LANGUAGES`/`REGIONS` arrays contain lowercase language codes and uppercase region codes so the case checks are exact (`en` valid, `EN`/`en-gb` invalid).

- [ ] **Step 4: GREEN + Pint + commit.**
Run: `ddev php artisan test --filter=HreflangCodesTest` → PASS.
Run: `ddev exec vendor/bin/pint app/Crawler/HreflangCodes.php tests/Unit/Crawler/HreflangCodesTest.php`.
```bash
git add app/Crawler/HreflangCodes.php tests/Unit/Crawler/HreflangCodesTest.php
git commit -m "feat(crawler): HreflangCodes BCP-47 validator (ISO 639-1 + 3166-1)"
```

---

## Task 3: `HreflangAnalyzer` + wiring

**Files:** Create `app/Crawler/Analyzers/HreflangAnalyzer.php`, `tests/Unit/Crawler/HreflangAnalyzerTest.php`; Modify `app/Crawler/PageAnalyzer.php`, `app/Observers/CrawlPageObserver.php`.

**Interfaces:** Consumes `HreflangCodes` (T2), `PageContext::$linkHeader` + the 6 per-page `IssueCode`s (T1). Produces `data['hreflang']` (the resolved annotation array) persisted to the column.

- [ ] **Step 1: Write `HreflangAnalyzerTest` first** with fixtures covering: a valid multilingual set (self + x-default, all valid codes, same host) → no issues + `data['hreflang']` populated; an invalid code → `hreflang_incorrect_codes`; a duplicated language → `hreflang_multiple_entries`; a `<link hreflang>` in `<body>` → `hreflang_outside_head`; a set with no self entry → `hreflang_missing_self_reference`; a set with no `x-default` → `hreflang_missing_x_default`; a page whose `<link rel=canonical>` points elsewhere while carrying hreflang → `hreflang_not_using_canonical`; a Link-header-only annotation set → parsed into `data['hreflang']`. Use `runAnalyzer()` as the helper name (NOT `run()` — collides with PHPUnit's `TestCase::run()`). Build the analyzer with a `PageContext` whose `url`/`scheme`/`linkHeader` you set per case.

- [ ] **Step 2: RED.** `ddev php artisan test --filter=HreflangAnalyzerTest` → fails.

- [ ] **Step 3: Implement `HreflangAnalyzer`** (`implements Analyzer`). Algorithm:
  - Collect DOM annotations: `$dom->filter('link[rel="alternate"][hreflang]')->each(...)` → `{ lang: trim(hreflang), href: resolveAbs(trim(href), $ctx->url) }`, skipping empty href/hreflang.
  - Out-of-head: compare `$dom->filterXPath('//link[@rel="alternate" and @hreflang]')->count()` to `//head//link[@rel="alternate" and @hreflang]` — if the former is greater → `HreflangOutsideHead` (mirror `CanonicalAnalyzer`).
  - Parse `$ctx->linkHeader` (RFC 8288): match entries `/<([^>]*)>\s*;\s*([^,]+)/` across the comma-separated list; for each, only keep it if its params contain `rel="alternate"` (or `rel=alternate`) AND a `hreflang="xx"` (or `hreflang=xx`) param — extract lang + resolve the URL absolute. Malformed entries are ignored.
  - Merge DOM + header annotations; de-dup by `lang.'|'.href`.
  - Per-page issues (only when the merged set is non-empty for 4–6):
    1. any `! HreflangCodes::isValid($lang)` → `HreflangIncorrectCodes`
    2. any `lang` appearing more than once → `HreflangMultipleEntries`
    3. (out-of-head handled above) → `HreflangOutsideHead`
    4. no annotation href normalizes to `$ctx->url` → `HreflangMissingSelfReference`
    5. no annotation `lang === 'x-default'` → `HreflangMissingXDefault`
    6. the page's own `link[rel=canonical]` first href resolves to a URL whose normalized path differs from `$ctx->url` → `HreflangNotUsingCanonical` (reuse the same normPath/differs logic as `CanonicalAnalyzer`)
  - `$r->data['hreflang'] = $annotations ?: null;` (array of `{lang, href}`) so the observer persists it.
  - Add a private `resolveAbs(string $href, string $base): string` (absolute-resolve relative/protocol-relative/root-relative hrefs; reuse the resolution approach used elsewhere — mirror `AggregateCrawlJob::resolveUrl`/`LinkExtractor`) and a `normPath()` matching `CanonicalAnalyzer`. For self-reference matching, normalize both sides by host+path (case-insensitive host, trailing-slash-insensitive path).

- [ ] **Step 4: Register in `PageAnalyzer`.** Add `use App\Crawler\Analyzers\HreflangAnalyzer;` and `new HreflangAnalyzer,` to the `analyzers()` array (e.g. after `PaginationAnalyzer`).

- [ ] **Step 5: Observer wiring.** In `CrawlPageObserver::recordResponse`, capture the Link header from the already-computed `$lower` map (`$lower['link'][0] ?? null`) and pass it as the new 7th arg to `new PageContext(... $scheme, $linkHeader)`. `data['hreflang']` flows to the column automatically via the existing `array_merge($data, [...])` in `create()` (same as `pagination_next/prev`) — no extra persistence code needed; confirm by reading the create() call.

- [ ] **Step 6: GREEN + Pint + commit.**
Run: `ddev php artisan test --filter=HreflangAnalyzerTest` → PASS.
Run: `ddev php artisan test --filter=Crawler` → PASS (no regressions).
Run: `ddev exec vendor/bin/pint app/Crawler app/Observers tests`.
```bash
git add app/Crawler/Analyzers/HreflangAnalyzer.php tests/Unit/Crawler/HreflangAnalyzerTest.php app/Crawler/PageAnalyzer.php app/Observers/CrawlPageObserver.php
git commit -m "feat(crawler): HreflangAnalyzer (DOM + Link header) with per-page checks"
```

---

## Task 4: cross-page hreflang checks in `AggregateCrawlJob`

**Files:** Modify `app/Jobs/AggregateCrawlJob.php`; Test: `tests/Feature/Crawler/` (add a hreflang aggregate test, e.g. `HreflangAggregateTest.php`).

**Interfaces:** Consumes the stored `crawl_pages.hreflang` (T3) + the 6 cross-page `IssueCode`s (T1).

- [ ] **Step 1: Write the aggregate feature test first** (`tests/Feature/Crawler/HreflangAggregateTest.php`). Seed one crawl with same-host pages forming a cluster on `https://example.com` (e.g. `/en/` and `/de/`), setting each `CrawlPage`'s `hreflang`, `canonical`, `is_indexable`, `status_code`, and inlinks/links as needed, then run `AggregateCrawlJob` and assert the expected codes land in the right pages' `issues`:
  - reciprocal valid cluster (each references the other + self + x-default, both 200, indexable, linked) → none of the cross-page codes.
  - A references B but B omits A → `hreflang_missing_return_link` on A.
  - B is `is_indexable = false` → `hreflang_noindex_return_link` on A.
  - B's `status_code = 404` → `hreflang_non_200` on A.
  - B's `canonical` differs from the URL A referenced → `hreflang_non_canonical_return_link` on A.
  - A declares itself `en` and declares B `de`, but B's return entry for A says `fr` → `hreflang_inconsistent_language`.
  - a same-host hreflang target with zero `<a>` inlinks → `hreflang_unlinked` on that target.
  - an off-host target (`https://example.de/…`, faked non-200 via `Http::fake` — resolvable host) → `hreflang_non_200`.
  Use resolvable public hosts, never `.test`/`.example` TLDs (see Global Constraints).

- [ ] **Step 2: RED.** Run the new test → fails.

- [ ] **Step 3: Implement.** In `handle()`:
  - Extend the page `select` (currently includes `canonical`, `pagination_next/prev`, `status_code`, `is_indexable`) to also load `hreflang`.
  - Build, alongside the existing `$pageByUrl`, a hreflang view: for each page store in its `$pageByUrl` entry `canonical => $norm(resolved canonical)|null`, `hreflang => array of {lang, hrefNorm}` (normalize each href with `$norm`), and `selfLang => the lang of the entry whose hrefNorm === norm(page url), or null`. Also build a set `$hreflangReferenced = [normUrl => true]` of every same-host URL referenced by any annotation, and collect off-host target URLs.
  - Off-host probe: dedupe off-host target URLs, probe with `LinkStatusChecker` (SSRF-safe, capped exactly like `checkBrokenLinks`/`checkResources`), into `$externalStatus[normUrl] = int|null`.
  - In the per-page `chunkById` pass (where canonical/pagination cross-page checks already run), for a page with annotations, iterate its annotations `{lang, hrefNorm}` (skip the self entry for reciprocity checks) and append to `$issues`:
    - **same-host** (`isset($pageByUrl[$hrefNorm])`): let `$t = $pageByUrl[$hrefNorm]`.
      - `$t['status'] !== null && $t['status'] !== 200` → `hreflang_non_200`.
      - `$t['indexable'] === false` → `hreflang_noindex_return_link`.
      - `$t['canonical'] !== null && $t['canonical'] !== $hrefNorm` → `hreflang_non_canonical_return_link`.
      - target has no entry pointing back to `norm($page->url)` → `hreflang_missing_return_link`.
      - target's return entry for `norm($page->url)` exists and its lang differs from this page's `selfLang` (when both are known) → `hreflang_inconsistent_language`.
    - **off-host** (`isset($externalStatus[$hrefNorm])`): status set and `!== 200` → `hreflang_non_200`.
  - After the annotation loop, if `norm($page->url)` is in `$hreflangReferenced` AND the page's normal `<a>` inlink count (from the existing `$inlinks` map) is 0 → `hreflang_unlinked`.
  - Use `$issues` (the `->flip()` collection) exactly like the surrounding canonical/pagination code; codes are added once. Keep the summary counting consistent with how other codes are tallied.

- [ ] **Step 4: GREEN + regression + Pint + commit.**
Run: `ddev php artisan test --filter="Hreflang|AggregateCrawl|CrawlController"` → PASS.
Run: `ddev exec vendor/bin/pint app/Jobs tests`.
```bash
git add app/Jobs/AggregateCrawlJob.php tests/Feature/Crawler/HreflangAggregateTest.php
git commit -m "feat(crawler): cross-page hreflang cluster checks + off-host status probe"
```

---

## Task 5: UI + lang keys

**Files:** Modify `resources/js/lib/crawlerCategories.ts`, `app/Http/Controllers/CrawlController.php`, `resources/js/Pages/Crawls/Page.tsx`, `lang/en/crawler.php`, `lang/de/crawler.php`.

- [ ] **Step 1: `CATEGORY_ORDER`.** Insert `'hreflang'` immediately after `'pagination'` in `resources/js/lib/crawlerCategories.ts`. (Show sidebar + page-detail checks table pick it up automatically.)

- [ ] **Step 2: pageDetail payload.** In `CrawlController::pageDetail`, add `'hreflang' => $pageModel->hreflang ?? [],` to the `page` array (near `structured_data`).

- [ ] **Step 3: `Page.tsx` — type + card.** Add `hreflang: { lang: string; href: string }[];` to the `CrawlPageDetail` interface. Add a `hreflangCard` builder (near the other cards): shown only when `page.hreflang.length > 0`; a small table (reuse the existing card/`<dl>`/table styling) listing each `lang → href` (href as a truncating link). Place `{isHtml && page.hreflang.length > 0 && hreflangCard}` in the overview grid (e.g. after `metaCard`). Heading `t('crawler.hreflang')`. Do NOT add hreflang codes to `EVIDENCE_CODES` — they are detail-less and must surface in the checks table under the Hreflang group.

- [ ] **Step 4: Lang keys (BOTH en + de).** Add:
  - `category_group.hreflang` → EN "Hreflang" / DE "Hreflang".
  - `hreflang` (card heading) → EN "Hreflang annotations" / DE "Hreflang-Angaben".
  - `issue.<code>` for all 12 codes (short label) and `issue_help.<code>` for all 12 (one-sentence fix guidance), EN + DE. Follow the phrasing style of the existing `issue.*`/`issue_help.*` entries. Grep the files first to place them in the correct nested arrays and to confirm no key collision.

- [ ] **Step 5: Build + regression + Pint + commit.**
Run: `ddev npm run build` → green.
Run: `ddev php artisan test --filter="CrawlController|Hreflang"` → PASS.
Run: `ddev exec vendor/bin/pint --test lang app/Http/Controllers/CrawlController.php`.
```bash
git add resources/js/lib/crawlerCategories.ts app/Http/Controllers/CrawlController.php resources/js/Pages/Crawls/Page.tsx lang/en/crawler.php lang/de/crawler.php
git commit -m "feat(crawler-ui): hreflang category, annotations card + lang keys"
```

---

## Final verification (whole plan)

- [ ] `ddev php artisan test` — full suite green (bijection/severity invariants + new analyzer/aggregate tests).
- [ ] `ddev npm run build` — green.
- [ ] `ddev exec vendor/bin/pint --test` — clean.
- [ ] Manual/live: crawl a multilingual same-host site → the Show sidebar shows a **Hreflang** category with counts; a page detail shows the hreflang annotations card + any hreflang findings in the Checks table.

## Self-Review notes (author)

- **Spec coverage:** all 12 checks — per-page 1–6 (T3), cross-page 7–12 (T4); category/codes/column (T1); validator (T2); UI/lang (T5). ✓
- **Invariant safety:** severities listed once in Global Constraints and reused in T1 Steps 3+5 so `severity()` and `CheckCatalog` agree; bijection test is the gate. ✓
- **Same-host / off-host split** honored: T4 same-host cluster checks + off-host status-only probe. ✓
- **Test-host constraint** (resolvable hosts) called out in T4. ✓
- **`runAnalyzer()` naming** flagged (PHPUnit `run()` collision — a recurring past defect). ✓
- **Detail-less** hreflang codes flow to the checks table (T5 note not to add to EVIDENCE_CODES); annotations get a dedicated evidence card. ✓
