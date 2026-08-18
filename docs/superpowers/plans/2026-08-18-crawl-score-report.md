# Crawl score + customer report — Implementation Plan (A3B-384)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A transparent on-demand crawl score (overall + SEO + GEO + per-category) shown on the Show dashboard, plus a customer-facing PDF report (cover + scores + prioritized top actions).

**Architecture:** `CrawlScore` computes everything from one pass over the crawl's stored `issues` (reusing `CheckCatalog` metadata) — no new table. The Show controller passes a `score` prop; a `report()` endpoint renders a Blade → PDF via spatie/laravel-pdf (existing pattern).

**Tech Stack:** Laravel 13 / PHP 8.3, spatie/laravel-pdf, Inertia/React, PHPUnit, Pint.

**Spec:** docs/superpowers/specs/2026-08-18-crawl-score-report-design.md

## Global Constraints

- Score is **on-demand** (no DB table/migration). Only **problem** codes count (`CheckCatalog::isProblemCode`, excludes `info`).
- Severity weights **error 3 / warning 2 / notice 1**; `score(set) = round(100 × (1 − min(1, penalty/(pages×3))))`, `pages = max(1, crawl.pages_crawled)`. SEO = all categories except `geo`; GEO = `geo`; overall = all.
- PDF download control on the Show page must be a **native `<a href>`** (Inertia `<Link>`/`LinkButton` breaks binary downloads).
- PDF tests use **`Pdf::fake()`** — no real headless Chromium in tests.
- Gates: `ddev php artisan test` green; `ddev npm run build` green; `ddev exec vendor/bin/pint --test` clean.

---

## Task 1: `CrawlScore` service

**Files:** Create `app/Crawler/CrawlScore.php`, `tests/Unit/Crawler/CrawlScoreTest.php`.

**Interfaces produced:** `CrawlScore::for(Crawl $crawl): array{overall:int,seo:int,geo:int,categories:array<string,int>,topActions:array<int,array{code:string,category:string,severity:string,count:int}>}`.

- [ ] **Step 1: Write `CrawlScoreTest` first.** Seed a `Crawl` (set `pages_crawled`) + `CrawlPage`s with known `issues` and assert:
  - all pages carry a per-page ERROR code (e.g. `missing_title` on every page) → `overall` and the `page_title` category score are low/near 0; a crawl with no issues → all scores 100.
  - a `geo`-only issue (e.g. `no_semantic_html` on some pages) dips `geo` while `seo` stays high; and vice-versa (an SEO issue dips `seo`, not `geo`).
  - an `info` code (`https_urls`) does NOT affect any score (excluded).
  - `topActions` is sorted by severity (error>warning>notice) then count desc, capped at 10, each `{code,category,severity,count}`.
  Match the crawl/page factory seeding used in existing `tests/Feature/Crawler/*` (a Unit test may still use the DB via RefreshDatabase — check how other CrawlScore-like DB unit tests are set up; if simpler, put this test under `tests/Feature/Crawler/CrawlScoreTest.php`).

- [ ] **Step 2: RED.** `ddev php artisan test --filter=CrawlScoreTest` → fails.

- [ ] **Step 3: Implement `CrawlScore`:**
```php
<?php

namespace App\Crawler;

use App\Models\Crawl;

class CrawlScore
{
    private const WEIGHTS = ['error' => 3, 'warning' => 2, 'notice' => 1];

    /**
     * @return array{overall:int, seo:int, geo:int, categories:array<string,int>,
     *   topActions:array<int,array{code:string,category:string,severity:string,count:int}>}
     */
    public static function for(Crawl $crawl): array
    {
        // Pages affected per problem code (unique per page), info excluded.
        $perCode = [];
        foreach ($crawl->pages()->pluck('issues') as $issues) {
            foreach (array_unique($issues ?? []) as $code) {
                if (CheckCatalog::isProblemCode($code)) {
                    $perCode[$code] = ($perCode[$code] ?? 0) + 1;
                }
            }
        }

        $pages = max(1, (int) $crawl->pages_crawled);

        $penalty = function (callable $inSet) use ($perCode): int {
            $p = 0;
            foreach ($perCode as $code => $count) {
                $cat = CheckCatalog::categoryOf($code);
                if ($cat === null || ! $inSet($cat)) {
                    continue;
                }
                $sev = IssueCode::tryFrom($code)?->severity() ?? 'notice';
                $p += $count * (self::WEIGHTS[$sev] ?? 1);
            }

            return $p;
        };

        $score = fn (int $pen): int => (int) round(100 * (1 - min(1, $pen / ($pages * 3))));

        $overall = $score($penalty(fn (string $c) => true));
        $geo = $score($penalty(fn (string $c) => $c === 'geo'));
        $seo = $score($penalty(fn (string $c) => $c !== 'geo'));

        $categories = [];
        foreach (IssueCategory::cases() as $cat) {
            if (CheckCatalog::activeCodesForCategory($cat->value) === []) {
                continue;
            }
            $categories[$cat->value] = $score($penalty(fn (string $c) => $c === $cat->value));
        }

        $rank = self::WEIGHTS;
        $actions = [];
        foreach ($perCode as $code => $count) {
            $cat = CheckCatalog::categoryOf($code);
            if ($cat === null) {
                continue;
            }
            $sev = IssueCode::tryFrom($code)?->severity() ?? 'notice';
            $actions[] = ['code' => $code, 'category' => $cat, 'severity' => $sev, 'count' => $count];
        }
        usort($actions, fn ($a, $b) => (($rank[$b['severity']] ?? 0) <=> ($rank[$a['severity']] ?? 0)) ?: ($b['count'] <=> $a['count']));
        $actions = array_slice($actions, 0, 10);

        return [
            'overall' => $overall,
            'seo' => $seo,
            'geo' => $geo,
            'categories' => $categories,
            'topActions' => $actions,
        ];
    }
}
```

- [ ] **Step 4: GREEN + Pint + commit.**
Run: `ddev php artisan test --filter=CrawlScoreTest` → PASS.
Run: `ddev exec vendor/bin/pint app/Crawler tests`.
```bash
git add app/Crawler/CrawlScore.php tests/Unit/Crawler/CrawlScoreTest.php
git commit -m "feat(crawler): CrawlScore (overall/SEO/GEO + per-category + top actions)"
```
(End body with `Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>`.)

---

## Task 2: score on the Show dashboard

**Files:** Modify `app/Http/Controllers/CrawlController.php` (`show()`), `resources/js/Pages/Crawls/Show.tsx`; extend `tests/Feature/Crawler/CrawlControllerTest.php`.

**Interfaces:** Consumes `CrawlScore` (Task 1). Produces a `score` Inertia prop.

- [ ] **Step 1: Controller.** In `show()`, add `'score' => \App\Crawler\CrawlScore::for($model),` to the `inertia('Crawls/Show', [...])` payload (add `use App\Crawler\CrawlScore;`). No other change.

- [ ] **Step 2: Test.** In `CrawlControllerTest`, extend a `show` test (or add one) to assert the Inertia response `->has('score')->has('score.overall')->has('score.seo')->has('score.geo')`.

- [ ] **Step 3: Show.tsx types + tiles.** Add to the component props type:
```ts
score: {
  overall: number;
  seo: number;
  geo: number;
  categories: Record<string, number>;
  topActions: { code: string; category: string; severity: string; count: number }[];
};
```
Add a small band-colour helper (module scope):
```tsx
const scoreClasses = (n: number): string =>
  n >= 80 ? 'bg-success-soft text-success-foreground'
  : n >= 60 ? 'bg-warning-soft text-warning-foreground'
  : 'bg-danger-soft text-danger-foreground';
```
In the `activeTab === 'overview'` branch, ABOVE the existing severity tiles (`severityTotals`), add a 3-tile grid for Overall / SEO / GEO:
```tsx
<div className="mb-6 grid grid-cols-1 gap-3 sm:grid-cols-3">
  {([['overall', t('crawler.score_overall')], ['seo', t('crawler.score_seo')], ['geo', t('crawler.score_geo')]] as const).map(([key, label]) => (
    <Card key={key} className={`p-4 ${scoreClasses(score[key])}`}>
      <div className="text-3xl font-semibold">{score[key]}<span className="text-lg">/100</span></div>
      <div className="text-xs">{label}</div>
    </Card>
  ))}
</div>
```
(Use real tokens — `bg-success-soft`/`text-success-foreground` etc. are confirmed to exist; match the existing severity-tile markup style. `score[key]` is typed via the keys above — index with a cast if TS complains, e.g. `score[key as 'overall'|'seo'|'geo']`.)

- [ ] **Step 4: Build + regression + Pint + commit.**
Run: `ddev npm run build` → green.
Run: `ddev php artisan test --filter=CrawlControllerTest` → PASS.
Run: `ddev exec vendor/bin/pint app/Http/Controllers/CrawlController.php tests`.
```bash
git add app/Http/Controllers/CrawlController.php resources/js/Pages/Crawls/Show.tsx tests/Feature/Crawler/CrawlControllerTest.php
git commit -m "feat(crawler-ui): overall/SEO/GEO score tiles on the crawl dashboard"
```

---

## Task 3: customer PDF report

**Files:** Create `resources/views/reports/crawl.blade.php`; Modify `app/Http/Controllers/CrawlController.php` (`report()`), `routes/web.php`, `resources/js/Pages/Crawls/Show.tsx` (button), `lang/en/crawler.php`, `lang/de/crawler.php`; Test: extend/add `tests/Feature/Crawler/CrawlReportTest.php`.

- [ ] **Step 1: Read the existing PDF pattern.** Open `app/Http/Controllers/ReportController.php` + a `resources/views/reports/*.blade.php` to copy the EXACT `Pdf::view(...)->...` chain (e.g. `->download(...)`, `->format('a4')`) and the Blade structure/inline-CSS conventions.

- [ ] **Step 2: Controller `report()`** (with `use Spatie\LaravelPdf\Facades\Pdf;` + `use App\Crawler\CrawlScore;`):
```php
public function report(Request $request, string $crawl)
{
    $project = $request->get('project');
    $model = $project->crawls()->findOrFail($crawl);

    return Pdf::view('reports.crawl', [
        'crawl' => $model,
        'project' => $project,
        'score' => CrawlScore::for($model),
    ])->download("crawl-{$model->id}.pdf");
}
```
(Match the exact return/chain style used by `ReportController` — if it uses `->name()` / `->format()` etc., mirror it.)

- [ ] **Step 3: Route.** In `routes/web.php`, beside the crawl export routes, add:
```php
Route::get('/crawls/{crawl}/report', [CrawlController::class, 'report'])->name('app.project.crawls.report');
```
(Same auth + ProjectBindingMiddleware group.)

- [ ] **Step 4: Blade `resources/views/reports/crawl.blade.php`.** Self-contained inline-CSS document (mirror the existing reports' styling), localised via `__()`/`@lang`:
  - Cover: `$project->name`, `$crawl->start_url`, generated date (`now()` formatted), `$crawl->pages_crawled` pages.
  - Scores: big Overall / SEO / GEO numbers with band colours (reuse the same >=80/>=60/<60 bands), then a per-category list — for each `$score['categories']` entry: `@lang('crawler.category_group.'.$cat)` + its number.
  - Top actions: a table over `$score['topActions']` — severity (`@lang('crawler.severity_'.$a['severity'])`), issue label (`@lang('crawler.issue.'.$a['code'])`), affected count, guidance (`@lang('crawler.issue_help.'.$a['code'])`). If `topActions` is empty, render a "no issues" line (`@lang('crawler.report_no_issues')`).
  - Heading `@lang('crawler.report_title')`; sections `@lang('crawler.report_scores')` / `@lang('crawler.report_top_actions')`.

- [ ] **Step 5: Show button.** In `Crawls/Show.tsx` `PageHeader` actions, add a native `<a>` (same `buttonClasses('secondary','md')` styling as the CSV/XLSX buttons) → `route('app.project.crawls.report', { project: project!.id, crawl: crawl.id })`, label `t('crawler.report_pdf')`. Place next to the export buttons.

- [ ] **Step 6: Lang keys** (BOTH en + de; grep first for placement/collisions):
  - EN: `score_overall` "Overall score", `score_seo` "SEO score", `score_geo` "GEO / AI score", `report_pdf` "Report (PDF)", `report_title` "SEO & GEO report", `report_scores` "Scores", `report_top_actions` "Top actions", `report_no_issues` "No issues found — great!", `report_generated` "Generated", `report_pages` "Pages crawled".
  - DE: `score_overall` "Gesamt-Score", `score_seo` "SEO-Score", `score_geo` "GEO / KI-Score", `report_pdf` "Report (PDF)", `report_title` "SEO- & GEO-Report", `report_scores` "Scores", `report_top_actions` "Wichtigste Maßnahmen", `report_no_issues` "Keine Probleme gefunden — top!", `report_generated` "Erstellt", `report_pages` "Gecrawlte Seiten".

- [ ] **Step 7: Report test** (`tests/Feature/Crawler/CrawlReportTest.php`) — use `Pdf::fake()` (spatie/laravel-pdf) so NO real Chromium runs:
  - as a project member, seed a crawl + a couple pages with issues;
  - `Pdf::fake();` then `GET route('app.project.crawls.report', …)` → `assertOk()`;
  - `Pdf::assertViewIs('reports.crawl');` and assert the view got the score data (`Pdf::assertSee(...)` on a known label/score, or assert via the fake's stored data). Mirror how existing report tests fake Pdf if they do.
  - a non-member/other-project crawl → `assertNotFound()` (tenant scoping).

- [ ] **Step 8: Build + regression + Pint + commit.**
Run: `ddev npm run build` → green.
Run: `ddev php artisan test --filter="CrawlReport|CrawlController|CrawlScore"` → PASS.
Run: `ddev exec vendor/bin/pint app/Http/Controllers/CrawlController.php routes resources/views/reports lang tests`.
```bash
git add resources/views/reports/crawl.blade.php app/Http/Controllers/CrawlController.php routes/web.php resources/js/Pages/Crawls/Show.tsx lang/en/crawler.php lang/de/crawler.php tests/Feature/Crawler/CrawlReportTest.php
git commit -m "feat(crawler): customer PDF report (cover + scores + top actions)"
```

---

## Final verification (whole plan)

- [ ] `ddev php artisan test` — full suite green (CrawlScore + controller + report tests; no Chromium via Pdf::fake).
- [ ] `ddev npm run build` — green.
- [ ] `ddev exec vendor/bin/pint --test` — clean.
- [ ] Manual/live: open a completed crawl → Overview shows Overall/SEO/GEO tiles; "Report (PDF)" downloads a PDF with the cover, scores + per-category, and the prioritized top-actions list.

## Self-Review notes (author)

- **Spec coverage:** score engine (T1), dashboard tiles + prop (T2), PDF report + button + lang (T3). ✓
- **Transparent formula** in one place (CrawlScore), weights as constants. ✓
- **Download button is a native `<a>`** (learned from the CSV/XLSX fix). ✓
- **Report tests hermetic** via `Pdf::fake()` — no headless Chromium. ✓
- **No new table** — on-demand from stored issues, one pluck pass (same as `show()`'s counts). ✓
- **SEO/GEO split** = geo vs non-geo; documented, tunable. ✓
