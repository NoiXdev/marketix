# Crawl score + customer report — Phase design (A3B-384)

**Status:** approved-scoping (build after spec+plan committed)
**Ticket:** A3B-384 — delivers the concept's "Score & Maßnahmen" + "kundentauglicher Report" on
top of the existing crawler checks. In-stack only (no external API/LLM). Monitoring/history is a
later phase (score is computed on-demand; no new DB table).

## Goal

1. A transparent **score** for a completed crawl: an **overall** score plus a **SEO** and a
   **GEO** sub-score (0–100 each), with a per-category breakdown — derived on-demand from the
   crawl's stored issues.
2. A **customer-facing PDF report**: cover + the scores + a prioritized "top actions" list.

Decisions locked with the user: Gesamt + SEO + GEO getrennt (+ per-category); report =
cover + score(s) + prioritized top-actions (compact).

## Scoring model (transparent, tunable)

Computed in `App\Crawler\CrawlScore` from ONE pass over the crawl's pages' `issues` (unique per
page → `perCode[code]` = pages affected), reusing the existing `CheckCatalog` metadata:

- Only **problem** codes count (`CheckCatalog::isProblemCode` → excludes `info`).
- Severity weights: **error 3, warning 2, notice 1** (constants, tunable).
- `penalty(set) = Σ perCode[c] × weight(severity(c))` over active problem codes whose
  `CheckCatalog::categoryOf(c)` is in `set`.
- `pages = max(1, crawl.pages_crawled)`.
- `score(set) = round(100 × (1 − min(1, penalty(set) / (pages × 3))))` → 0–100.
  (Rationale: one *error* on every crawled page drives that set's score to 0; sparse notices on a
  large site stay near 100.)
- **overall** = `score(all categories)`; **geo** = `score({geo})`; **seo** =
  `score(all categories except geo)`; **per-category** = `score({category})` for each category
  that has active checks.
- **Band** (for colour, both UI + PDF): `>= 80` good, `60–79` warning, `< 60` danger.
- **topActions**: every problem code with `count > 0`, sorted by severity (error>warning>notice)
  then by `count` desc, capped at 10; each = `{code, category, severity, count}`.

**Known limitation (documented):** site-level checks (e.g. `ai_crawler_blocked`,
`missing_llms_txt`) fire on a single page, so they barely move the density score on a large
site. They are therefore **always surfaced explicitly** in the report's top-actions list
(sorted by severity, so warnings like `ai_crawler_blocked` appear) regardless of their small
score impact. The score is a coarse gauge; the action list is the truth. Weights/normaliser are
constants that can be tuned later without changing the contract.

`CrawlScore::for(Crawl $crawl): array` returns:
`{ overall:int, seo:int, geo:int, categories: array<string,int>, topActions: array<{code,category,severity,count}> }`.

## Where it shows

- **Show page Overview dashboard** (`Crawls/Show.tsx`): three score tiles — Overall / SEO / GEO
  (number + band colour) — added above the existing severity tiles. The controller `show()` adds
  a `score` prop (`CrawlScore::for($model)`).
- **PDF report**: a "Report (PDF)" download control next to the existing CSV/XLSX buttons in the
  Show `PageHeader` — a **native `<a href>`** (downloads work; not an Inertia `<Link>`).

## PDF report

- Route `GET /project/{project}/crawls/{crawl}/report` → name `app.project.crawls.report` →
  `CrawlController::report(...)` (tenant-scoped `findOrFail`, like `export`).
- Renders `resources/views/reports/crawl.blade.php` via **spatie/laravel-pdf**, mirroring the
  existing pattern in `app/Http/Controllers/ReportController.php`
  (`return Pdf::view('reports.crawl', $data)->download("crawl-{$model->id}.pdf")` or the repo's
  exact chained form — reuse it verbatim, incl. `->format('a4')` if used).
- **Blade content** (compact, customer-facing, localised to the current app locale):
  - Cover: project name, `crawl.start_url`, generated-at date, pages crawled.
  - Scores: Overall / SEO / GEO (big numbers + band colour) + a small per-category list with each
    category label + its score.
  - Top actions: a table of the (≤10) prioritized issues — severity, issue label
    (`crawler.issue.<code>`), affected-page count, and the fix guidance (`crawler.issue_help.<code>`).
  - Empty state: if no problems, a "no issues found" line.
  - Styling self-contained (inline CSS in the Blade; PDF has no external assets). Reuse the look of
    the existing `resources/views/reports/*` templates for consistency.

## Files

**Create:** `app/Crawler/CrawlScore.php`, `resources/views/reports/crawl.blade.php`,
`tests/Unit/Crawler/CrawlScoreTest.php`.
**Modify:** `app/Http/Controllers/CrawlController.php` (`show()` `score` prop + `report()`),
`routes/web.php` (report route), `resources/js/Pages/Crawls/Show.tsx` (score tiles + report
button + `score` prop type), `lang/en/crawler.php`, `lang/de/crawler.php`.
**No change:** the check logic, catalogue, other exports. No new DB table/migration.

## Testing

- `CrawlScoreTest` (unit): seed a crawl + pages with known issues (a per-page error on all pages →
  that category/overall score 0; a few notices on a large crawl → high score; a geo-only issue →
  geo dips while seo stays high; info codes ignored). Assert `overall`/`seo`/`geo`/`categories`
  and `topActions` ordering (severity then count) + cap.
- `CrawlControllerTest`: `show()` exposes a `score` prop with the expected keys.
- Report route test: assert auth/tenant scoping + that the PDF is produced **using `Pdf::fake()`**
  (spatie/laravel-pdf's fake) — `Pdf::assertViewIs('reports.crawl')` and assert the view data /
  a `see()` on key content — so NO real headless Chromium runs in tests (mirror how existing
  report tests fake Pdf; if none do, introduce `Pdf::fake()` here). Hermetic.
- Gates: `ddev php artisan test` green; `ddev npm run build` green; `ddev exec vendor/bin/pint
  --test` clean.

## Risks / decisions

- **Coarse score / tunable weights** — documented; site-level checks under-weighted but always
  surfaced in top-actions. Score is a signal, not a precise metric.
- **On-demand (no persistence)** — matches "no history this phase"; recomputed cheaply from stored
  issues (same one-pass approach `show()` already uses for category counts).
- **SEO vs GEO split** = `geo` category vs everything else (incl. `other`); simple + explainable.
  Can be refined (e.g. count schema/hreflang toward GEO) in a later tweak.
- **PDF tests use `Pdf::fake()`** — no Chromium in CI (consistent with the hermeticity rule the
  GEO phase established for headless rendering).
- **Report localised to the current app locale** (per-project reports; same as existing reports).
