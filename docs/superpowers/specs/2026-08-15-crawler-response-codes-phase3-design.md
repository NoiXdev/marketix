# Crawler Phase 3 — Response-code refinements — Design

**Status:** approved for planning
**Depends on:** Phase 0 (framework), Phase 1 (security), Phase 2 (url). This phase
activates 5 more `response_codes` checks and refines the existing `redirect_chain`.

## Goal

Refine the crawler's response-code signals: distinguish a single internal redirect from
a multi-hop chain, detect redirect *types* (HTTP `Refresh` header, HTML meta refresh),
detect redirect loops, and split "no response" from "server error". The Response-Codes
tab gains 5 active checks; `redirect_chain`'s threshold is corrected to mean an actual
chain.

## Scope

**In (5 checks flip planned→active):**
- `internal_redirect_3xx` (notice) — exactly ONE internal redirect hop.
- `internal_http_refresh_redirect` (warning) — response carries a `Refresh:` header.
- `internal_meta_refresh_redirect` (warning) — HTML has `<meta http-equiv="refresh">`.
- `internal_redirect_loop` (error) — a URL repeats in the redirect chain, or the fetch
  failed with "too many redirects".
- `internal_no_response` (error) — a fetch failure with no HTTP response (DNS failure,
  timeout, connection refused) — previously mis-flagged as `server_error`.

**Behavior change (existing active check):**
- `redirect_chain` (stays active, severity stays `warning`) now fires only for **2+**
  redirect hops. Previously it fired on any redirect (≥1 hop); the single-hop case is now
  `internal_redirect_3xx`. The two are mutually exclusive. The stored `redirect_chain`
  DB column (the hop URLs) is unchanged — only the emitted *issue* changes.

**Deferred (stay planned/greyed — user decision):**
- `internal_success_2xx` — fires on every healthy page; that is the "All URLs" tab, not a
  problem list.
- `internal_js_redirect` — needs JS execution; a raw-HTML heuristic is unreliable/noisy.
- `external_server_error_5xx` — overlaps the active `broken_link` (already flags 4xx/5xx
  outlinks).
- `internal_blocked_resource` — overlaps the active `robots_blocked`.

**Out:** no new severity, no migration, no frontend or lang changes (the Response-Codes
tab already exists; the 5 labels already exist greyed from Phase 0). No new network
requests — everything reads the response/redirect data already captured.

## Global constraints (carried)

- Bijection stays exact: active catalogue codes === `IssueCode` cases. This phase adds 5
  `IssueCode` cases and flips 5 `response_codes` catalogue entries planned→active, so both
  grow **43 → 48**.
- `IssueCode::category()` and `::severity()` stay exhaustive `match` (no default arm); the
  5 new cases get arms in both (`category()` → `ResponseCodes`; severities per the table).
- The 5 response_codes lang labels already exist (Phase 0) — no rename, no lang change.
- Run via DDEV. Frontend gate `ddev npm run build` (no change expected). Pint clean.

## Context: how redirects are captured today

`RunCrawlJob` configures Guzzle with `allow_redirects.track_redirects = true`. After a
followed redirect, the final response carries `X-Guzzle-Redirect-History` (the target
URLs). `CrawlPageObserver::redirects($url, $headers)` returns `[$chain, $finalUrl]` where
`$chain = [$url, ...targets]` (origin first), or `[[], null]` when there was no redirect.
The number of **hops** = `max(0, count($chain) - 1)`.

Guzzle does NOT follow HTTP `Refresh` headers or HTML meta refreshes (both are
client-side), so such a page is recorded with its own 2xx status and the refresh
directive still present in its headers/body — detectable directly. A true redirect loop
exceeds Guzzle's redirect limit and throws `TooManyRedirectsException`, routing to
`crawlFailed()` with a null response.

All `CrawlPage` rows are internal by definition (external URLs live only in `CrawlLink`),
so the `internal_` checks need no host test.

## Component: `RedirectClassifier`

A new `App\Crawler\RedirectClassifier` with two pure static methods — all response-code
logic in one testable place.

```php
/**
 * Issues for a SUCCESSFUL (crawled) response.
 *
 * @param  string[]  $chain           [$url, ...redirect targets] or [] when no redirect
 * @param  array<string, string[]>  $lowerHeaders  headers with lower-cased keys
 * @return string[]  IssueCode values
 */
public static function issues(array $chain, array $lowerHeaders, string $body, bool $isHtml): array
```

Logic:
- `$hops = max(0, count($chain) - 1)`.
- `$hops >= 2` → `redirect_chain`; `elseif $hops === 1` → `internal_redirect_3xx`. (0 hops → neither.)
- `count(array_unique($chain)) !== count($chain)` (a URL repeats) → `internal_redirect_loop`.
- `isset($lowerHeaders['refresh'])` → `internal_http_refresh_redirect`.
- `$isHtml && preg_match('/<meta[^>]+http-equiv\s*=\s*["\']?\s*refresh/i', $body)` → `internal_meta_refresh_redirect`.

```php
/**
 * Issues for a FAILED fetch (crawlFailed).
 *
 * @return string[]  IssueCode values
 */
public static function failureIssues(?int $status, bool $tooManyRedirects): array
```

Logic (mutually exclusive, in order):
- `$tooManyRedirects` → `[internal_redirect_loop]`.
- `$status === null` → `[internal_no_response]`.
- `$status >= 400 && $status < 500` → `[client_error]`.
- else → `[server_error]`.

## Observer wiring

`CrawlPageObserver::recordResponse()`:
- REMOVE the inline `if (count($chain) > 1) { $issues[] = IssueCode::RedirectChain->value; }`
  (lines ~89-90). Its replacement lives in `RedirectClassifier::issues()`.
- After the branch (where `$category`, `$chain`, `$lower`, `$body` are known), merge:
  ```php
  foreach (RedirectClassifier::issues($chain, $lower, $body, $category === ResourceClassifier::HTML) as $code) {
      $issues[] = $code;
  }
  ```
  (`$lower` is the already-computed lower-cased header array; the existing
  `array_values(array_unique($issues))` dedups.)

`CrawlPageObserver::crawlFailed()`:
- Replace the single-line `issues` computation with:
  ```php
  $status = $requestException->getResponse()?->getStatusCode();
  $tooMany = $requestException instanceof TooManyRedirectsException;
  $page = $this->crawl->pages()->create([
      'url' => $url,
      'status_code' => $status,
      'issues' => RedirectClassifier::failureIssues($status, $tooMany),
  ]);
  ```
- Add `use GuzzleHttp\Exception\TooManyRedirectsException;` and
  `use App\Crawler\RedirectClassifier;`.

## Files

**Create**
- `app/Crawler/RedirectClassifier.php`
- `tests/Unit/Crawler/RedirectClassifierTest.php`

**Modify**
- `app/Crawler/IssueCode.php` — 5 cases + `category()`/`severity()` arms.
- `app/Crawler/CheckCatalog.php` — flip the 5 response_codes entries planned→active.
- `app/Observers/CrawlPageObserver.php` — wire `RedirectClassifier` into `recordResponse`
  and `crawlFailed`; remove the inline redirect_chain.
- `tests/Unit/Crawler/CheckCatalogTest.php` — response_codes active count (9), bijection 48.
- `tests/Feature/Crawler/CrawlControllerTest.php` — the response_codes tab lists a page
  with a redirect/refresh issue.

**No changes:** migrations, models, `PageContext`, analyzers, frontend, lang.

## Data flow

1. `recordResponse` computes `$issues` (status/wrong_content_type/url as today), then
   merges `RedirectClassifier::issues($chain, $lower, $body, $isHtml)` — a single redirect
   → `internal_redirect_3xx`; 2+ → `redirect_chain`; loops/refresh types as detected.
2. `crawlFailed` computes `$issues` via `RedirectClassifier::failureIssues()` — no-response
   and too-many-redirects now distinct from generic 5xx.
3. Codes persist in `crawl_pages.issues`; grouping/filtering by the Response-Codes tab
   works automatically (category already active, 5 more checks now live).

## Testing

- **`RedirectClassifierTest`** (unit, pure): single redirect → `internal_redirect_3xx`
  (not `redirect_chain`); 2-hop chain → `redirect_chain` (not `internal_redirect_3xx`);
  no redirect → neither; duplicate URL in chain → `internal_redirect_loop`; `Refresh`
  header → `internal_http_refresh_redirect`; meta refresh in HTML body →
  `internal_meta_refresh_redirect` (and NOT when `$isHtml` is false, and NOT when absent).
  `failureIssues`: null → `internal_no_response`; tooMany → `internal_redirect_loop`
  (precedence over null); 404 → `client_error`; 503 → `server_error`.
- **`CheckCatalogTest`**: response_codes active count is 9 (4 prior + 5); the 4 deferred
  response_codes codes remain planned; bijection cardinality 48.
- **Observer feature test** (extend an existing observer test or add one): a response with
  a single-hop `X-Guzzle-Redirect-History` persists `internal_redirect_3xx`; a `Refresh`
  header persists `internal_http_refresh_redirect`; an HTML body with a meta refresh
  persists `internal_meta_refresh_redirect`.
- **Controller feature test**: `group=response_codes` lists a page carrying
  `internal_meta_refresh_redirect`.
- Gates: crawler namespace green, full suite green, Pint clean, `ddev npm run build` green.

## Risks / decisions

- **`redirect_chain` threshold change** (≥1 → ≥2 hops) is the one behavior change to an
  active check. No existing test pins the old threshold; the new behavior is tested. Users
  who previously saw single 301s under `redirect_chain` now see them under
  `internal_redirect_3xx` (a lower-severity notice) — the intended refinement.
- **`crawlFailed` previously flagged null-response failures as `server_error`.** They now
  become `internal_no_response`. This is the intended correction; `server_error` remains
  for actual 5xx responses (handled in `recordResponse`, unchanged).
- **Loop precedence:** a too-many-redirects failure yields `internal_redirect_loop`, not
  `internal_no_response`, even though the response is null — the loop is the root cause.
- **Meta-refresh via regex** (not DOM): a targeted presence regex on the raw HTML, matching
  the observer's existing `wrong_content_type` sniff style; only run for HTML pages.
- **Deferred trio/quartet stays greyed** — success_2xx (noise), js_redirect
  (unreliable), external_5xx and blocked_resource (overlap active checks).
