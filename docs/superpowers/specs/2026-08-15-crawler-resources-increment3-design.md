# Crawler — Resource view, Increment 3 — Design

**Status:** approved for planning
**Depends on:** Resource view Increments 1 (listing) + 2 (external probing). This is the
polish increment: per-type size totals, a reference drill-down, wider resource capture
(`srcset` + inline `background-image`), and a memory ceiling on the GET size fallback.

## Goal

Round out the Resources tab: show total bytes per type, let a resource URL drill down to
the pages that reference it, capture responsive-image (`srcset`) and inline
`background-image` resources, and bound the GET-fallback body read.

## Scope

**In:**
- **A. Per-type size totals** — `resourceSummary` becomes `{type: {count, total_bytes}}`
  (summed over DISTINCT URLs so a multiply-referenced asset counts once). Chips show
  count + summed size.
- **B. Reference drill-down** — `?view=resources&resource=<url>` returns the pages that
  reference that resource (paginated), shown as a panel with a back link; each resource
  row's URL links into it.
- **C. Wider extraction** — `ResourceExtractor` also captures `img[srcset]` /
  `source[srcset]` candidate URLs and inline `style="background-image:url(…)"` URLs (both
  → `image`; inline styles only, documented — external CSS is not parsed).
- **D. GET size ceiling** — `ResourceProbe`'s GET fallback streams the body and counts
  bytes up to `MAX_PROBE_BYTES` (10 MB); within → exact size, beyond → `null` (too large
  to measure without buffering). Bounds memory for `Content-Length`-less resources.

**Out / unchanged:** no new `IssueCode`/checks/severity/bijection; no new resource types
(srcset/bg are `image`); external CSS `@import`/`url()` not parsed; the `resource_type`
filter + Increment-2 status/size behaviour unchanged.

## Non-goals / constraints

- No new crawl-time network requests from extraction (srcset/bg are parsed from the
  already-fetched HTML). Probing unchanged except the streamed byte cap.
- Multi-tenant scoping unchanged. Run via DDEV; Pint clean; backend suite green; frontend
  build green.

## A — Per-type size totals (controller + UI)

`CrawlController::show()` resources branch: replace the `resourceSummary` count-only query
with a distinct-URL aggregation:

```php
$resourceSummary = $model->resources()
    ->select('url', 'type', 'size_bytes')->distinct()->get()
    ->groupBy('type')
    ->map(fn ($g) => ['count' => $g->count(), 'total_bytes' => (int) $g->sum('size_bytes')])
    ->all();
```

(`size_bytes` null → summed as 0.) `Show.tsx` chips render `{label} · {count} · {formatBytes(total_bytes)}`. The `resourceSummary` prop type becomes `Record<string, {count: number; total_bytes: number}>`.

## B — Reference drill-down (controller + UI)

Controller reads `$resource = $request->query('resource')`. When `$view === 'resources'`
AND `$resource` is a non-empty string, compute `resourceRefs` and SKIP the paginated
resources list (`$resources = null`; keep `resourceSummary` for the chips):

```php
$resourceRefs = null;
if ($view === 'resources' && is_string($resource) && $resource !== '') {
    $pageIds = $model->resources()->where('url', $resource)->distinct()->pluck('from_page_id');
    $refPages = $model->pages()->whereIn('id', $pageIds)->orderBy('url')
        ->paginate(50)->withQueryString()
        ->through(fn ($p) => ['id' => $p->id, 'url' => $p->url, 'status_code' => $p->status_code]);
    $resourceRefs = ['url' => $resource, 'pages' => $refPages];
}
```

Share `resourceRefs` + `filters.resource` (`$resource` or null) to Inertia. `Show.tsx`:
when on the Resources tab, each row's URL is a button → `go({ view: 'resources', resource:
res.url })`. When `filters.resource` is set, render a **panel** instead of the resource
table: a back link (`go({ view: 'resources' })`), the resource URL heading, and a table of
referencing page URLs (each linking to the page-detail route) + `Pagination`. Tenant
scoping via the same `$model`.

## C — Wider extraction (`ResourceExtractor`)

Reuse the existing `$add(url, type)` closure. After the current selectors, add:

- **srcset:** `img[srcset], source[srcset]` — split the attribute on commas; each candidate
  is `URL [descriptor]`, so take the first whitespace-separated token as the URL; `$add(url,
  'image')`.
- **inline background-image:** `[style]` elements — `preg_match_all` for
  `background-image` `url(...)` occurrences and `$add(url, 'image')` each. The existing
  `resolve()` already skips `data:` URIs, so inline data-URI backgrounds are ignored.

Dedup-per-page still holds (the `$byUrl` map keys by absolute URL). The `background_images`
issue check (Phase 8) is unaffected.

## D — GET size ceiling (`ResourceProbe`)

Add `private const MAX_PROBE_BYTES = 10_485_760; // 10 MB`. In the GET fallback, request in
streaming mode and measure the body in bounded chunks only when there is no usable
`Content-Length`:

```php
$get = Http::timeout(self::TIMEOUT)->connectTimeout(self::CONNECT_TIMEOUT)
    ->withOptions(['stream' => true])->get($url);
$status = $get->status();
$size = $this->contentLength($get) ?? $this->streamedSize($get);
```

`streamedSize()` reads the PSR-7 body in 8 KB chunks, summing lengths; if it exceeds
`MAX_PROBE_BYTES` it stops and returns `null` (unknown — too large to buffer). When a
`Content-Length` is present, the body is never read. HEAD path unchanged.

## Files

**Modify**
- `app/Crawler/Analyzers/ResourceExtractor.php` (+ `tests/Unit/Crawler/ResourceExtractorTest.php`)
- `app/Crawler/ResourceProbe.php` (+ `tests/Unit/Crawler/ResourceProbeTest.php`)
- `app/Http/Controllers/CrawlController.php` (+ `tests/Feature/Crawler/CrawlControllerTest.php`)
- `resources/js/Pages/Crawls/Show.tsx`, `resources/js/types/index.d.ts`, `lang/en/crawler.php`, `lang/de/crawler.php`

**No changes:** models, migrations (`crawl_resources` already has the columns),
`IssueCode`/`CheckCatalog`, `AggregateCrawlJob`.

## Testing

- **`ResourceExtractorTest`**: an `<img srcset="a.jpg 1x, b.jpg 2x">` yields both `a.jpg`
  and `b.jpg` as `image`; a `<div style="background-image:url('bg.png')">` yields `bg.png`
  as `image`; a `data:` background is skipped; existing extraction cases still pass.
- **`ResourceProbeTest`**: GET fallback with a body under the cap → exact size; a body
  over `MAX_PROBE_BYTES` (fake a large streamed body, or temporarily assert the chunked
  counter returns null past the cap) → `null`; `Content-Length` present → body never
  streamed (size from header). Keep the resolvable-host (`example.com`) convention.
- **`CrawlControllerTest`**: `?view=resources` returns `resourceSummary` as
  `{type:{count,total_bytes}}` with the summed distinct-URL size; `?view=resources&resource=<url>`
  returns `resourceRefs.pages` = the distinct pages referencing that URL (and `resources`
  null), tenant-scoped; without `resource`, `resourceRefs` is null.
- Frontend: `ddev npm run build` green (chips + panel + widened `resourceSummary` type
  compile).
- Gates: crawler namespace green, full suite green, Pint clean, build green.

## Risks / decisions

- **Distinct-URL PHP aggregation** for `resourceSummary` (bounded per crawl); avoids the
  row-multiplication a raw `SUM(size_bytes)` GROUP BY would cause.
- **Drill-down replaces the list** (list not computed when `resource` is set) to keep the
  request light; chips remain for context; back link returns to the list.
- **srcset/background are `image`** — no new type; inline `background-image` only (external
  CSS unparsed), consistent with the Phase-8 `background_images` check's scope.
- **10 MB probe cap → null past it** (honest "unknown, too large") rather than a misleading
  floor; bounds memory for `Content-Length`-less external resources. Test compatibility:
  the streamed read must still work under `Http::fake` (PSR-7-backed) — if a fake body
  isn't streamable, the test adapts while preserving the cap intent.
