# Crawler Phase 6 — Canonicals / Pagination — Design

**Status:** approved for planning
**Depends on:** Phases 0-5. Activates all 21 planned `canonicals` + `pagination` checks.

## Goal

Flip the `canonicals` and `pagination` categories fully active: per-page canonical and
`rel=next`/`rel=prev` checks (a new `CanonicalAnalyzer` + `PaginationAnalyzer`), plus six
cross-page checks in `AggregateCrawlJob` (canonical/pagination targets' indexability,
status, and linkage). The existing `canonical_mismatch` stays active and unchanged.

## Scope

**In (21 checks flip planned→active).** Severities are taken as-is from the catalogue
(the 5 informational-positive checks stay `notice` per explicit user decision — expect
high volume).

*Canonicals — per-page (`CanonicalAnalyzer`, 9):* `has_canonical` (notice),
`missing_canonical` (notice), `canonical_self_referencing` (notice), `multiple_canonical`
(warning), `multiple_conflicting_canonical` (warning), `canonical_is_relative` (notice),
`canonical_fragment_url` (notice), `canonical_outside_head` (warning),
`canonical_invalid_attribute` (warning).

*Canonicals — cross-page (`AggregateCrawlJob`, 2):* `non_indexable_canonical` (warning),
`canonical_not_linked` (notice).

*Pagination — per-page (`PaginationAnalyzer`, 6):* `has_pagination` (notice),
`pagination_first_page` (notice), `paginated_2plus` (notice), `multiple_pagination_urls`
(warning), `pagination_url_not_in_anchor` (warning), `pagination_loop` (error).

*Pagination — cross-page (`AggregateCrawlJob`, 4):* `pagination_non_200` (warning),
`pagination_unlinked` (warning), `pagination_non_indexable` (warning),
`pagination_sequence_error` (warning).

**Out:** no new severity; categories/severities/labels already exist (Phase 0) — no
frontend or lang changes. No new network requests (cross-page checks reuse already-crawled
page data).

## Global constraints (carried)

- Bijection stays exact: active catalogue codes === `IssueCode` cases. This phase adds 21
  `IssueCode` cases and flips 21 catalogue entries planned→active, so both grow
  **71 → 92**.
- `IssueCode::category()` and `::severity()` stay exhaustive `match` (no default arm); the
  21 new cases get arms in both. Categories: 11 → `Canonicals`, 10 → `Pagination`.
  Severities per the scope list (match the catalogue; the generalized severity-match test
  from Phase 5 will enforce this).
- Run via DDEV. Frontend gate `ddev npm run build` (no change expected). Pint clean.

## Confirmed definitions (the two judgment calls)

- **`canonical_invalid_attribute`** = a `link[rel="canonical"]` whose `href` is missing,
  empty, or whitespace-only.
- **`pagination_sequence_error`** = this page has a `rel=next` pointing to an internal
  crawled page Q, but Q's `rel=prev` does not point back to this page (broken reciprocity).

## Per-page: `CanonicalAnalyzer`

New `App\Crawler\Analyzers\CanonicalAnalyzer implements Analyzer`, registered in
`PageAnalyzer`. Runs on the HTML DOM + `PageContext`. It does NOT store the canonical
(`MetaAnalyzer` already stores the raw `canonical`); it only emits the per-page canonical
issues. Let `$nodes = $dom->filter('link[rel="canonical"]')` (whole document), `$hrefs` =
their trimmed `href` values, `$first` = the first node's raw href.

- `count($nodes) === 0` → `missing_canonical`; `>= 1` → `has_canonical`.
- `count($nodes) > 1` → `multiple_canonical`; and if the normalized distinct hrefs among
  them number `>= 2` → `multiple_conflicting_canonical`.
- When a first canonical exists and its href is non-empty:
  - not `differs($first, $ctx->url)` (same page, path-normalized like the existing
    `canonical_mismatch`) → `canonical_self_referencing`.
  - `href` does not match `#^https?://#i` → `canonical_is_relative`.
  - `href` contains `#` → `canonical_fragment_url`.
- Any canonical node whose `href` is missing/empty/whitespace → `canonical_invalid_attribute`.
- A canonical link outside `<head>` (`filterXPath('//link[@rel="canonical"]')` count >
  `//head//link[@rel="canonical"]` count) → `canonical_outside_head`.

## Per-page: `PaginationAnalyzer`

New `App\Crawler\Analyzers\PaginationAnalyzer implements Analyzer`, registered in
`PageAnalyzer`. Reads `link[rel="next"]` / `link[rel="prev"]`, resolves their hrefs to
absolute URLs (using `$ctx->url` as base, mirroring `LinkExtractor::resolve`), and stores
the **resolved** first next/prev as `$data['pagination_next']` / `$data['pagination_prev']`
(null when absent). Emits:

- `next` or `prev` present → `has_pagination`.
- `next` present AND `prev` absent → `pagination_first_page`.
- `prev` present → `paginated_2plus`.
- more than one `rel=next` OR more than one `rel=prev` link → `multiple_pagination_urls`.
- a resolved next/prev URL not also present among the page's `<a href>` (resolved)
  targets → `pagination_url_not_in_anchor`.
- a resolved next/prev URL equal (path-normalized) to `$ctx->url` → `pagination_loop`.

## Storage

- **Migration** `add_pagination_urls_to_crawl_pages`: nullable `text pagination_next` and
  `text pagination_prev` on `crawl_pages`. Persist via the observer's `array_merge($data,
  …)` once `PaginationAnalyzer` adds them (model is `$guarded = ['id']`; no cast needed).
- `canonical` is already a column (stored by `MetaAnalyzer`).

## Cross-page: `AggregateCrawlJob`

Add `canonical`, `pagination_next`, `pagination_prev`, `status_code`, `is_indexable` to the
`identities` `->select([...])`. Build a `$pageByUrl` map: for every page, both `$norm(url)`
and `$norm(final_url)` → `['status' => status_code, 'indexable' => (bool) is_indexable,
'next' => $norm(pagination_next), 'prev' => $norm(pagination_prev)]`.

In the per-page chunk loop (full models available), resolve the page's canonical to
absolute (`resolveUrl($page->canonical, $page->url)`, a small helper mirroring
`LinkExtractor::resolve`; pagination next/prev are already stored resolved), then:

- **`non_indexable_canonical`**: canonical resolves to a page in `$pageByUrl` whose
  `indexable === false`.
- **`canonical_not_linked`**: canonical is non-empty and its normalized form is NOT a key
  in `$pageByUrl` (target was not crawled — effectively unlinked for a same-site crawl).
- **`pagination_non_200`**: a next/prev in `$pageByUrl` with `status !== 200`.
- **`pagination_unlinked`**: a next/prev that is non-null and NOT a key in `$pageByUrl`.
- **`pagination_non_indexable`**: a next/prev in `$pageByUrl` whose `indexable === false`.
- **`pagination_sequence_error`**: the page's `next` is in `$pageByUrl` and that target's
  stored `prev` (normalized) `!== $norm($page->url)` (broken reciprocity).

Notes: cross-page checks only meaningfully apply to internal, crawled targets — an
external or uncrawled canonical/pagination target surfaces as `canonical_not_linked` /
`pagination_unlinked` (expected for a same-site crawl).

## Files

**Create**
- `app/Crawler/Analyzers/CanonicalAnalyzer.php`
- `app/Crawler/Analyzers/PaginationAnalyzer.php`
- `database/migrations/2026_08_15_000004_add_pagination_urls_to_crawl_pages.php`
- `tests/Unit/Crawler/CanonicalAnalyzerTest.php`
- `tests/Unit/Crawler/PaginationAnalyzerTest.php`

**Modify**
- `app/Crawler/IssueCode.php` — 21 cases + `category()`/`severity()` arms.
- `app/Crawler/CheckCatalog.php` — flip the 21 entries planned→active.
- `app/Crawler/PageAnalyzer.php` — register the two new analyzers.
- `app/Jobs/AggregateCrawlJob.php` — the 6 cross-page checks + `$pageByUrl` map.
- `tests/Unit/Crawler/CheckCatalogTest.php` — canonicals active 12, pagination 10; bijection 92.
- `tests/Feature/Crawler/AggregateCrawlJobTest.php`, `tests/Feature/Crawler/CrawlControllerTest.php`.

**No changes:** models (guarded), `MetaAnalyzer` (still stores raw canonical),
`IndexabilityAnalyzer` (still emits `canonical_mismatch`), frontend, lang.

## Testing

- **`CanonicalAnalyzerTest`** (unit, HTML fixtures): each per-page canonical check fires on
  a crafted page and not on a clean control — missing/has, self-referencing (canonical ==
  own url), multiple + conflicting, relative href, fragment href, empty-href invalid
  attribute, a canonical in `<body>` (outside head).
- **`PaginationAnalyzerTest`** (unit): has/first/2plus, multiple next, a next href not in
  any anchor, a next pointing to the page itself (loop), and the resolved
  `$data['pagination_next']`/`pagination_prev`.
- **`CheckCatalogTest`**: canonicals active 12 (1 prior + 11), pagination active 10;
  bijection 92; the generalized severity-match still passes.
- **Aggregate test**: seed pages so a canonical points to a non-indexable page
  (`non_indexable_canonical`) and to an uncrawled URL (`canonical_not_linked`); a
  `pagination_next` to a 404 page (`pagination_non_200`), to an uncrawled URL
  (`pagination_unlinked`), to a non-indexable page (`pagination_non_indexable`), and a
  next→Q where Q.prev doesn't point back (`pagination_sequence_error`).
- **Controller feature test**: `group=canonicals` and `group=pagination` each list a page
  carrying a new code.
- Gates: crawler namespace green, full suite green, Pint clean, `ddev npm run build` green.

## Risks / decisions

- **Big surface (21 checks, 2 new analyzers, 1 migration, 6 cross-page checks).** Executed
  as one plan in 4 tasks (enum/catalogue; canonical analyzer; pagination analyzer +
  migration; cross-page aggregate).
- **5 informational-positive checks stay `notice`** (has_canonical, self_referencing,
  has_pagination, first_page, 2plus) — high-volume by nature; per explicit user decision.
- **`pagination` (rel=next/prev) is Google-deprecated** (2019); activated in full per user
  decision (Bing still uses it).
- **Path-only normalization** for self-referencing/loop reuses the existing
  `canonical_mismatch` normalizer (host-insensitive) for consistency; cross-page URL
  matching uses the aggregate's `$norm` (trailing-slash trim) against crawled page URLs.
- **External/uncrawled targets** surface as `canonical_not_linked` / `pagination_unlinked`
  — expected for a same-site crawl; documented.
- **Canonical resolution in the aggregate** uses a small `resolveUrl` helper (relative →
  absolute against the page URL); pagination next/prev are stored pre-resolved by the
  analyzer to keep aggregate matching simple.
