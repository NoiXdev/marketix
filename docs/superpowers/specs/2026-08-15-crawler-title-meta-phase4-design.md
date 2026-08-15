# Crawler Phase 4 — Page Title / Meta Description / Meta Keywords — Design

**Status:** approved for planning
**Depends on:** Phases 0-3. Activates all 15 planned checks across three categories.

## Goal

Flip the `page_title`, `meta_description`, and `meta_keywords` categories fully active:
character-count, pixel-width, structural (multiple / outside-`<head>`), `title_same_as_h1`,
and the meta-keywords checks (missing / multiple / cross-page duplicate). The title and
description text + lengths are already extracted by `MetaAnalyzer`; this phase adds the
checks, a `PixelWidth` estimator, a `meta_keywords` column, and cross-page keyword
duplicate detection.

## Scope

**In (15 checks flip planned→active):**

*page_title (6):* `title_below_30_chars` (notice), `title_below_200px` (notice),
`title_over_561px` (notice), `title_same_as_h1` (notice), `multiple_title` (warning),
`title_outside_head` (warning).

*meta_description (6):* `meta_description_below_70_chars` (notice),
`meta_description_over_155_chars` (notice), `meta_description_below_400px` (notice),
`meta_description_over_985px` (notice), `multiple_meta_description` (warning),
`meta_description_outside_head` (warning).

*meta_keywords (3):* `missing_meta_keywords` (notice), `multiple_meta_keywords` (notice),
`duplicate_meta_keywords` (notice).

**Out:** no new severity; the categories, severities, and all 15 lang labels already
exist (Phase 0) — no frontend or lang changes. No new network requests.

## Global constraints (carried)

- Bijection stays exact: active catalogue codes === `IssueCode` cases. This phase adds 15
  `IssueCode` cases and flips 15 catalogue entries planned→active, so both grow
  **48 → 63**.
- `IssueCode::category()` and `::severity()` stay exhaustive `match` (no default arm); the
  15 new cases get arms in both. Categories: 6 → `PageTitle`, 6 → `MetaDescription`, 3 →
  `MetaKeywords`. Severities per the scope list (match the catalogue entries).
- Run via DDEV. Frontend gate `ddev npm run build` (no change expected). Pint clean.

## Component: `PixelWidth`

A new pure `App\Crawler\PixelWidth` estimating the rendered pixel width of a string from a
per-character Arial-reference width table (Google truncates by pixel width, which
character count only approximates).

```php
/** Estimated rendered width in pixels. $scale adjusts for font size (title vs snippet). */
public static function widthPx(string $text, float $scale = 1.0): int
```

- A `private const WIDTHS = [...]` maps characters to approximate px advances at the title
  font; unmapped characters use an average default. `widthPx` sums the per-character
  widths and multiplies by `$scale`, rounding to an int.
- **Calibration:** the table is tuned so a representative ~60-character title ≈ 561px
  (consistent with the existing 60-char `title_too_long` heuristic). Titles use
  `$scale = 1.0`; descriptions use `$scale = 0.72` (Google's snippet font is smaller), so
  ~155 characters ≈ 985px. This is a documented approximation, not exact browser
  rendering.

## Component: `MetaAnalyzer` (extended)

`MetaAnalyzer` already extracts title/description/canonical/robots/word-count and emits
`missing_title`/`title_too_long`/`missing_meta_description`/`thin_content`. It gains the
new checks (all on the parsed DOM it already receives). Guarding rule: length and pixel
checks fire ONLY when the field is present and non-empty — absence is covered by the
existing `missing_*` checks.

### Title checks
- Read the title text (as today). When present:
  - `mb_strlen($title) < 30` → `title_below_30_chars`.
  - `$px = PixelWidth::widthPx($title, 1.0)`; `$px < 200` → `title_below_200px`; `$px > 561`
    → `title_over_561px`.
  - First `<h1>` text (trimmed); if it equals the title (trimmed, case-insensitive) →
    `title_same_as_h1`.
- Structural (whole document, **excluding SVG `<title>`** via XPath):
  - `allTitles = filterXPath('//title[not(ancestor::svg)]')->count()`,
    `headTitles = filterXPath('//head/title')->count()`.
  - `allTitles > 1` → `multiple_title`; `allTitles > headTitles` → `title_outside_head`.

### Description checks
- Read the description (as today). When present:
  - `mb_strlen($desc) < 70` → `meta_description_below_70_chars`;
    `mb_strlen($desc) > 155` → `meta_description_over_155_chars`.
  - `$px = PixelWidth::widthPx($desc, 0.72)`; `$px < 400` → `meta_description_below_400px`;
    `$px > 985` → `meta_description_over_985px`.
- Structural:
  - `all = filter('meta[name="description"]')->count()`,
    `head = filter('head meta[name="description"]')->count()`.
  - `all > 1` → `multiple_meta_description`; `all > head` → `meta_description_outside_head`.

### Meta-keywords checks + extraction
- `keywords = attr('meta[name="keywords"]', 'content')` (first occurrence anywhere);
  trim; store via `$r->add('meta_keywords', $keywords)` (null when absent).
- `count = filter('meta[name="keywords"]')->count()`.
- `count === 0` → `missing_meta_keywords` (NOTE: meta keywords are SEO-obsolete, so this
  fires on nearly every page — activated per explicit user decision; expect high volume).
- `count > 1` → `multiple_meta_keywords`.
- Cross-page `duplicate_meta_keywords` is computed in `AggregateCrawlJob` (below).

## Storage: `meta_keywords` column

- **Migration** `add_meta_keywords_to_crawl_pages`: nullable `text meta_keywords` on
  `crawl_pages` (mirrors `title`/`meta_description`). `CrawlPage` is `$guarded = ['id']`,
  so the value persists automatically via the observer's `array_merge($data, ...)` once
  `MetaAnalyzer` adds it to `$data`. No cast needed (plain string).

## Cross-page: `AggregateCrawlJob`

Mirror the existing `duplicate_meta_description` logic:
- Add `meta_keywords` to the `identities` `->select([...])` list.
- `$dupKeywords = $this->duplicates($identities, 'meta_keywords');`
- In the per-page chunk loop, after the description-duplicate block, add:
  `if ($page->meta_keywords !== null && ($dupKeywords[$page->meta_keywords] ?? 0) > 1) { $issues[IssueCode::DuplicateMetaKeywords->value] = true; }`
- Thread `$dupKeywords` into the closure's `use (...)`. The chunk loop already loads full
  models, so `$page->meta_keywords` is available.

## Files

**Create**
- `app/Crawler/PixelWidth.php`
- `tests/Unit/Crawler/PixelWidthTest.php`
- `database/migrations/2026_08_15_000002_add_meta_keywords_to_crawl_pages.php`

**Modify**
- `app/Crawler/IssueCode.php` — 15 cases + `category()`/`severity()` arms.
- `app/Crawler/CheckCatalog.php` — flip the 15 entries planned→active.
- `app/Crawler/Analyzers/MetaAnalyzer.php` — the new title/description/keywords checks +
  keyword extraction.
- `app/Jobs/AggregateCrawlJob.php` — `duplicate_meta_keywords`.
- `tests/Unit/Crawler/MetaAnalyzerTest.php` — new checks (create if absent).
- `tests/Unit/Crawler/CheckCatalogTest.php` — the three categories' active counts; bijection 63.
- `tests/Feature/Crawler/CrawlControllerTest.php` — a title/desc/keywords tab lists an affected page; duplicate_meta_keywords via aggregation.

**No changes:** models (guarded), `PageContext`, other analyzers, frontend, lang.

## Testing

- **`PixelWidthTest`** (unit, pure): a clearly-long title estimates `> 561`; a very short
  one `< 200`; monotonic (longer string ⇒ larger px); the `$scale` multiplies. Assert
  threshold-crossing behavior with representative strings, not brittle exact px values.
- **`MetaAnalyzerTest`** (unit, HTML fixtures): each new check fires on a crafted page and
  does NOT fire on a clean control — a 20-char title → `title_below_30_chars`; a
  long title → `title_over_561px`; title == h1 → `title_same_as_h1`; two `<title>` →
  `multiple_title`; a `<title>` in body → `title_outside_head`; an SVG `<title>` does NOT
  trigger multiple/outside-head (SVG-exclusion); short/long descriptions; two meta
  descriptions; no keywords → `missing_meta_keywords`; two keyword tags →
  `multiple_meta_keywords`; keyword extraction into `$data`.
- **`CheckCatalogTest`**: page_title active 9 (3 prior + 6), meta_description active 8
  (2 prior + 6), meta_keywords active 3; bijection cardinality 63.
- **Feature test** (`CrawlControllerTest`): `group=page_title` (or meta_description /
  meta_keywords) lists a page carrying a new code; `duplicate_meta_keywords` appears when
  two pages share keywords (drive through `AggregateCrawlJob` or seed issues directly for
  the tab-listing assertion, and a dedicated aggregate test for the duplicate logic).
- Gates: crawler namespace green, full suite green, Pint clean, `ddev npm run build` green.

## Risks / decisions

- **Pixel width is approximate** — a calibrated char-width table, not real rendering.
  Tuned to the existing 60-char title heuristic; documented. Tests assert threshold
  crossings, not exact pixels, so table tuning won't make them brittle.
- **`missing_meta_keywords` is high-volume noise** (obsolete tag) — activated per explicit
  user decision; the `notice` severity keeps it out of error/warning counts.
- **SVG `<title>` false positives** — avoided by counting titles with
  `//title[not(ancestor::svg)]`.
- **`title_same_as_h1` uses trimmed, case-insensitive comparison** — catches
  casing-only matches; both fields must be present.
- **Char-count vs pixel checks can co-fire** on the same short/long field (independent
  metrics) — intentional; both are `notice`.
- **`MetaAnalyzer` grows** — the title/description/keywords checks are cohesive with its
  existing responsibility; the pixel table lives in its own `PixelWidth` class to keep the
  analyzer readable.
