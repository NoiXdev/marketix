# Crawler Phase 2 — URL checks — Design

**Status:** approved for planning
**Depends on:** Phase 0 (catalogue/tabs framework) and Phase 1 (security). This phase
flips the **url** category from planned to active.

## Goal

Implement 8 of the 11 `url` checks as pure-string analysis of every crawled URL, so the
URL tab on the crawl overview lists affected URLs and its per-check dropdown works. Three
checks stay planned (greyed). No new crawl data, no migration, no new severity, no
frontend changes.

## Scope

**In (8 checks flip planned→active, all `notice`):**
`url_non_ascii`, `url_underscores`, `url_uppercase`, `url_contains_space`,
`url_multiple_slashes`, `url_repetitive_path`, `url_ga_tracking_params`,
`url_over_115_chars`.

**Deferred (stay `planned`/greyed — user decision):**
- `url_broken_bookmark` — a `#fragment` link that doesn't resolve to an id/name on the
  target page. That is a link + target-DOM check, not a pure string check on the page's
  own URL (crawlers strip fragments). Belongs to a later link-analysis phase.
- `url_parameters` — fires on any `?query`; too noisy on faceted/filter sites.
- `url_internal_search` — needs an agreed heuristic pattern set; deferred until defined.

**Out:**
- Every other planned category — later phases.
- No decoding of percent-encoded paths (a `%C3%A9` path is ASCII and not flagged as
  non-ASCII; a raw non-ASCII byte is). Crawled URLs are normally already normalised.

## Global constraints (carried)

- Bijection stays exact: active catalogue codes === `IssueCode` cases. This phase adds 8
  `IssueCode` cases and flips 8 `url` catalogue entries planned→active, so both grow
  **35 → 43**.
- `IssueCode::category()` and `::severity()` stay exhaustive `match` (no default arm);
  the 8 new cases get arms in both (`category()` → `Url`, `severity()` → `notice`).
- The 8 `url` lang labels already exist (Phase 0, greyed). Do not rename; no lang change.
- No new crawl-time network requests. Run via DDEV. Frontend gate `ddev npm run build`
  (no changes expected, but the url tab must still build). Pint clean.

## Coverage & placement

Per the user decision: URL checks run for **every** crawled resource (HTML pages,
images, PDFs, media) — URL hygiene applies to all assets. They are pure string
functions with no DOM dependency, so they live in a static `UrlChecker` called from
`CrawlPageObserver::recordResponse()` — the same placement as the Phase-1
`wrong_content_type` sniff, which already runs for both the HTML and non-HTML branches.

`UrlChecker::issues(string $url): array` returns a list of `IssueCode` string values.
`recordResponse()` merges them into `$issues` before persisting; the existing
`array_values(array_unique($issues))` dedups. The URL analysed is the page's crawled
`url` (not `final_url`) — these describe "this URL as linked/crawled".

## Check definitions

`$path = parse_url($url, PHP_URL_PATH) ?? ''` and `$query = parse_url($url, PHP_URL_QUERY) ?? ''`.
Character-hygiene checks read the **path** only (query strings legitimately carry mixed
case and encoded characters — tokens, signatures — and the host/scheme are not
per-page SEO-controllable). Tracking-params read the **query**. Length reads the whole
URL.

| Code | Severity | Fires when | Reads |
|---|---|---|---|
| `url_non_ascii` | notice | `preg_match('/[^\x00-\x7F]/', $path)` — path has a byte > 0x7F | path |
| `url_underscores` | notice | `str_contains($path, '_')` | path |
| `url_uppercase` | notice | `preg_match('/[A-Z]/', $path)` | path |
| `url_contains_space` | notice | `str_contains($path, ' ')` or `stripos($path, '%20') !== false` | path |
| `url_multiple_slashes` | notice | `str_contains($path, '//')` (PHP_URL_PATH excludes the `://`) | path |
| `url_repetitive_path` | notice | two adjacent identical non-empty path segments (`/blog/blog/`) | path |
| `url_ga_tracking_params` | notice | the parsed query has any key in {`utm_source`, `utm_medium`, `utm_campaign`, `utm_term`, `utm_content`, `gclid`, `fbclid`, `mc_cid`, `mc_eid`} | query |
| `url_over_115_chars` | notice | `mb_strlen($url) > 115` | whole URL |

Notes:
- `url_repetitive_path`: split the path on `/`, drop empty segments, flag if any segment
  equals the immediately preceding one. `/a/b/a/` does NOT fire (non-adjacent); `/a/a/`
  does.
- `url_ga_tracking_params`: `parse_str($query, $params)` then
  `array_intersect_key($params, array_flip($known)) !== []`. Keys are matched
  case-sensitively (the tracking params are lower-case by convention).
- Each code is emitted at most once per page (return a deduped list; the observer also
  dedups).
- A root URL with no path (`https://example.com`) yields `$path === ''` → no path checks
  fire.

## Files

**Create**
- `app/Crawler/UrlChecker.php` — the static checker.
- `tests/Unit/Crawler/UrlCheckerTest.php`.

**Modify**
- `app/Crawler/IssueCode.php` — 8 cases + `category()`/`severity()` arms.
- `app/Crawler/CheckCatalog.php` — flip the 8 `url` entries planned→active (leave the 3
  deferred as planned).
- `app/Observers/CrawlPageObserver.php` — call `UrlChecker::issues($url)` and merge into
  `$issues` (runs for both branches, before the create).
- `tests/Unit/Crawler/CheckCatalogTest.php` — url active count 8; bijection 43.
- `tests/Feature/Crawler/CrawlControllerTest.php` — the url tab lists a page with a URL
  issue; a clean URL is not listed under the url group.

**No changes:** migrations, models, `PageContext`, `PageAnalyzer`/analyzers, frontend,
lang. The url category tab already exists (Phase 0 `CATEGORY_ORDER`) and lights up
automatically once the catalogue has active url checks; `notice` severity already
renders.

## Data flow

1. `recordResponse` computes `$issues` (status/redirect/wrong_content_type as today),
   then merges `UrlChecker::issues($url)` — for both HTML and non-HTML branches.
2. Codes persist in `crawl_pages.issues`.
3. `CrawlController::show()` groups the catalogue (url now has 8 active checks) and
   filters by `group=url` / `issue=<code>` exactly as for every other active category.
4. `Show.tsx` renders the URL tab (already present) with the affected-URL table +
   dropdown; the Phase-0 "no issues found" empty-state applies when a crawl has none.

## Testing

- **`UrlCheckerTest`** (unit, pure function): one case per active check firing, plus a
  clean URL (`https://example.com/blog/post-1`) that fires nothing; assert path-only
  scoping (a query like `?ref=AbC_Def` on a clean lowercase path does NOT trigger
  uppercase/underscore); assert a deferred code (e.g. `url_parameters`) is never emitted;
  assert `url_over_115_chars` boundary (115 = no, 116 = yes).
- **`CheckCatalogTest`**: url active count 8; bijection cardinality 43; the 3 deferred url
  codes remain planned.
- **Observer feature test**: a page crawled at `https://example.com/Foo_Bar` persists
  `url_uppercase` + `url_underscores` in its `issues`; an image URL with a space is also
  flagged (proves non-HTML coverage).
- **Controller feature test**: `group=url` lists a page with a url issue; a clean page is
  excluded.
- Gates: crawler namespace green, full suite green, Pint clean, `ddev npm run build`
  green.

## Risks / decisions

- **Percent-encoded paths not decoded** — a deliberate simplification; documented above.
- **Path-only hygiene scoping** — the key false-positive guard (query tokens, host
  casing excluded). Explicitly tested.
- **`url_over_115_chars` uses `mb_strlen`** (character count, not bytes) to match how a
  human reads URL length.
- **Deferred trio stays greyed** — `url_broken_bookmark` in particular needs link+DOM and
  is out of the observer-string model; revisit in a link phase.
