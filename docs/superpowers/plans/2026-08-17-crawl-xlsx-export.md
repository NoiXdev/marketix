# Crawl XLSX export — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add an XLSX export of a crawl — an "Übersicht" sheet (category → affected-URL count) plus one sheet per affected category listing the affected URLs — alongside the untouched CSV export, with a download button on the crawl Show page.

**Architecture:** A streaming `CrawlXlsxExporter` service (OpenSpout) builds the workbook to a temp file; a thin `CrawlController::exportXlsx` streams it as a download. "Affected" mirrors the UI: a page counts for category C if it has ≥1 issue in C with `CheckCatalog::isProblemCode` (i.e. severity ≠ info) — so the Übersicht counts equal the Show sidebar counts.

**Tech Stack:** Laravel 13 / PHP 8.3, `openspout/openspout` ^4, Inertia/React, PHPUnit, Pint.

**Spec:** docs/superpowers/specs/2026-08-17-crawl-xlsx-export-design.md

## Global Constraints

- The existing CSV `export` route/method stay unchanged.
- "Affected" gate = `CheckCatalog::isProblemCode($code)` (severity ≠ info) so counts match the UI.
- Memory-safe: use `cursor()` for both the count pass and the per-category row queries; OpenSpout streams rows to disk.
- XLSX string cells are inline strings (not formulas) — do NOT apply the CSV `sanitizeCsvCell` prefix (it would corrupt values); values are written as-is.
- Use the installed OpenSpout **v4** API (`new Writer()` / `new Reader()`, no factories). Verify exact class/method names against the installed package before finalising.
- Gates: `ddev php artisan test` green; `ddev npm run build` green; `ddev exec vendor/bin/pint --test` clean. Lint is broken — build is the FE gate.

---

## Task 1: dependency + exporter service + endpoint + lang + test

**Files:** Modify `composer.json`/`composer.lock` (add dep), `app/Http/Controllers/CrawlController.php`, `routes/web.php`, `lang/en/crawler.php`, `lang/de/crawler.php`; Create `app/Crawler/Export/CrawlXlsxExporter.php`, `tests/Feature/Crawler/CrawlXlsxExportTest.php`.

**Interfaces produced:** `CrawlXlsxExporter::writeToFile(Crawl, string $path): void`; route `app.project.crawls.export-xlsx`.

- [ ] **Step 1: Add the dependency.** Run `ddev composer require openspout/openspout` (installs ^4). Confirm it resolves and `composer.lock` updates.

- [ ] **Step 2: Add lang keys** to BOTH `lang/en/crawler.php` and `lang/de/crawler.php` (place beside the existing `col_*` keys; grep first to avoid collisions):
  - en: `'export_xlsx' => 'Export XLSX'`, `'export_overview' => 'Overview'`, `'export_col_category' => 'Category'`, `'export_col_affected' => 'Affected URLs'`, `'export_col_problems' => 'Problems'`, `'yes' => 'Yes'`, `'no' => 'No'`.
  - de: `'export_xlsx' => 'XLSX-Export'`, `'export_overview' => 'Übersicht'`, `'export_col_category' => 'Kategorie'`, `'export_col_affected' => 'Betroffene URLs'`, `'export_col_problems' => 'Probleme'`, `'yes' => 'Ja'`, `'no' => 'Nein'`.
  (Reuse existing `col_url`/`col_status`/`col_indexable`/`col_inlinks`, `category_group.*`, `issue.*`.)

- [ ] **Step 3: Write the feature test first** (`tests/Feature/Crawler/CrawlXlsxExportTest.php`). Seed a project + member user (mirror the seeding in `tests/Feature/Crawler/CrawlControllerTest.php`), a crawl, and pages:
  - A `https://example.com/a` → `issues = ['missing_title', 'missing_csp_header']` (page_title + security), `is_indexable = true`, `inlinks_count = 3`, `status_code = 200`, `depth = 1`.
  - B `https://example.com/b` → `issues = ['https_urls']` (security, but severity info → NOT a problem), `status_code = 200`.
  - C `https://example.com/c` → `issues = []`.

  Assertions:
  1. `GET route('app.project.crawls.export-xlsx', ['project'=>$project->id,'crawl'=>$crawl->id])` → `assertOk()`, header `Content-Type` contains `spreadsheetml.sheet`, `Content-Disposition` contains `crawl-{$crawl->id}.xlsx`.
  2. Write the workbook via the exporter to a temp path and read it back with OpenSpout's v4 `Reader\XLSX\Reader`: collect `sheetName => array_of_rows`. Assert:
     - an `Overview`/`Übersicht` sheet exists (use the app's default-locale label — set the app locale explicitly in the test, e.g. `app()->setLocale('en')`, and assert against the en labels) with a `page_title → 1` row and a `security → 1` row (B is NOT counted — proves info exclusion).
     - a `Page Titles` sheet (label of `page_title`) exists with a row whose URL cell is `https://example.com/a` and whose Problems cell contains the `missing_title` label.
     - the `security` sheet contains A but NOT `https://example.com/b`.
     - no sheet is created for a category with zero affected pages.

  Read-back helper: `$reader = new \OpenSpout\Reader\XLSX\Reader(); $reader->open($path); foreach ($reader->getSheetIterator() as $sheet) { $name=$sheet->getName(); foreach ($sheet->getRowIterator() as $row) { $rows[$name][] = $row->toArray(); } } $reader->close();` — verify these exact calls exist in the installed v4 API and adjust if needed.

- [ ] **Step 4: RED.** `ddev php artisan test --filter=CrawlXlsxExportTest` → fails (class/route missing).

- [ ] **Step 5: Implement `CrawlXlsxExporter`.** Create `app/Crawler/Export/CrawlXlsxExporter.php`:

```php
<?php

namespace App\Crawler\Export;

use App\Crawler\CheckCatalog;
use App\Crawler\IssueCategory;
use App\Models\Crawl;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;

class CrawlXlsxExporter
{
    public function writeToFile(Crawl $crawl, string $path): void
    {
        // 1) Affected-URL count per category — one streamed pass over issues.
        $affected = [];
        foreach ($crawl->pages()->select('issues')->cursor() as $p) {
            $cats = [];
            foreach (array_unique($p->issues ?? []) as $code) {
                if (! CheckCatalog::isProblemCode($code)) {
                    continue;
                }
                $cat = CheckCatalog::categoryOf($code);
                if ($cat !== null) {
                    $cats[$cat] = true;
                }
            }
            foreach (array_keys($cats) as $cat) {
                $affected[$cat] = ($affected[$cat] ?? 0) + 1;
            }
        }

        $orderedCats = array_values(array_filter(
            array_map(fn (IssueCategory $c) => $c->value, IssueCategory::cases()),
            fn (string $c) => ($affected[$c] ?? 0) > 0,
        ));

        $writer = new Writer;
        $writer->openToFile($path);
        $bold = (new Style)->setFontBold();

        // Übersicht sheet.
        $writer->getCurrentSheet()->setName($this->sheetName(__('crawler.export_overview')));
        $writer->addRow(Row::fromValues([__('crawler.export_col_category'), __('crawler.export_col_affected')], $bold));
        foreach ($orderedCats as $cat) {
            $writer->addRow(Row::fromValues([__('crawler.category_group.'.$cat), $affected[$cat]]));
        }

        // One sheet per affected category.
        foreach ($orderedCats as $cat) {
            $problemCodes = array_values(array_filter(
                CheckCatalog::activeCodesForCategory($cat),
                fn (string $code) => CheckCatalog::isProblemCode($code),
            ));

            $sheet = $writer->addNewSheetAndMakeItCurrent();
            $sheet->setName($this->sheetName(__('crawler.category_group.'.$cat)));
            $writer->addRow(Row::fromValues([
                __('crawler.col_url'), __('crawler.col_status'), __('crawler.col_indexable'),
                __('crawler.col_inlinks'), __('crawler.export_col_problems'),
            ], $bold));

            $query = $crawl->pages()->orderBy('depth')->orderBy('url')
                ->where(function ($w) use ($problemCodes) {
                    foreach ($problemCodes as $code) {
                        $w->orWhereJsonContains('issues', $code);
                    }
                });

            foreach ($query->cursor() as $p) {
                $present = array_values(array_intersect($problemCodes, array_unique($p->issues ?? [])));
                if ($present === []) {
                    continue;
                }
                $labels = array_map(fn (string $code) => __('crawler.issue.'.$code), $present);
                $writer->addRow(Row::fromValues([
                    $p->url,
                    $p->status_code,
                    $p->is_indexable ? __('crawler.yes') : __('crawler.no'),
                    $p->inlinks_count,
                    implode(', ', $labels),
                ]));
            }
        }

        $writer->close();
    }

    /** Excel sheet names: ≤31 chars, without : \ / ? * [ ]. */
    private function sheetName(string $label): string
    {
        $clean = trim((string) preg_replace('/[:\\\\\/?*\[\]]/', ' ', $label));

        return mb_substr($clean === '' ? 'Sheet' : $clean, 0, 31);
    }
}
```
Verify the OpenSpout v4 class names/methods (`Writer`, `Row::fromValues`, `Style::setFontBold`, `getCurrentSheet`, `addNewSheetAndMakeItCurrent`, `setName`) against the installed package; adjust imports/signatures if v4 differs.

- [ ] **Step 6: Controller + route.** In `app/Http/Controllers/CrawlController.php` add (with `use App\Crawler\Export\CrawlXlsxExporter;` and `use Symfony\Component\HttpFoundation\BinaryFileResponse;`):
```php
public function exportXlsx(Request $request, string $crawl): BinaryFileResponse
{
    $project = $request->get('project');
    $model = $project->crawls()->findOrFail($crawl);

    $path = tempnam(sys_get_temp_dir(), 'crawl-xlsx-');
    app(CrawlXlsxExporter::class)->writeToFile($model, $path);

    return response()->download($path, "crawl-{$model->id}.xlsx", [
        'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ])->deleteFileAfterSend();
}
```
In `routes/web.php`, beside the existing `crawls/{crawl}/export` route, add:
```php
Route::get('/crawls/{crawl}/export/xlsx', [CrawlController::class, 'exportXlsx'])->name('app.project.crawls.export-xlsx');
```
(Keep it in the same auth + ProjectBindingMiddleware group.)

- [ ] **Step 7: GREEN + Pint.**
Run: `ddev php artisan test --filter=CrawlXlsxExportTest` → PASS.
Run: `ddev php artisan test --filter="CrawlController|Crawl"` → PASS (no regressions).
Run: `ddev exec vendor/bin/pint app/Crawler/Export app/Http/Controllers/CrawlController.php routes lang tests`.

- [ ] **Step 8: Commit.**
```bash
git add app/Crawler/Export/CrawlXlsxExporter.php tests/Feature/Crawler/CrawlXlsxExportTest.php app/Http/Controllers/CrawlController.php routes/web.php lang/en/crawler.php lang/de/crawler.php composer.json composer.lock
git commit -m "feat(crawler): XLSX export — Übersicht + one sheet per affected category"
```
(End commit body with `Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>`.)

---

## Task 2: Show-page export button

**Files:** Modify `resources/js/Pages/Crawls/Show.tsx`.

**Interfaces:** Consumes the `app.project.crawls.export-xlsx` route (Task 1) + `crawler.export_xlsx` lang key (Task 1).

- [ ] **Step 1: Locate the crawl actions.** Read `Show.tsx`; find the `PageHeader` / actions area and check whether a CSV export control already exists (a link to `app.project.crawls.export`). Co-locate the XLSX button there; if no CSV control is rendered, add the XLSX button to the `PageHeader` actions region.

- [ ] **Step 2: Add the button.** A native download anchor (NOT an Inertia visit), styled with the UI kit (`LinkButton` or the same anchor styling used for other header actions):
```tsx
<a
  href={route('app.project.crawls.export-xlsx', { project: project!.id, crawl: crawl.id })}
  className={/* reuse the kit's button/link styling used by sibling header actions */}
>
  {t('crawler.export_xlsx')}
</a>
```
Use the Ziggy `route()` object-param form `{ project: project!.id, crawl: crawl.id }` (project rule). If a CSV export anchor already exists, mirror its exact markup/styling and place the XLSX one next to it.

- [ ] **Step 3: Build + commit.**
Run: `ddev npm run build` → green.
```bash
git add resources/js/Pages/Crawls/Show.tsx
git commit -m "feat(crawler-ui): XLSX export button on the crawl show page"
```
(End body with the Co-Authored-By line.)

---

## Final verification (whole plan)

- [ ] `ddev php artisan test` — full suite green.
- [ ] `ddev npm run build` — green.
- [ ] `ddev exec vendor/bin/pint --test` — clean.
- [ ] Manual/live: open a completed crawl → click Export XLSX → the file has an Übersicht sheet whose counts match the sidebar category counts, and one sheet per affected category listing the affected URLs.

## Self-Review notes (author)

- **Spec coverage:** dependency + exporter + endpoint + route + lang + test (T1); UI button (T2). ✓
- **Counts match UI:** both use `isProblemCode` over `IssueCategory` order. ✓
- **Memory:** `cursor()` for count pass + per-category rows; OpenSpout streams. ✓
- **Info exclusion** proven by the test's page B (`https_urls`). ✓
- **Excel sheet-name limits** handled by `sheetName()`; **no CSV sanitize** on XLSX cells (documented). ✓
- **OpenSpout v4 API** flagged to verify against the installed package. ✓
- **CSV export untouched.** ✓
