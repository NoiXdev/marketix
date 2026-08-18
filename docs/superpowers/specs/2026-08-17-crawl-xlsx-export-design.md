# Crawl XLSX export — Design

**Status:** awaiting user review
**Scope:** a new XLSX export of a crawl — one worksheet per issue category, listing the affected
URLs — alongside the existing CSV export. Backend export service + endpoint + a UI button.

## Goal

From a crawl's Show page, download an `.xlsx` where:
- a leading **"Übersicht"** sheet lists each category with its count of affected URLs;
- then **one sheet per category that has ≥1 affected URL** (empty categories skipped), each row
  an affected URL with context + the failed checks of that category.

Decisions locked with the user: rows = **URL + context + problem list**; sheets =
**Übersicht + only affected categories**; library = **`openspout/openspout`** (streaming, low
memory — matches the crawler's "unlimited pages" design).

## Definitions

- A page is **affected** by category *C* if it has ≥1 issue code whose `CheckCatalog::categoryOf`
  is *C* **and** `CheckCatalog::isProblemCode` is true (so `info` signals like `https_urls` never
  count — consistent with the UI's category counts).
- The **problem list** cell for a page on category *C*'s sheet = the translated labels
  (`crawler.issue.<code>`) of that page's problem codes belonging to *C*, joined by `", "`.

## Sheets

1. **Übersicht** (always first). Header row `[Kategorie, Betroffene URLs]`; one row per category
   (in `IssueCategory::cases()` order) that has ≥1 affected URL — category label
   (`crawler.category_group.<cat>`) + count. Categories with 0 affected URLs are omitted.
2. **One sheet per affected category** (same order). Sheet title = the category label, sanitised
   for Excel (≤31 chars, strip `: \ / ? * [ ]`). Header row
   `[URL, Status, Indexierbar, Inlinks, Probleme]`; one row per affected page:
   - URL (`page.url`), Status (`status_code`), Indexierbar (`is_indexable` → „Ja"/„Nein"),
     Inlinks (`inlinks_count`), Probleme (the problem list defined above).
   Pages ordered by `depth` then `url` (mirrors the existing export/list order).

If a crawl has zero affected URLs in every category, the file still downloads with just the
Übersicht sheet (with only its header) — no empty error.

## Backend

- **Dependency:** add `openspout/openspout` (^4) via composer.
- **Service** `App\Crawler\Export\CrawlXlsxExporter` — HTTP-decoupled and unit-testable:
  `writeToFile(Crawl $crawl, string $path): void`. It:
  1. Counts affected URLs per category in ONE `cursor()` pass over the crawl's pages (reading only
     `issues`), building the ordered list of affected categories + counts (memory-bounded).
  2. Writes the Übersicht sheet from those counts.
  3. For each affected category, opens a new sheet and streams its rows from a
     `cursor()` query filtered to that category's active problem codes (an `orWhereJsonContains`
     over the codes — same shape as `CrawlController::show`'s `group` filter), computing each row's
     problem list by intersecting `page.issues` with the category's problem codes.
  Uses OpenSpout's `Writer\XLSX\Writer` with a bold header row (a `Style` with `setFontBold`).
- **Controller** `CrawlController::exportXlsx(Request, string $crawl): BinaryFileResponse` — resolves
  the crawl via the project (tenant-scoped `findOrFail`, like `export`), writes to a temp file
  (`tempnam(sys_get_temp_dir(), 'crawl-xlsx-')`), returns
  `response()->download($path, "crawl-{$model->id}.xlsx", [...xlsx content-type...])
  ->deleteFileAfterSend()`.
- **Route** `GET /project/{project}/crawls/{crawl}/export/xlsx` →
  name `app.project.crawls.export-xlsx`, in the same auth + `ProjectBindingMiddleware` group as the
  existing `export` route. The existing CSV `export` route/method stay unchanged.
- **Formula-injection:** XLSX string cells are written as inline strings, not formulas, so a
  leading `=`/`+`/`-`/`@` is stored literally (unlike CSV). No `sanitizeCsvCell` prefix is applied
  (it would visibly corrupt values); documented as the deliberate reason it's safe here.

## Frontend

- On `resources/js/Pages/Crawls/Show.tsx`, add an **"Export XLSX"** action next to wherever the
  crawl actions/header live (co-locate with the existing CSV export control if one is rendered;
  otherwise add it to the `PageHeader` actions). It's a plain download link to
  `route('app.project.crawls.export-xlsx', { project, crawl })` — a normal `<a href>` (native
  download; not an Inertia visit). Reuse the UI kit's `LinkButton`/anchor styling.

## i18n

Reuse existing `crawler.col_url`, `crawler.col_status`, `crawler.col_indexable`,
`crawler.col_inlinks`, `crawler.category_group.<cat>`, `crawler.issue.<code>`, and „Ja"/„Nein"
(reuse existing yes/no keys if present, else add `crawler.yes`/`crawler.no`). Add (en+de):
`crawler.export_xlsx` (button), `crawler.export_overview` (Übersicht sheet title + label),
`crawler.export_col_category`, `crawler.export_col_affected`, `crawler.export_col_problems`.

## Files

**Create:** `app/Crawler/Export/CrawlXlsxExporter.php`, `tests/Feature/Crawler/CrawlXlsxExportTest.php`.
**Modify:** `composer.json`/`composer.lock` (openspout), `app/Http/Controllers/CrawlController.php`
(exportXlsx), `routes/web.php` (route), `resources/js/Pages/Crawls/Show.tsx` (button),
`lang/en/crawler.php`, `lang/de/crawler.php`.
**No change:** the CSV export, analyzers, catalogue, models.

## Testing

- `CrawlXlsxExportTest` (feature): seed a crawl with a member user + a few pages carrying issues in
  ≥2 categories (e.g. `missing_title` → page_title, `missing_csp_header` → security) plus one page
  with only an `info` code (`https_urls`) to prove it is NOT counted as affected. GET the route →
  assert 200 + the xlsx content-type + `Content-Disposition` filename. Then read the returned bytes
  back with OpenSpout's `Reader\XLSX\Reader` and assert: an "Übersicht" sheet exists with the right
  category→count rows; a category sheet exists (title = the localised label) containing the affected
  URL row and its problem-label cell; a category with only-info pages has NO sheet.
- Gates: `ddev composer require openspout/openspout` succeeds; `ddev php artisan test` green;
  `ddev npm run build` green; `ddev exec vendor/bin/pint --test` clean.

## Risks / decisions

- **Memory:** counts + rows both stream via `cursor()`; OpenSpout streams sheet rows to disk — the
  whole workbook is not held in RAM. Safe for large crawls.
- **Excel sheet-name limits** (≤31 chars, reserved chars, uniqueness): category labels are short and
  unique; sanitise + truncate defensively.
- **Temp file** is written to the system temp dir and removed via `deleteFileAfterSend()`.
- **Not reusing the CSV path:** the XLSX export is a separate service/endpoint; the CSV export is
  untouched. A shared "affected pages per category" helper is small enough to live in the exporter.
- **`info` excluded** from affected-counts and problem lists (matches the UI's problem semantics).
