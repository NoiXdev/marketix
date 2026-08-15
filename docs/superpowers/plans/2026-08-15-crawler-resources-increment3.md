# Crawler — Resource view, Increment 3 — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Polish the Resources tab — per-type size totals, a reference drill-down (`?resource=<url>` panel), wider extraction (`srcset` + inline `background-image`), and a streamed GET size ceiling.

**Architecture:** Small, mostly-independent changes: `ResourceExtractor` (C), `ResourceProbe` (D), `CrawlController` resources branch (A + B backend), `Crawls/Show.tsx` (A + B frontend). No new checks/severity/migration.

**Tech Stack:** Laravel 13 / PHP 8.3, Inertia + React/TS, Symfony DomCrawler, Guzzle, PHPUnit, Pint.

**Spec:** `docs/superpowers/specs/2026-08-15-crawler-resources-increment3-design.md`

## Global Constraints

- No new `IssueCode`/`CheckCatalog`/checks/severity/bijection; no migration (`crawl_resources` already has the columns).
- Extraction adds no crawl-time network requests (parsed from fetched HTML). Multi-tenant scoping unchanged.
- Run via DDEV. Backend gate `ddev php artisan test`; frontend gate `ddev npm run build`; Pint clean `ddev exec vendor/bin/pint --test`.

---

## Task 1: `ResourceExtractor` — `srcset` + inline `background-image`

**Files:** Modify `app/Crawler/Analyzers/ResourceExtractor.php`, `tests/Unit/Crawler/ResourceExtractorTest.php`.

**Interfaces:** No new interface — extends `$data['resources']` with srcset/background image URLs (type `image`), deduped per page by the existing `$byUrl` map.

- [ ] **Step 1: Extend the extractor.** In `app/Crawler/Analyzers/ResourceExtractor.php`, in `analyze()`, AFTER the existing `$dom->filter('link[rel]')->each(...)` block and BEFORE `$r->add('resources', array_values($byUrl));`, add:

```php
        // Responsive images: srcset is a comma-separated list of "URL [descriptor]".
        $dom->filter('img[srcset], source[srcset]')->each(function (Crawler $n) use ($add) {
            foreach (explode(',', $n->attr('srcset') ?? '') as $candidate) {
                $url = trim(explode(' ', trim($candidate))[0]);
                if ($url !== '') {
                    $add($url, 'image');
                }
            }
        });

        // Inline background-image URLs (inline styles only — external CSS is not parsed).
        $dom->filter('[style]')->each(function (Crawler $n) use ($add) {
            if (preg_match_all('/background-image\s*:\s*[^;}]*?url\(\s*(["\']?)([^"\')]+)\1\s*\)/i', $n->attr('style') ?? '', $m)) {
                foreach ($m[2] as $url) {
                    $add(trim($url), 'image');
                }
            }
        });
```

  (`$add`/`resolve()` already skip `data:`/`#`/`mailto:`/`tel:`/`javascript:` and dedup by absolute URL.)

- [ ] **Step 2: Add test cases.** In `tests/Unit/Crawler/ResourceExtractorTest.php`, add:

```php
    public function test_extracts_srcset_and_inline_background_image(): void
    {
        $res = collect($this->extract(
            '<img srcset="/a.jpg 1x, /b.jpg 2x">'
            .'<div style="background-image: url(\'/bg.png\')"></div>'
            .'<span style="background-image:url(data:image/png;base64,AAAA)"></span>'
        ))->keyBy('url');

        $this->assertSame('image', $res['https://x.test/a.jpg']['type']);
        $this->assertArrayHasKey('https://x.test/b.jpg', $res->all());
        $this->assertSame('image', $res['https://x.test/bg.png']['type']);
        // data: background is skipped by resolve().
        $this->assertFalse(collect($res)->contains(fn ($r) => str_starts_with($r['url'], 'data:')));
    }
```

- [ ] **Step 3: Run + Pint.**

Run: `ddev php artisan test --filter=ResourceExtractorTest` → PASS (new + existing cases).
Run: `ddev exec vendor/bin/pint --test app/Crawler/Analyzers/ResourceExtractor.php tests/Unit/Crawler/ResourceExtractorTest.php` → PASS.

- [ ] **Step 4: Commit.** `git add app/Crawler/Analyzers/ResourceExtractor.php tests/Unit/Crawler/ResourceExtractorTest.php && git commit -m "feat(crawler): extract srcset + inline background-image resources"`

---

## Task 2: `ResourceProbe` — streamed GET size ceiling

**Files:** Modify `app/Crawler/ResourceProbe.php`, `tests/Unit/Crawler/ResourceProbeTest.php`.

**Interfaces:** `probe()` signature unchanged; GET fallback now streams and bounds the measured size.

- [ ] **Step 1: Add the cap + stream the GET fallback.** In `app/Crawler/ResourceProbe.php`, add a constant near the timeouts:

```php
    /** Bound the GET-fallback body read for resources without a Content-Length. */
    private const MAX_PROBE_BYTES = 10_485_760; // 10 MB
```

  Change the GET fallback in `probe()` to stream and use a bounded reader:

```php
            if (in_array($status, [405, 501], true) || $size === null) {
                $get = Http::timeout(self::TIMEOUT)->connectTimeout(self::CONNECT_TIMEOUT)
                    ->withOptions(['stream' => true])->get($url);
                $status = $get->status();
                $size = $this->contentLength($get) ?? $this->streamedSize($get);
            }
```

  Add the helper (below `contentLength()`):

```php
    /** Sum the body in bounded chunks; null if it exceeds MAX_PROBE_BYTES (too large to measure). */
    private function streamedSize(Response $response): ?int
    {
        $body = $response->toPsrResponse()->getBody();
        $size = 0;
        while (! $body->eof()) {
            $size += strlen($body->read(8192));
            if ($size > self::MAX_PROBE_BYTES) {
                return null;
            }
        }

        return $size;
    }
```

  (`Response` is already imported as `Illuminate\Http\Client\Response`.)

- [ ] **Step 2: Update/extend the test.** In `tests/Unit/Crawler/ResourceProbeTest.php`, the existing `test_no_content_length_uses_get_body_length` still asserts size 4 (now via `streamedSize` reading the faked body). Add an over-cap case:

```php
    public function test_get_fallback_returns_null_past_the_size_cap(): void
    {
        $big = str_repeat('a', 10 * 1024 * 1024 + 1); // 10 MB + 1 byte, no Content-Length
        Http::fakeSequence()
            ->push('', 200)      // HEAD: 200, no Content-Length
            ->push($big, 200);   // GET: no Content-Length → streamed, exceeds cap
        $result = (new ResourceProbe)->probe('https://example.com/huge.bin');
        $this->assertSame(200, $result['status']);
        $this->assertNull($result['size']);
    }
```

  (If `Http::fake` + `['stream'=>true]` + `toPsrResponse()->getBody()` does not yield a readable body under the installed client version, adapt the fake to a streamable body while preserving the intent: under-cap → exact size, over-cap → null, Content-Length present → header used without reading the body. Keep the resolvable-host `example.com` convention.)

- [ ] **Step 3: Run + Pint.**

Run: `ddev php artisan test --filter=ResourceProbeTest` → PASS (existing + new).
Run: `ddev exec vendor/bin/pint --test app/Crawler/ResourceProbe.php tests/Unit/Crawler/ResourceProbeTest.php` → PASS.

- [ ] **Step 4: Commit.** `git add app/Crawler/ResourceProbe.php tests/Unit/Crawler/ResourceProbeTest.php && git commit -m "feat(crawler): bound ResourceProbe GET size with a streamed cap"`

---

## Task 3: controller — per-type size totals + reference drill-down

**Files:** Modify `app/Http/Controllers/CrawlController.php`, `tests/Feature/Crawler/CrawlControllerTest.php`.

**Interfaces:** `resourceSummary` becomes `{type: {count, total_bytes}}`; adds `resourceRefs` (the referencing pages for `?resource=<url>`) + `filters.resource`.

- [ ] **Step 1: Rework the resources branch.** In `app/Http/Controllers/CrawlController.php::show()`, read `$resource = $request->query('resource');` (alongside `$view`/`$resourceType`). Replace the whole `if ($view === 'resources') { … }` block with:

```php
        $resources = null;
        $resourceRefs = null;
        $resourceSummary = [];
        if ($view === 'resources') {
            $resourceSummary = $model->resources()
                ->select('url', 'type', 'size_bytes')->distinct()->get()
                ->groupBy('type')
                ->map(fn ($g) => ['count' => $g->count(), 'total_bytes' => (int) $g->sum('size_bytes')])
                ->all();

            if (is_string($resource) && $resource !== '') {
                $pageIds = $model->resources()->where('url', $resource)->distinct()->pluck('from_page_id');
                $resourceRefs = [
                    'url' => $resource,
                    'pages' => $model->pages()->whereIn('id', $pageIds)->orderBy('url')
                        ->paginate(50)->withQueryString()
                        ->through(fn ($p) => ['id' => $p->id, 'url' => $p->url, 'status_code' => $p->status_code]),
                ];
            } else {
                $rq = $model->resources()
                    ->selectRaw('url, min(type) as type, max(is_internal) as is_internal, count(distinct from_page_id) as ref_count, min(status_code) as status_code, min(size_bytes) as size_bytes')
                    ->groupBy('url');
                if (is_string($resourceType) && $resourceType !== '') {
                    $rq->where('type', $resourceType);
                }
                $paginator = $rq->orderByDesc('ref_count')->paginate(50)->withQueryString();
                $paginator->getCollection()->transform(fn ($row) => [
                    'url' => $row->url,
                    'type' => $row->type,
                    'is_internal' => (bool) $row->is_internal,
                    'ref_count' => (int) $row->ref_count,
                    'status_code' => $row->status_code !== null ? (int) $row->status_code : null,
                    'size_bytes' => $row->size_bytes !== null ? (int) $row->size_bytes : null,
                ]);
                $resources = $paginator;
            }
        }
```

  Add to the `inertia('Crawls/Show', [...])` payload: `'resourceRefs' => $resourceRefs,` (keep `resources` + `resourceSummary`), and extend `filters` with `'resource' => is_string($resource) && $resource !== '' ? $resource : null,`.

- [ ] **Step 2: Update the resources feature test.** In `tests/Feature/Crawler/CrawlControllerTest.php`, update `test_resources_view_lists_grouped_resources` so the `resourceSummary` assertion matches the new shape, and add a drill-down assertion:

```php
        // resourceSummary is now {type: {count, total_bytes}}.
        $this->actingAs($user)
            ->get(route('app.project.crawls.show', ['project' => $project->id, 'crawl' => $crawl->id, 'view' => 'resources']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('resourceSummary.css.count', 1)
                ->where('resourceSummary.css.total_bytes', 4096)
                ->where('resources.data', fn ($rows) => collect($rows)->firstWhere('url', 'https://example.com/app.css')['ref_count'] === 2)
            );

        // Drill-down: ?resource=<url> returns the referencing pages, resources null.
        $this->actingAs($user)
            ->get(route('app.project.crawls.show', ['project' => $project->id, 'crawl' => $crawl->id, 'view' => 'resources', 'resource' => 'https://example.com/app.css']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('filters.resource', 'https://example.com/app.css')
                ->where('resources', null)
                ->where('resourceRefs.url', 'https://example.com/app.css')
                ->has('resourceRefs.pages.data', 2)
            );
```

  (The two `app.css` `CrawlResource` rows from Increment 2's seed already have `size_bytes=4096` and come from pages `$p1`/`$p2` → drill-down returns 2 pages. Adapt the `AssertableInertia` closure mechanics to the installed version if needed, preserving: `resourceSummary.css` = count 1 / total_bytes 4096; drill-down returns the 2 referencing pages with `resources` null.)

- [ ] **Step 3: Run + Pint.**

Run: `ddev php artisan test --filter=CrawlControllerTest` → PASS.
Run: `ddev exec vendor/bin/pint --test app/Http/Controllers/CrawlController.php tests/Feature/Crawler/CrawlControllerTest.php` → PASS.

- [ ] **Step 4: Commit.** `git add app/Http/Controllers/CrawlController.php tests/Feature/Crawler/CrawlControllerTest.php && git commit -m "feat(crawler): resource size totals + reference drill-down endpoint"`

---

## Task 4: `Show.tsx` — total chips + clickable URL + refs panel

**Files:** Modify `resources/js/Pages/Crawls/Show.tsx`, `resources/js/types/index.d.ts`, `lang/en/crawler.php`, `lang/de/crawler.php`.

**Interfaces:** Consumes `resourceRefs` + the widened `resourceSummary` (Task 3).

- [ ] **Step 1: Props + types.** In `resources/js/Pages/Crawls/Show.tsx`:
  - Change the `resourceSummary` prop type to `Record<string, { count: number; total_bytes: number }>`.
  - Add a `resourceRefs` prop: `{ url: string; pages: Paginated<{ id: string; url: string; status_code: number | null }> } | null`.
  - Add `resource: string | null` to the `Filters` type.

- [ ] **Step 2: Chips with totals.** In the resources block, change the chip map to:

```tsx
              {Object.entries(resourceSummary).map(([type, s]) => (
                <Badge key={type}>
                  {t(`crawler.category.${type}`)}: {s.count} · {formatBytes(s.total_bytes)}
                </Badge>
              ))}
```

  (The type-filter `<Select>` already maps `Object.keys(resourceSummary)` — unchanged.)

- [ ] **Step 3: Clickable URL + refs panel.** Restructure the `activeTab === 'resources'` region so it renders the refs panel when a resource is selected, else the list. Replace the block guard `{activeTab === 'resources' && resources && (` with a wrapper that handles both:

```tsx
        {activeTab === 'resources' && filters.resource && resourceRefs && (
          <>
            <div className="mb-3">
              <button type="button" onClick={() => go({ view: 'resources' })} className="text-sm text-accent-soft-foreground hover:underline">
                ← {t('crawler.tab_resources')}
              </button>
            </div>
            <div className="mb-3 break-all text-sm text-muted">{t('crawler.resource_referenced_on')}: <span className="text-foreground">{resourceRefs.url}</span></div>
            <TableCard columns={[{ label: t('crawler.col_url') }, { label: t('crawler.col_status') }]}>
              <tbody className="divide-y divide-line">
                {resourceRefs.pages.data.map((p) => (
                  <tr key={p.id} className="hover:bg-elevated">
                    <td className="px-4 py-3">
                      <Link href={route('app.project.crawls.pages.show', { project: project!.id, crawl: crawl.id, page: p.id })} className="font-medium text-foreground hover:text-accent-soft-foreground">{p.url}</Link>
                    </td>
                    <td className="px-4 py-3 text-muted">{p.status_code ?? '—'}</td>
                  </tr>
                ))}
              </tbody>
            </TableCard>
            <div className="mt-2"><Pagination links={resourceRefs.pages.links} /></div>
          </>
        )}

        {activeTab === 'resources' && !filters.resource && resources && (
          <>
            {/* existing chips + type filter + table + pagination block goes here, with the URL cell made clickable */}
          </>
        )}
```

  In the existing list table, make the URL cell a button that drills down:

```tsx
                    <td className="px-4 py-3">
                      <button type="button" onClick={() => go({ view: 'resources', resource: res.url })} className="break-all text-left font-medium text-foreground hover:text-accent-soft-foreground">
                        {res.url}
                      </button>
                      <span className="ml-2 text-xs text-muted">{res.is_internal ? t('crawler.resource_internal') : t('crawler.resource_external')}</span>
                    </td>
```

- [ ] **Step 4: Lang key.** Add `resource_referenced_on` to BOTH lang files — EN `'resource_referenced_on' => 'Referenced on'`, DE `'resource_referenced_on' => 'Referenziert auf'`.

- [ ] **Step 5: Build + Pint.**

Run: `ddev npm run build` → green (widened `resourceSummary` type, `resourceRefs` prop, panel all compile).
Run: `ddev exec vendor/bin/pint --test lang/en/crawler.php lang/de/crawler.php` → PASS.
Run: `ddev php artisan test --filter=CrawlControllerTest` → PASS (unchanged, sanity).

- [ ] **Step 6: Commit.** `git add resources/js/Pages/Crawls/Show.tsx resources/js/types/index.d.ts lang/en/crawler.php lang/de/crawler.php && git commit -m "feat(crawler): resource size-total chips + reference drill-down panel"`

---

## Final verification (whole plan)

- [ ] `ddev php artisan test` — full suite green.
- [ ] `ddev exec vendor/bin/pint --test` — clean.
- [ ] `ddev npm run build` — green.

## Self-Review notes (author)

- **Spec coverage:** srcset + inline bg (Task 1), streamed GET cap (Task 2), size totals + drill-down backend (Task 3), chips + clickable URL + refs panel (Task 4). ✓
- **No checks/bijection/migration impact.** ✓
- **Distinct-URL totals** avoid the row-multiplication a raw `SUM` GROUP BY would cause; drill-down skips the list to stay light; back link returns. ✓
- **Probe cap** bounds memory; `Content-Length` present → body never read; test covers under- and over-cap (fake-streamability caveat noted). ✓
- **srcset/bg → image**, inline-only, dedup preserved. ✓
