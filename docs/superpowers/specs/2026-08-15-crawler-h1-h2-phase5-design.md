# Crawler Phase 5 — H1 / H2 — Design

**Status:** approved for planning
**Depends on:** Phases 0-4. Activates all 8 planned `h1` + `h2` checks.

## Goal

Flip the `h1` and `h2` categories fully active: heading length, image-alt-as-heading,
missing/multiple/duplicate H2, non-sequential H2, and cross-page duplicate H1.
`HeadingAnalyzer` already extracts every heading as `{level, text}` in document order and
flags `missing_h1`/`multiple_h1`/`heading_order_skip`; this phase adds the rest, an `h1`
column, and cross-page H1 duplicate detection.

## Scope

**In (8 checks flip planned→active):**

*h1 (3):* `duplicate_h1` (warning, cross-page), `h1_over_70_chars` (notice),
`alt_text_in_h1` (notice).

*h2 (5):* `missing_h2` (notice), `duplicate_h2` (notice, within-page),
`h2_over_70_chars` (notice), `multiple_h2` (warning), `h2_non_sequential` (warning).

**Out:** no new severity; categories/severities/labels already exist (Phase 0) — no
frontend or lang changes. No new network requests.

## Global constraints (carried)

- Bijection stays exact: active catalogue codes === `IssueCode` cases. This phase adds 8
  `IssueCode` cases and flips 8 catalogue entries planned→active, so both grow
  **63 → 71**.
- `IssueCode::category()` and `::severity()` stay exhaustive `match` (no default arm); the
  8 new cases get arms in both. Categories: 3 → `H1`, 5 → `H2`. Severities per the scope
  list (match the catalogue entries).
- Run via DDEV. Frontend gate `ddev npm run build` (no change expected). Pint clean.

## Trigger rules

Computed in `HeadingAnalyzer` from the already-built `$headings` (`[{level, text}]`, in
document order), except `alt_text_in_h1` which reads the DOM. Let `$h1s` = texts of
level-1 headings, `$h2s` = texts of level-2 headings (trimmed).

| Code | Severity | Fires when |
|---|---|---|
| `h1_over_70_chars` | notice | any H1 text has `mb_strlen > 70` |
| `alt_text_in_h1` | notice | an `<img>` with a non-empty `alt` sits inside an `<h1>` |
| `duplicate_h1` | warning | (cross-page) the page's first H1 text equals another page's — computed in the aggregate |
| `missing_h2` | notice | `count($h2s) === 0` |
| `h2_over_70_chars` | notice | any H2 text has `mb_strlen > 70` |
| `multiple_h2` | warning | `count($h2s) > 1` (activated per explicit user decision; note: multiple H2 is normal, so this is high-volume) |
| `duplicate_h2` | notice | (within-page) some H2 text appears more than once among `$h2s` (trimmed, case-insensitive), ignoring empties |
| `h2_non_sequential` | warning | an H1 exists AND the first H2 appears before the first H1 in document order |

Notes:
- `h2_non_sequential` only fires when an H1 exists; a page with H2s but no H1 is already
  covered by `missing_h1` (don't double-flag).
- The page's first H1 text is stored as `$data['h1']` (null when there is no H1, or when
  the only H1 has empty text — e.g. an image-only H1). The aggregate's `duplicate_h1`
  uses this column; the shared `duplicates()` helper already skips null/empty.

## Storage: `h1` column

- **Migration** `add_h1_to_crawl_pages`: nullable `text h1` on `crawl_pages`. `CrawlPage`
  is `$guarded = ['id']`, so the value persists via the observer's `array_merge($data, …)`
  once `HeadingAnalyzer` adds it to `$data`. No cast needed.

## Cross-page: `AggregateCrawlJob`

Mirror `duplicate_title` / `duplicate_meta_description`:
- Add `h1` to the `identities` `->select([...])`.
- `$dupH1 = $this->duplicates($identities, 'h1');`
- Thread `$dupH1` into the `chunkById(...)` closure's `use (...)`.
- After the existing keyword/description duplicate blocks, add:
  `if ($page->h1 !== null && ($dupH1[$page->h1] ?? 0) > 1) { $issues[IssueCode::DuplicateH1->value] = true; }`

## Files

**Create**
- `database/migrations/2026_08_15_000003_add_h1_to_crawl_pages.php`

**Modify**
- `app/Crawler/IssueCode.php` — 8 cases + `category()`/`severity()` arms.
- `app/Crawler/CheckCatalog.php` — flip the 8 entries planned→active.
- `app/Crawler/Analyzers/HeadingAnalyzer.php` — the new per-page H1/H2 checks + `h1`
  storage.
- `app/Jobs/AggregateCrawlJob.php` — cross-page `duplicate_h1`.
- `tests/Unit/Crawler/HeadingAnalyzerTest.php`, `tests/Unit/Crawler/CheckCatalogTest.php`,
  `tests/Feature/Crawler/AggregateCrawlJobTest.php`, `tests/Feature/Crawler/CrawlControllerTest.php`.

**No changes:** models (guarded), `PageContext`, other analyzers, frontend, lang.

## Hardening (folds in a Phase-4 parked minor)

Generalize `CheckCatalogTest`'s catalogue-vs-enum severity check from the security
category to **all active codes**: for every active catalogue code, assert its `severity`
equals `IssueCode::from($code)->severity()`. This pins that the catalogue and the enum
agree everywhere, for this and every future phase. (Enum-wide `severity()` exhaustiveness
is already covered by `IssueCodeTest::test_every_issue_code_has_a_known_severity`, which
calls `severity()` on every case.)

## Testing

- **`HeadingAnalyzerTest`** (unit, HTML fixtures): each new check fires on a crafted page
  and not on a clean control — a >70-char H1 → `h1_over_70_chars`; an `<h1><img alt="x">`
  → `alt_text_in_h1`; no H2 → `missing_h2`; a >70-char H2 → `h2_over_70_chars`; two H2 →
  `multiple_h2`; two identical H2 → `duplicate_h2`; an H2 before the H1 →
  `h2_non_sequential` (and NOT when H2 follows H1); the first H1 text stored in
  `$data['h1']`. Existing missing_h1/multiple_h1/heading_order_skip still pass.
- **`CheckCatalogTest`**: h1 active 6 (3 prior + 3), h2 active 5; bijection 71; the
  generalized severity-match assertion over all active codes.
- **Aggregate test**: two pages with the same first H1 → both get `duplicate_h1`; a unique
  H1 does not.
- **Controller feature test**: `group=h2` lists a page carrying an H2 code.
- Gates: crawler namespace green, full suite green, Pint clean, `ddev npm run build` green.

## Risks / decisions

- **`multiple_h2` is high-volume** (multiple H2s is normal, good markup) — activated as
  `warning` per explicit user decision.
- **`duplicate_h1` is cross-page** (like `duplicate_title`); **`duplicate_h2` is
  within-page** (pages legitimately share H2s site-wide, so cross-page would be noise) —
  per user decision.
- **`h2_non_sequential`** = an H2 before the first H1, distinct from the existing
  `heading_order_skip` (which flags level jumps like H1→H3). No overlap by construction.
- **Image-only H1** (text from `alt`) yields empty heading text → stored `h1` is null and
  `alt_text_in_h1` fires; it is not mis-counted as a cross-page duplicate.
- **Generalized severity-match test** may surface a latent catalogue/enum mismatch on a
  pre-existing code; if so it is a real bug to reconcile, not a test error.
