# Crawler Phase 1 — Security checks — Design

**Status:** approved for planning
**Depends on:** Phase 0 (`2026-08-14-crawler-issue-categories-design.md`) — the check
catalogue, `IssueCategory`, category tabs, and per-check dropdown already ship. This
phase flips the **security** category from planned to active.

## Goal

Implement the 13 `security` checks so the **Security** tab on the crawl overview lists
the affected URLs and its per-check dropdown works — the first category to go from
fully greyed to live. Capture response headers at crawl time (new column) and add one
`SecurityAnalyzer` covering both header- and DOM-based checks.

## Scope

**In:** all 13 security checks become `active`; a `security_headers` JSON column on
`crawl_pages`; header routing into the analyzer pipeline; a new `SecurityAnalyzer`; an
observer-level `wrong_content_type` body-sniff; a new `info` severity for positive
informational checks (`https_urls`); catalogue/enum/lang/count updates; tests.

**Out:**
- The page **detail** view stays unchanged this phase. The `security_headers` column is
  captured now so a future Security panel needs no re-crawl, but no detail-view UI ships
  here.
- Every other planned category (url, response_codes, …) stays greyed — later phases.
- No new crawl-time network requests: every check reads the response we already fetch
  (headers + body) for each page.

## Global constraints (carried from Phase 0)

- The catalogue ↔ `IssueCode` **bijection** stays exact: active catalogue codes ===
  `IssueCode` cases. This phase moves 13 codes from planned to active, so active count
  goes **22 → 35**, and 13 new `IssueCode` cases are added. The bijection test updates
  accordingly.
- `IssueCode::category()` and `::severity()` are exhaustive `match` over all cases (no
  default arm) — the 13 new cases get arms in both.
- Every catalogue code has a lang label in `lang/en` and `lang/de`. The 13 security
  labels already exist (added greyed in Phase 0); this phase keeps them (they are the
  real labels, no "(planned)" suffix is stored — the suffix is applied in the UI only).
- Run via DDEV (`ddev php`, `ddev npm`, …). Frontend gate is `ddev npm run build`
  (lint is broken). Backend gate is the crawler test namespace + full suite; Pint clean.

## The `info` severity

Phase 0 severities are `error | warning | notice` — all of which mean "a problem".
`https_urls` is a positive/neutral signal (the page IS served over HTTPS), so it does
not fit "issues = problems". Rather than drop it, add a fourth severity `info` with
these rules:

- **Filterable, not counted.** An `info` code is a real emitted `IssueCode` and appears
  in the category's per-check dropdown with its `(N)` count, so a user can list "all
  HTTPS URLs" exactly like Screaming Frog's filter.
- **Excluded from problem counts.** `info` codes do **not** contribute to:
  - the category problem badge (`perCategory` count in `CrawlController::show()`),
  - the overview summary tiles (`$summary` in `AggregateCrawlJob`),
  - severity colouring (rendered neutral, never red/yellow).
- The per-code count (`perCode` in the controller, used by the dropdown) **includes**
  `info` codes.

### Where the `info` rule lives (exact touch-points)

- `IssueCode::severity()` — `HttpsUrls` returns `'info'`; the other 12 security codes
  return the severities in the check table below.
- `CheckCatalog` — the `https_urls` entry's `severity` becomes `'info'`; all 13 security
  entries flip `status` P → A.
- `CrawlController::show()` — when building `perCategory`, skip a code whose
  `IssueCode::from($code)->severity() === 'info'` (dropdown `perCode` still counts it).
  A helper `IssueCode::isInfo()` (or a `CheckCatalog::isProblem(code)`) keeps this in one
  place; the controller and the aggregate job both call it.
- `AggregateCrawlJob` — when accumulating `$summary` (the per-code map for the overview
  tiles), skip `info` codes so `https_urls` never appears as a "problem" tile.
- Frontend: `CatalogCheck.severity` TS union gains `'info'`; the Page-detail severity
  colour map (`issue_severities`) renders `info` neutral. The category-tab count logic
  (`hasActiveChecks`, dimming) is unaffected — it keys on `status`, not severity.

## Header capture

`CrawlPageObserver::recordResponse()` already receives `array $headers`
(`array<string, string[]>`). Today only `Content-Type` is read. Add:

- **Migration** `add_security_headers_to_crawl_pages`: nullable `json security_headers`
  column on `crawl_pages`. Add `security_headers` to the model `$fillable`/`$casts`
  (`'array'`).
- In `recordResponse`, before analysis, extract a **normalised, lower-cased-key** subset
  of the security-relevant headers and store it:
  `content-security-policy`, `x-frame-options`, `x-content-type-options`,
  `strict-transport-security`, `referrer-policy`, `content-type`. Store only these keys
  (not the full header bag) to bound row size. Missing header → key absent.
- Header lookups are case-insensitive: normalise `$headers` keys to lower-case once.

## Routing headers into the analyzer

`PageContext` gains the data the `SecurityAnalyzer` needs:

```php
class PageContext {
    public function __construct(
        public string $url,
        public int $statusCode,
        public string $baseHost,
        public bool $robotsBlocked = false,
        /** normalised lower-case header name => first value */
        public array $securityHeaders = [],
        public string $scheme = 'https', // parsed from $url; drives scheme-aware checks
    ) {}
}
```

`recordResponse` builds `$securityHeaders` (the same normalised subset it stores) and
`$scheme` (`parse_url($url, PHP_URL_SCHEME)`), and passes both into the `PageContext` it
already constructs for the HTML branch.

## `SecurityAnalyzer`

A new `App\Crawler\Analyzers\SecurityAnalyzer implements Analyzer`, registered in
`PageAnalyzer::analyzers()`. It reads the DOM (`Symfony\Component\DomCrawler\Crawler`)
and `PageContext` and emits `IssueCode`s. It runs only in the HTML branch (same as the
other analyzers), so all its checks assume an HTML page.

Header-based checks read `$ctx->securityHeaders`; DOM checks walk the DOM; scheme-aware
checks read `$ctx->scheme`. `wrong_content_type` is **not** here (see below).

### Check table (final severities + trigger)

| Code | Severity | Nature | Fires when |
|---|---|---|---|
| `missing_hsts_header` | warning | header | scheme is https AND no `strict-transport-security` |
| `missing_csp_header` | notice | header | no `content-security-policy` |
| `missing_x_content_type_options` | warning | header | no `x-content-type-options` (value ignored; presence check) |
| `missing_x_frame_options` | warning | header+DOM | no `x-frame-options` AND CSP has no `frame-ancestors` directive |
| `missing_referrer_policy` | notice | header+DOM | no `referrer-policy` header AND no `<meta name="referrer">` |
| `http_urls` | warning | scheme | page scheme is http |
| `https_urls` | **info** | scheme | page scheme is https |
| `mixed_content` | warning | DOM | scheme is https AND a subresource (`img/script/@src`, `link[rel=stylesheet]/@href`, `iframe/@src`, `audio/video/source/@src`, `object/@data`) uses an absolute `http://` URL |
| `protocol_relative_resource_links` | notice | DOM | any subresource URL (same element set as mixed_content) starts with `//` |
| `unsafe_cross_origin_links` | warning | DOM | an `<a target="_blank" href="http(s)://otherhost…">` lacks `noopener` AND `noreferrer` in `rel` |
| `form_on_http` | warning | scheme+DOM | scheme is http AND the page has ≥1 `<form>` |
| `form_url_insecure` | warning | DOM | a `<form action="http://…">` (absolute http action, regardless of page scheme) |
| `wrong_content_type` | warning | observer | body sniffs as HTML but declared `content-type` is not an HTML type — see below |

Notes:
- Each code is emitted **at most once per page** (the `PageAnalyzer` already dedups by
  code). A page with 5 mixed-content resources emits `mixed_content` once.
- `mixed_content`, `missing_hsts_header`, `form_on_http`, `https_urls`/`http_urls` are
  scheme-aware; on the "wrong" scheme they simply do not fire.
- Cross-origin comparison for `unsafe_cross_origin_links` uses host inequality against
  `$ctx->baseHost` (subdomains count as cross-origin, matching browser origin rules is
  out of scope — host compare is the pragmatic rule).

### `wrong_content_type` (observer, not analyzer)

A page served with a non-HTML `Content-Type` (e.g. `text/plain`,
`application/octet-stream`) is classified non-HTML by `ResourceClassifier` and skips the
analyzer pipeline entirely — so this check cannot live in `SecurityAnalyzer`. It runs in
`recordResponse`:

- Sniff the first bytes of `$body` (after trimming BOM + leading whitespace,
  case-insensitive): matches `^<!doctype html` or `^<html[\s>]`.
- If it sniffs as HTML **and** the declared content-type's media type is not
  `text/html` or `application/xhtml+xml` → append `wrong_content_type`.
- Only the HTML-sniff direction is checked (mis-declared HTML). The reverse (declared
  HTML, body not HTML) is out of scope — lower value, higher false-positive risk.

## Files

**Create**
- `database/migrations/2026_08_15_000001_add_security_headers_to_crawl_pages.php`
- `app/Crawler/Analyzers/SecurityAnalyzer.php`
- `tests/Unit/Crawler/SecurityAnalyzerTest.php`

**Modify**
- `app/Crawler/IssueCode.php` — 13 new cases; arms in `category()` (→ `Security`) and
  `severity()`; add `isInfo()` (or equivalent).
- `app/Crawler/CheckCatalog.php` — flip 13 security entries P → A; `https_urls`
  severity → `info`; add `isProblem(string $code): bool` helper if chosen over
  `IssueCode::isInfo()`.
- `app/Crawler/PageContext.php` — add `securityHeaders`, `scheme`.
- `app/Crawler/PageAnalyzer.php` — register `SecurityAnalyzer`.
- `app/Observers/CrawlPageObserver.php` — normalise headers; store `security_headers`
  subset; pass headers + scheme into `PageContext`; `wrong_content_type` body-sniff.
- `app/Models/CrawlPage.php` — `security_headers` fillable + array cast.
- `app/Http/Controllers/CrawlController.php` — exclude `info` codes from `perCategory`.
- `app/Jobs/AggregateCrawlJob.php` — exclude `info` codes from `$summary`.
- `lang/en/crawler.php`, `lang/de/crawler.php` — security labels stay; no new keys
  required unless a severity label surface needs `info` (add `severity.info` if such a
  map exists).
- `resources/js/Pages/Crawls/Show.tsx` and the Page-detail severity map — add `'info'`
  to the `severity` union and render it neutral.
- `tests/Unit/Crawler/CheckCatalogTest.php` — bijection now 35; assert security codes
  active; assert `https_urls` severity `info`.
- `tests/Feature/Crawler/CrawlControllerTest.php` — security category now lists affected
  URLs; an `info`-only page is not counted in the category badge but is dropdown-listable.

## Data flow

1. `recordResponse` normalises headers → stores `security_headers` subset → builds
   `PageContext{securityHeaders, scheme}` → runs `wrong_content_type` sniff (appends to
   `$issues`) → runs the analyzer pipeline (HTML branch).
2. `SecurityAnalyzer` emits the 12 header/DOM security codes into the merged result.
3. Codes persist in `crawl_pages.issues` exactly as today.
4. `AggregateCrawlJob` builds `$summary` skipping `info` codes.
5. `CrawlController::show()` groups the catalogue (security now has active checks),
   computes `perCategory` skipping `info` codes and `perCode` including them.
6. `Show.tsx` renders the Security tab: affected-URL table + dropdown; the Phase-0
   "no issues found" empty-state now applies (security became an active category).

## Testing

- **`SecurityAnalyzerTest`** (unit, HTML fixtures + fake `PageContext`): one focused
  case per check — header present/absent, https vs http scheme, a mixed-content page, a
  protocol-relative resource, an unsafe `target=_blank` link, an http form action, a
  form on an http page, and the negative (a fully secure page emits only `https_urls`).
- **Observer test** for `wrong_content_type`: an HTML body served as `text/plain`
  flags it; a normal `text/html` page does not; a real non-HTML file (PNG) does not.
- **`CheckCatalogTest`**: active count 35; the 13 security codes are active;
  `https_urls` severity `info`; `category()`/`severity()` exhaustive (compile-enforced).
- **Controller feature test**: a crawl with a mixed-content page shows it under the
  Security tab and under the `mixed_content` dropdown filter; a secure page appears under
  the `https_urls` filter but is **not** in `catalog.security.count`.
- Gates: crawler namespace green, full suite green, Pint clean, `ddev npm run build`
  green.

## Risks / decisions

- **False positives on header checks behind CDNs/proxies.** Some hosts add security
  headers at the edge our fetch may or may not see; acceptable — the check reflects what
  a crawler (and thus a bot/user agent) actually receives.
- **`info` severity is a model change** touching counting in two backend spots and the
  TS union. Kept to a single predicate (`isInfo`/`isProblem`) to avoid drift.
- **Row size** from `security_headers`: bounded by storing only the 6-key subset.
- **`unsafe_cross_origin_links` is largely obsolete** (browsers default `noopener` since
  2021) — included per user decision; low severity impact (warning), no problem-count
  distortion.
