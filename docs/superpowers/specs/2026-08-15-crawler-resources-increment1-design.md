# Crawler — Resource view, Increment 1 — Design

**Status:** approved for planning
**Context:** A ScreamingFrog-style resource listing for crawls. This is Increment 1 of a
3-increment feature: **(1) data model + type differentiation + internal resource tab**
(this doc), (2) external-resource probing, (3) polish (size totals, reference drill-down,
inline `background-image`).

## Goal

Capture every resource a crawled HTML page references (`<img>`, `<script>`, `<link>`,
`<source>`), differentiate resource types (JavaScript / CSS / Font / Image / …), and show
a **Resources** tab on the crawl overview listing each unique resource URL with its type,
reference count, internal/external flag, and — for internal resources already crawled —
its size and status. No new network requests in this increment (external resources are
listed but not yet probed — that is Increment 2).

## Scope

**In:**
- `crawl_resources` table + `CrawlResource` model capturing per-page resource references.
- `ResourceExtractor` analyzer that extracts referenced resources (with a tag-inferred
  type) from each HTML page; persisted by the observer (mirrors the existing out-links
  flow).
- `ResourceClassifier` gains `javascript` / `css` / `font` content categories (so crawled
  same-host JS/CSS/font resources are no longer lumped into `other`), with lang labels.
- A **Resources** tab on `Crawls/Show.tsx`: type-breakdown chips + a table (URL · type ·
  size · status · #references · internal/external) + a type filter. Backend serves the
  aggregation on demand (`?view=resources`).

**Out (later increments / not this feature):**
- Probing external resources for status/size (Increment 2) — external rows show size/status
  as unknown here.
- Reference drill-down ("exact pages referencing this resource"), size totals per type,
  inline-`style` background images (Increment 3).
- No new `IssueCode`/catalogue checks — this is a listing/reporting feature, not a check.

## Non-goals / constraints

- No new crawl-time network requests (extraction reads the already-fetched HTML; internal
  size/status come from the already-crawled `crawl_pages`).
- Multi-tenant + crawl scoping unchanged (`$project->crawls()->findOrFail`).
- Run via DDEV. Frontend gate `ddev npm run build`. Pint clean. Backend suite green.
- SSRF: no fetching here, so no new SSRF surface (Increment 2 will reuse `UrlSafety`/
  `LinkStatusChecker` patterns).

## Data model

**Migration `create_crawl_resources_table`** — mirrors `crawl_links`:
- `id` ULID pk; `crawl_id` (FK, cascade); `from_page_id` (FK → `crawl_pages`, cascade);
  `url` text; `type` string (`javascript|css|font|image|other`); `is_internal` boolean;
  timestamps. Index `['crawl_id', 'type']`.

**`App\Models\CrawlResource`** (ULID, `$guarded=['id']`) with `belongsTo(CrawlPage,
'from_page_id')`. Add `Crawl::resources()` (hasMany) and `CrawlPage::resources()`
(hasMany, `from_page_id`) — mirroring `outLinks`.

## Extraction: `ResourceExtractor`

New `App\Crawler\Analyzers\ResourceExtractor implements Analyzer`, registered in
`PageAnalyzer`. It reads the DOM + `PageContext` and adds `$data['resources']` — a list of
`['url' => <absolute>, 'type' => <type>, 'is_internal' => <bool>]`, **deduplicated by URL
within the page** (so reference count = number of distinct pages later).

Selectors + tag-inferred type (resolve relative URLs to absolute via a helper mirroring
`LinkExtractor::resolve`; skip empty / `data:` / `#` / `mailto:`/`tel:`/`javascript:`):
- `script[src]` → `javascript`
- `link[rel~="stylesheet"]`, `link[as="style"]` → `css`
- `link[rel~="preload"][as="font"]`, or a URL whose path ends `.woff`/`.woff2`/`.ttf`/`.otf`/`.eot` → `font`
- `img[src]`, `img[data-src]`, `source[src]`, `link[rel~="icon"]` → `image`
- anything else extracted → `other`

`is_internal` = the resource host equals `$ctx->baseHost` or ends with `.$ctx->baseHost`
(same rule as `LinkExtractor`).

**Observer persistence:** in `CrawlPageObserver::recordResponse()`'s HTML branch, after the
existing out-links creation, read `$analysis->data['resources']` (and `unset` it from
`$data` like `links`), then create a `crawl_resources` row per entry
(`$page->resources()->create([... 'crawl_id' => $this->crawl->id])`).

## Type differentiation: `ResourceClassifier`

Add categories `JAVASCRIPT='javascript'`, `CSS='css'`, `FONT='font'` and extend
`categorize()`:
- `application/javascript`, `text/javascript`, `application/x-javascript`, `application/ecmascript` → `JAVASCRIPT`
- `text/css` → `CSS`
- `font/*`, `application/font-woff*`, `application/vnd.ms-fontobject`, `application/x-font-*` → `FONT`
- (existing html/image/pdf/media rules unchanged; everything else → `OTHER`)

`sizeIssue()` must treat `javascript`/`css`/`font` with the same non-image "getting big"
tier it currently applies to `other` (so those resources still get `large_resource`/
`oversized_resource`). Add lang labels `crawler.category.javascript` / `.css` / `.font`
(en + de). The existing "All URLs" type filter picks the new values up automatically (its
options are the crawl's distinct `content_category` values).

## Backend: resources aggregation

`CrawlController::show()` gains a `?view=resources` branch (kept off the default page so the
normal load stays light). When requested, it returns `resources`: a list grouped by unique
`url` across `$model->resources()`:
- `url`, `type`, `is_internal`, `ref_count` = `COUNT(DISTINCT from_page_id)`.
- For internal resources, left-join the crawled `crawl_pages` by normalised URL to fill
  `status_code` + `size_bytes` (already fetched). External → null (probed in Increment 2).
- Also a `resource_summary`: per-type counts (for the breakdown chips).
- Paginated (like `pages`), `withQueryString()`.

`filters.view` (`'resources'|null`) and an optional `filters.resource_type` (type filter)
are shared to Inertia. Tenant scoping is unchanged.

## Frontend: Resources tab

`Crawls/Show.tsx` gains a **Resources** tab (label `crawler.tab_resources`) in the tab bar,
active when `filters.view === 'resources'`. When active it renders:
- **Type-breakdown chips** from `resource_summary` (JS / CSS / Images / Fonts / Other with
  counts).
- A **type filter** `<Select>` (all / javascript / css / font / image / other) → navigates
  with `?view=resources&resource_type=…`.
- A **table**: URL (external opens in new tab / internal links to the page detail if it is
  a crawled page) · Type (Badge) · Size (`formatBytes`, "—" when unknown) · Status ("—"
  when unknown) · References (count) · a small internal/external pill.
- `Pagination`.

New lang keys: `tab_resources`, `col_size`, `col_references`, `resource_internal`,
`resource_external` (+ the 3 category labels). No new severity, no design-token changes.

## Files

**Create**
- `database/migrations/2026_08_15_000006_create_crawl_resources_table.php`
- `app/Models/CrawlResource.php`
- `database/factories/CrawlResourceFactory.php`
- `app/Crawler/Analyzers/ResourceExtractor.php`
- `tests/Unit/Crawler/ResourceExtractorTest.php`
- `tests/Feature/Crawler/CrawlResourcesTest.php`

**Modify**
- `app/Crawler/ResourceClassifier.php` (+ its test), `app/Crawler/PageAnalyzer.php`
  (register), `app/Observers/CrawlPageObserver.php` (persist resources), `app/Models/{Crawl,CrawlPage}.php`
  (relations), `app/Http/Controllers/CrawlController.php` (resources view),
  `resources/js/Pages/Crawls/Show.tsx`, `lang/en/crawler.php`, `lang/de/crawler.php`,
  `resources/js/types/index.d.ts` (a `CrawlResourceRow` type if needed).

**No changes:** `IssueCode`/`CheckCatalog` (no new checks), other analyzers.

## Testing

- **`ResourceExtractorTest`** (unit): a page with `<script src>`, `<link rel=stylesheet>`,
  `<img>`, a `.woff2` preload, and an external CDN script → correct types + `is_internal`
  flags; duplicate `<img>` of the same URL is deduped; `data:`/inline are skipped.
- **`ResourceClassifierTest`**: `application/javascript` → `javascript`, `text/css` → `css`,
  `font/woff2` → `font`; existing html/image/pdf/media/other cases still hold; `sizeIssue`
  still fires for a large js/css/font.
- **`CrawlResourcesTest`** (feature): seed a crawl with pages + `crawl_resources` rows;
  `GET …/crawls/{crawl}?view=resources` returns the grouped resources with correct
  `ref_count`, internal size/status filled from the matching `crawl_page`, external
  size/status null, and `resource_summary` counts; the `resource_type` filter narrows the
  list; tenant isolation holds (another project's crawl → 404).
- **Observer**: recording an HTML page persists its `crawl_resources` rows (deduped per page).
- Gates: crawler namespace green, full suite green, Pint clean, `ddev npm run build` green.

## Risks / decisions

- **Reference count = distinct referencing pages** (dedup per page in the extractor +
  `COUNT(DISTINCT from_page_id)`), so a page referencing the same asset twice counts once.
- **Type is tag-inferred** in `crawl_resources` (consistent for internal + external);
  the `content_category` refinement is a parallel, content-type-accurate classification for
  the existing All-URLs filter. Two type fields, different views — documented.
- **External resources are listed but not probed** here (size/status "—"); Increment 2 adds
  SSRF-safe probing. Listing them is "from existing data" (references parsed from crawled
  HTML — no new fetch).
- **`sizeIssue` must cover the new categories** so js/css/font keep their large-resource
  flags after leaving the `other` bucket.
- **Row volume:** `crawl_resources` is one row per (page, distinct resource URL) — bounded
  like `crawl_links`; deleted with the crawl via the cascade FK.
