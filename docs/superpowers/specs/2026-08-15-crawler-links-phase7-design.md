# Crawler Phase 7 — Links — Design

**Status:** approved for planning
**Depends on:** Phases 0-6. Activates all 12 planned `links` checks (`orphan_page` +
`broken_link` are already active).

## Goal

Flip the `links` category fully active: per-page outlink/depth checks and cross-page
inlink checks, all computed in `AggregateCrawlJob` from the already-stored `CrawlLink`
rows + depth + indexability. A pure `LinkChecks` class owns the thresholds, the
non-descriptive-anchor list, and the flag→issue-code logic; the aggregate builds the
per-page/per-target aggregates and calls it.

## Scope

**In (12 checks flip planned→active).** Severities taken as-is from the catalogue.

*Per-page — outlinks/depth (9):*
| Code | Sev | Fires when |
|---|---|---|
| `pages_high_crawl_depth` | notice | page depth ≥ 4 |
| `pages_no_internal_outlinks` | warning | 0 internal outlinks (dead-end) |
| `pages_many_internal_outlinks` | notice | > 100 internal outlinks |
| `pages_many_external_outlinks` | notice | > 100 external outlinks |
| `internal_nofollow_outlinks` | notice | an internal outlink has `rel` containing `nofollow` |
| `internal_outlinks_no_anchor` | notice | an internal outlink has empty anchor text |
| `non_descriptive_anchor_internal_outlinks` | notice | an internal outlink's anchor is in the stop-list |
| `outlinks_to_localhost` | warning | an outlink host is `localhost`/`127.0.0.1`/`::1`/`0.0.0.0`/`*.localhost` |
| `pages_non_crawlable_internal_outlinks` | warning | an internal outlink targets a crawled, non-indexable page |

*Cross-page — inlink analysis (3):*
| `follow_nofollow_internal_inlinks` | notice | the page has BOTH follow and nofollow internal inlinks |
| `only_internal_nofollow_inlinks` | warning | all of the page's internal inlinks are nofollow (≥1) |
| `only_non_indexable_inlinks` | warning | all pages linking to this page are non-indexable (≥1 inlink) |

**Guard:** all 12 checks apply only to HTML pages (`content_category === 'html'`) — images/
PDFs/media have no meaningful link structure and would otherwise inflate
`pages_no_internal_outlinks` / `pages_high_crawl_depth`.

**Non-descriptive anchor stop-list:** `click here`, `here`, `read more`, `more`, `this`,
`link`, `click`, `learn more`, `details`, `continue`, `read`, `info`, `this page`, `go`
(matched case-insensitively against the trimmed anchor text).

**Out:** no new severity; categories/severities/labels already exist (Phase 0) — no
frontend or lang changes. No new network requests (reuses stored links + page data).

## Global constraints (carried)

- Bijection stays exact: active catalogue codes === `IssueCode` cases. This phase adds 12
  `IssueCode` cases and flips 12 catalogue entries planned→active, so both grow
  **92 → 104**.
- `IssueCode::category()` and `::severity()` stay exhaustive `match` (no default arm); the
  12 new cases get arms in both — all → `IssueCategory::Links`. Severities per the table
  (match the catalogue; the generalized severity-match test enforces this).
- Run via DDEV. Frontend gate `ddev npm run build` (no change expected). Pint clean.

## Component: `LinkChecks` (pure)

New `App\Crawler\LinkChecks` — thresholds + classification + flag→code logic, unit-tested
in isolation.

```php
final class LinkChecks
{
    public const HIGH_CRAWL_DEPTH = 4;
    public const MANY_OUTLINKS = 100;
    public const NON_DESCRIPTIVE_ANCHORS = ['click here','here','read more','more','this','link','click','learn more','details','continue','read','info','this page','go'];

    public static function isNonDescriptive(string $anchor): bool;   // trimmed, lower-cased ∈ stop-list
    public static function isLocalhost(string $url): bool;           // host ∈ {localhost,127.0.0.1,::1,0.0.0.0} or *.localhost

    /**
     * @param array{internal:int,external:int,nofollow_internal:bool,no_anchor_internal:bool,non_descriptive_internal:bool,localhost:bool,non_crawlable_internal:bool} $stats
     * @return string[]  IssueCode values
     */
    public static function outlinkIssues(array $stats, ?int $depth): array;

    /**
     * @param array{count:int,follow:bool,nofollow:bool,anyIndexableSource:bool} $detail
     * @return string[]  IssueCode values
     */
    public static function inlinkIssues(array $detail): array;
}
```

- `outlinkIssues`: `depth !== null && depth >= HIGH_CRAWL_DEPTH` → `pages_high_crawl_depth`;
  `internal === 0` → `pages_no_internal_outlinks`; `internal > MANY_OUTLINKS` →
  `pages_many_internal_outlinks`; `external > MANY_OUTLINKS` → `pages_many_external_outlinks`;
  each boolean flag → its code (`nofollow_internal` → `internal_nofollow_outlinks`, etc.).
- `inlinkIssues` (only meaningful when `count > 0`): `follow && nofollow` →
  `follow_nofollow_internal_inlinks`; `nofollow && ! follow` → `only_internal_nofollow_inlinks`;
  `! anyIndexableSource` → `only_non_indexable_inlinks`.

## Aggregate wiring: `AggregateCrawlJob`

The existing single internal-links pass is widened to all links and enriched:

- Build `$pageIndexableById` from `$identities` (`id → (bool) is_indexable`).
- Change the links pass from `->where('type','internal')->select(['from_page_id','to_url'])`
  to `->select(['from_page_id','to_url','type','rel','anchor'])` (all links), branching on
  `$link->type`. Keep the existing `$inlinks` count + `$adjById` adjacency for
  `type === 'internal'` only (unchanged behavior). Additionally build:
  - `$outStats[from_page_id]` = the `stats` shape above (internal/external counts + the
    5 booleans; `localhost` from `LinkChecks::isLocalhost($to_url)`; `non_crawlable_internal`
    when the internal target resolves in `$pageByUrl` with `indexable === false`).
  - `$inlinkDetail[norm(to_url)]` (internal links only) = `{count, follow, nofollow,
    anyIndexableSource}`, where `follow`/`nofollow` come from the link's `rel` and
    `anyIndexableSource` is true if any source page (`$pageIndexableById[from_page_id]`)
    is indexable.
- Thread `$outStats`, `$inlinkDetail` into the `chunkById` closure's `use (...)`.
- In the per-page loop, for HTML pages only (`content_category === 'html'`):
  merge `LinkChecks::outlinkIssues($outStats[$page->id] ?? EMPTY_STATS, $depthsById[$page->id] ?? null)`
  and `LinkChecks::inlinkIssues($inlinkDetail[$key] ?? EMPTY_DETAIL)` into `$issues`.
  (`EMPTY_STATS` = zero counts / all-false; `EMPTY_DETAIL` = count 0 → `inlinkIssues`
  returns nothing.)

The existing `orphan_page` / `broken_link` / duplicate / sitemap logic is untouched.

## Files

**Create**
- `app/Crawler/LinkChecks.php`
- `tests/Unit/Crawler/LinkChecksTest.php`

**Modify**
- `app/Crawler/IssueCode.php` — 12 cases + arms.
- `app/Crawler/CheckCatalog.php` — flip the 12 entries planned→active.
- `app/Jobs/AggregateCrawlJob.php` — widen/enrich the links pass; call `LinkChecks`.
- `tests/Unit/Crawler/CheckCatalogTest.php` — links active 14; bijection 104.
- `tests/Feature/Crawler/AggregateCrawlJobTest.php`, `tests/Feature/Crawler/CrawlControllerTest.php`.

**No changes:** models, analyzers, `PageContext`, frontend, lang.

## Testing

- **`LinkChecksTest`** (unit, pure): `isNonDescriptive` / `isLocalhost`; `outlinkIssues`
  crossing each threshold (depth 3 vs 4; 100 vs 101 internal/external) and each boolean
  flag; `inlinkIssues` for the three inlink combinations (both→follow_nofollow; nofollow
  only→only_nofollow; no indexable source→only_non_indexable; a clean follow-only inlink →
  nothing).
- **`CheckCatalogTest`**: links active 14 (2 prior + 12); bijection cardinality 104;
  generalized severity-match still passes.
- **Aggregate feature test**: seed a small graph so an HTML page gets, e.g.,
  `pages_no_internal_outlinks` / `internal_nofollow_outlinks` / `outlinks_to_localhost` /
  `pages_non_crawlable_internal_outlinks` (link to a noindex page) on the source, and a
  target that has `only_internal_nofollow_inlinks` / `only_non_indexable_inlinks`; assert a
  non-HTML resource does NOT receive `pages_no_internal_outlinks`.
- **Controller feature test**: `group=links` lists a page carrying a new code.
- Gates: crawler namespace green, full suite green, Pint clean, `ddev npm run build` green.

## Risks / decisions

- **Thresholds** (depth ≥ 4, > 100 outlinks) per explicit user decision; centralized as
  `LinkChecks` constants for easy future tuning.
- **HTML-only guard** prevents images/PDFs from inflating dead-end / depth counts.
- **`pages_non_crawlable_internal_outlinks`** = internal outlink to a crawled non-indexable
  page (reuses the `$pageByUrl` indexable map from Phase 6); an internal link to an
  uncrawled URL is already surfaced by `broken_link`/orphan logic, not here.
- **Cross-page inlink checks** reuse the single links pass — no extra query; `anyIndexableSource`
  defaults true for an unknown source id (so a missing source never forces
  `only_non_indexable_inlinks`).
- **`many_outlinks` counts** are per-direction (internal vs external), matching the SF/Google
  ~100-links guideline.
