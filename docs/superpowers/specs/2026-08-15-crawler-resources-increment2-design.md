# Crawler — Resource view, Increment 2 — Design

**Status:** approved for planning
**Depends on:** Resource view Increment 1 (crawl_resources + ResourceExtractor + Resources
tab). This increment probes **external** resources for status + size (internal resources
already have both from the crawl), so the Resources tab shows real values instead of "—".

## Goal

Populate `status_code` + `size_bytes` for every resource in the Resources view: internal
resources from the already-crawled `crawl_pages` (normalised match), external resources via
an SSRF-safe HTTP probe (HEAD `Content-Length`, GET fallback). Fill it during
`AggregateCrawlJob`; the controller then reads status/size straight from `crawl_resources`.

## Scope

**In:**
- `crawl_resources` gains nullable `status_code` + `size_bytes` columns.
- `ResourceProbe` service: `probe(string $url): array{status: ?int, size: ?int}` — same
  host-safety gate as `LinkStatusChecker`; HEAD → status + `Content-Length`; if HEAD is
  405/501 **or** has no usable `Content-Length` → GET → status + `Content-Length` (else
  `strlen(body)`); any failure / unsafe host → `{null, null}`.
- `AggregateCrawlJob::checkResources()` (new step): for each distinct resource URL, set
  `status_code`/`size_bytes` on all its rows — internal from a normalised `crawl_pages`
  meta map, external via `ResourceProbe`, deduped and capped at `MAX_RESOURCE_PROBES`
  (2000, separate from the link-probe budget).
- `CrawlController::show()` resources branch reads `status_code`/`size_bytes` from the
  grouped `crawl_resources` query (dropping the Increment-1 `crawl_pages` `whereIn` join).
  This also resolves the Increment-1 parked minor (exact-string vs normalised internal
  join): the aggregate now matches internal resources to pages with the same `$norm`
  (trailing-slash trim) used everywhere else.

**Out / unchanged:**
- No new `IssueCode`/checks, no new severity, no frontend changes (the existing Size/Status
  columns simply render real values now). No new resource types.
- No probing of internal resources (they carry the crawl's own status/size); an internal
  resource that was not crawled stays null.
- `srcset`, size totals, reference drill-down remain Increment 3.

## Non-goals / constraints

- SSRF: only public, resolvable hosts are probed (`UrlSafety::hostIsSafe`), identical to
  `LinkStatusChecker`. Private/reserved/unresolvable → skipped (`{null,null}`).
- Runs in `AggregateCrawlJob` (`timeout=0`, `tries=1`) — long crawls are fine.
- Multi-tenant scoping unchanged. Run via DDEV; Pint clean; backend suite green; frontend
  build stays green (no FE change, but the type filter/table already handle real values).

## Component: `ResourceProbe`

New `App\Crawler\ResourceProbe` (mirrors `LinkStatusChecker`'s timeouts + safety):

```php
/** @return array{status: ?int, size: ?int} */
public function probe(string $url): array
```

- Reject non-http(s) / non-string host / `! UrlSafety::hostIsSafe($host)` → `['status'=>null,'size'=>null]`.
- `HEAD` (timeout 8s / connect 4s): `$status = head->status()`, `$size = contentLength(head)`.
- If `$status ∈ {405,501}` OR `$size === null` → `GET`: `$status = get->status()`,
  `$size = contentLength(get) ?? strlen(get->body())`.
- `contentLength()` reads the `Content-Length` header, returns an int only when present and
  all-digits, else null.
- Wrap in try/catch → `{null,null}` on any transport error (same lenient behaviour as
  `LinkStatusChecker`).

## Aggregation: `AggregateCrawlJob::checkResources()`

Add `private const MAX_RESOURCE_PROBES = 2000;` and call `$this->checkResources($norm);`
in `handle()` after `checkBrokenLinks()` (both are post-crawl passes; order independent).

```
checkResources($norm):
  meta = {}                      # norm(url) => ['status'=>?, 'size'=>?]
  for each crawl_pages row (url, final_url, status_code, size_bytes):
      meta[norm(url)] = {status,size}; and for final_url when set
  probe = app(ResourceProbe); probes = 0
  for each DISTINCT (url, is_internal) in this crawl's resources:
      if is_internal and meta[norm(url)] exists:  status,size = meta[...]
      elif not is_internal and probes < MAX_RESOURCE_PROBES:
          {status,size} = probe->probe(url); probes++
      else: continue            # internal-not-crawled, or over cap → leave null
      if status !== null or size !== null:
          crawl.resources().where('url', url).update(status_code=status, size_bytes=size)
  if probes >= MAX_RESOURCE_PROBES: Log::warning(cap hit)
```

Idempotent: safe to re-run (updates the same rows). External hosts that fail/rebind → null
(no false data). The distinct pass dedupes so each external URL is probed at most once.

## Controller change

In `CrawlController::show()`'s `?view=resources` branch (Increment 1), extend the grouped
select with `min(status_code) as status_code, min(size_bytes) as size_bytes` and drop the
`crawl_pages` `whereIn` map + the per-row internal/external size/status ternary. The
transform reads the two columns directly (cast to int-or-null). `status_code`/`size_bytes`
are consistent per URL (the aggregate writes the same value to all rows of a URL), so
`min()` is a safe strict-`GROUP BY` aggregate.

## Files

**Create**
- `database/migrations/2026_08_15_000007_add_status_size_to_crawl_resources.php`
- `app/Crawler/ResourceProbe.php`
- `tests/Unit/Crawler/ResourceProbeTest.php`

**Modify**
- `app/Jobs/AggregateCrawlJob.php` — `MAX_RESOURCE_PROBES`, `checkResources()`, call in `handle()`.
- `app/Http/Controllers/CrawlController.php` — read status/size from the grouped query.
- `tests/Feature/Crawler/AggregateCrawlJobTest.php`, `tests/Feature/Crawler/CrawlControllerTest.php`.

**No changes:** models (columns are `$guarded`-friendly; mass-assign via `update()` on the
query builder), `ResourceExtractor`, `Show.tsx`, lang, `IssueCode`/catalogue.

## Testing

- **`ResourceProbeTest`** (unit, `Http::fake`): HEAD with `Content-Length` → `{status,size}`;
  HEAD 405 → GET fallback used; HEAD 200 without `Content-Length` → GET fallback, size from
  header or body length; an unsafe/private host (e.g. `http://127.0.0.1/x`) → `{null,null}`
  without any HTTP call.
- **`AggregateCrawlJobTest`** (feature, `Http::fake` for the external URL): seed a crawl
  with an internal resource whose URL matches a crawled `crawl_page` (status/size filled
  from the page, incl. a trailing-slash-normalised match to prove the join fix) and an
  external resource (probed → faked status/size); assert both `crawl_resources` rows get the
  expected `status_code`/`size_bytes`; assert an internal resource with no crawled page stays
  null.
- **`CrawlControllerTest`** (feature): with `crawl_resources` rows carrying `status_code`/
  `size_bytes`, `?view=resources` returns those values (internal + external) directly — the
  Increment-1 test that relied on the `crawl_pages` join is updated to seed the columns on
  `crawl_resources` instead.
- Gates: crawler namespace green, full suite green, Pint clean, `ddev npm run build` green.

## Risks / decisions

- **GET fallback downloads the body** when a server omits `Content-Length` (per the user's
  accuracy-over-egress choice); bounded by `MAX_RESOURCE_PROBES` distinct external URLs.
- **Separate probe budget** (`MAX_RESOURCE_PROBES`) so resource probing doesn't consume the
  broken-link budget.
- **Post-aggregation population:** resource status/size are set during `AggregateCrawlJob`;
  a Resources tab viewed mid-crawl briefly shows "—" until aggregation runs — acceptable
  (resources are a post-crawl report).
- **Internal join now normalised** via `$norm` (fixes the Increment-1 exact-string parked
  minor); an internal resource whose URL matches no crawled page (e.g. beyond `max_pages`)
  stays null rather than being probed.
