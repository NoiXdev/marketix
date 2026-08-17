# Hreflang checks — Phase design

**Status:** awaiting user review
**Scope:** new `hreflang` check category (15th `IssueCategory`) — per-page `HreflangAnalyzer`
+ cross-page checks in `AggregateCrawlJob` + a new `crawl_pages.hreflang` column + catalogue/
enum entries + page-detail evidence card + lang keys. Mirrors the Phase-6 (Canonicals/
Pagination) architecture.

## Goal

Add Screaming-Frog-style hreflang analysis. Parse `<link rel="alternate" hreflang="…">`
annotations from the HTML `<head>` **and** the HTTP `Link:` header, validate them per-page,
and validate the reciprocal cluster across the crawled (same-host) pages. Off-host hreflang
targets (the common cross-domain / ccTLD case) get **status probing only** — we don't crawl
them, so we can't validate their return links or content.

Decisions locked with the user: **same-host clusters fully validated; off-host targets get a
status probe only**; annotations read from **HTML `<link>` + HTTP `Link:` header** (not XML
sitemap alternates).

## Checks (map to Screaming Frog's 12; category `hreflang`)

**Per-page** (`HreflangAnalyzer`, HTML pages; applies to every annotation regardless of host):

1. `hreflang_incorrect_codes` (warning) — a hreflang value is neither `x-default` nor a valid
   BCP-47-ish code: language ∈ ISO 639-1, optional 4-letter Titlecase script, optional region
   ∈ ISO 3166-1 alpha-2 (e.g. flags `en-UK`, `zz`, `de_DE`). Validated via a `HreflangCodes`
   helper holding the ISO 639-1 + ISO 3166-1 sets; lenient on script to avoid false positives.
2. `hreflang_multiple_entries` (warning) — the same hreflang value appears more than once on
   the page (duplicate language entries).
3. `hreflang_outside_head` (warning) — a hreflang `<link>` sits outside `<head>` (DOM only;
   header-sourced entries are always valid).
4. `hreflang_missing_self_reference` (notice) — the page has annotations but none resolves to
   the page's own URL (a self-referential entry is required).
5. `hreflang_missing_x_default` (notice) — the annotation set has no `x-default` entry.
6. `hreflang_not_using_canonical` (warning) — the page carries hreflang annotations while being
   non-self-canonical (its `canonical` differs from its own URL) — annotations belong on the
   canonical version.

**Cross-page** (`AggregateCrawlJob`, using a cluster map built from the stored annotations):

7. `hreflang_non_200` (warning) — a referenced hreflang target returns non-200. Same-host: read
   from the crawl's status map. **Off-host: from a status probe** (HEAD→GET, SSRF-safe, deduped,
   capped — reuse `LinkStatusChecker`). Unresolvable/blocked hosts are skipped (conservative,
   like broken-link detection).
8. `hreflang_missing_return_link` (warning) — page A lists same-host page B as an alternate, but
   B does not list A back. Off-host B skipped (no HTML).
9. `hreflang_non_canonical_return_link` (warning) — A references a same-host URL for B whose
   canonical (on the crawled B) differs from the referenced URL — i.e. A points at a
   non-canonical version. Same-host only.
10. `hreflang_inconsistent_language` (warning) — the confirmation is inconsistent: A declares B
    as language `x`, but B's self/return declaration for that URL uses a different language.
    Same-host only.
11. `hreflang_noindex_return_link` (warning) — a same-host hreflang target is non-indexable
    (`is_indexable === false`).
12. `hreflang_unlinked` (notice) — a same-host hreflang target reachable via no normal `<a>`
    inlink (only referenced through hreflang), analogous to `orphan_page`. Uses the inlink map
    already computed in aggregation.

All 12 are `status = active`. Severities mirror SF's Issue/Warning split mapped to our scale
(SF-Issue → `warning`, SF-Warning → `notice`); none rise to `error` (hreflang faults are rarely
site-breaking). Final severities are as annotated above.

## Data model

- **New column** `crawl_pages.hreflang` (JSON, nullable) — the page's resolved annotation set:
  `[{ "lang": "en-GB", "href": "https://…/en-gb" }, …]` (absolute, resolved against the page
  URL; DOM + header entries merged, de-duplicated by lang+href). Null when the page has none.
- Migration `2026_08_17_000001_add_hreflang_to_crawl_pages.php`; `'hreflang' => 'array'` cast on
  `CrawlPage`.
- `PageContext` gains `?string $linkHeader = null`; the observer passes the raw HTTP `Link:`
  header value (`$lower['link'][0] ?? null`) so the analyzer can parse header-sourced
  annotations alongside the DOM.

## Flow

- **Observer** (`recordResponse`, HTML branch): capture the `Link` header into `PageContext`;
  `HreflangAnalyzer` returns issues + `data['hreflang']` (the annotation array), which the
  observer persists to the new column exactly like `pagination_next/prev` today.
- **`HreflangAnalyzer`**: parse DOM `link[rel="alternate"][hreflang]` (+ out-of-head detection
  via `filterXPath`) and the `Link:` header (`<url>; rel="alternate"; hreflang="xx"` comma-list);
  resolve hrefs to absolute; emit per-page checks 1–6; return the merged annotation set in
  `data['hreflang']`.
- **`AggregateCrawlJob`**: extend the page `select` to include `hreflang`; build
  `hreflangByUrl[normUrl] = { lang→hrefNorm map, selfLang, canonicalNorm, indexable, status }`
  and the reverse cluster. Run checks 7–12 in the existing per-page `chunkById` pass (append to
  `$page->issues`), same pattern as the canonical/pagination cross-page block. Off-host targets:
  gather their URLs, probe statuses once (capped), flag `hreflang_non_200`.

## UI

- `CATEGORY_ORDER` (shared `resources/js/lib/crawlerCategories.ts`) gains `'hreflang'` (inserted
  after `'pagination'`). The Show sidebar category list + the page-detail checks table pick it up
  automatically (the 12 codes are detail-less → they render as ✗ rows in the page-detail
  "Checks" table under a **Hreflang** group, and drive the Show category view/counts).
- **New evidence card** `hreflangCard` on the page-detail Überblick grid (shown when the page has
  annotations): a small table of `hreflang → href`, each href a link. Purely presentational,
  from the new `page.hreflang` field (add it to `CrawlPageDetail` + the `pageDetail` payload).
- Lang keys (en+de): `category_group.hreflang`; `issue.<code>` + `issue_help.<code>` for all 12
  codes; a `hreflang` card heading key.

## Files

**Create:** `app/Crawler/Analyzers/HreflangAnalyzer.php`, `app/Crawler/HreflangCodes.php`
(ISO 639-1 + ISO 3166-1 sets + `isValid()`), the migration, and analyzer/validator tests.

**Modify:** `app/Crawler/IssueCode.php` (+12 cases), `app/Crawler/IssueCategory.php`
(+`Hreflang`), `app/Crawler/CheckCatalog.php` (+12 active entries), `app/Crawler/PageContext.php`
(+`linkHeader`), `app/Crawler/PageAnalyzer.php` (register `HreflangAnalyzer`),
`app/Observers/CrawlPageObserver.php` (pass Link header, persist `hreflang`),
`app/Jobs/AggregateCrawlJob.php` (cluster map + checks 7–12 + off-host probe),
`app/Models/CrawlPage.php` (cast), `app/Http/Controllers/CrawlController.php` (`hreflang` in
pageDetail), `resources/js/lib/crawlerCategories.ts` (+`hreflang`),
`resources/js/Pages/Crawls/Page.tsx` (`hreflangCard` + type), `lang/en/crawler.php`,
`lang/de/crawler.php`.

**No change:** the crawl engine/profile (still same-host), other analyzers.

## Testing / verification

- `HreflangAnalyzerTest`: DOM + Link-header parsing/merge; each per-page check (valid set →
  none; bad codes; duplicate langs; out-of-head; missing self/x-default; non-self-canonical).
- `HreflangCodesTest`: valid (`en`, `en-GB`, `zh-Hans`, `x-default`) vs invalid (`en-UK`, `zz`,
  `de_DE`, empty).
- Aggregate feature test: a same-host `/en/ /de/` cluster exercising return-link, non-200
  (seeded status), noindex, non-canonical, inconsistent-language, unlinked; plus an off-host
  target probed via `Http::fake` — **use resolvable public hosts (example.com), never RFC-2606
  TLDs**, because `UrlSafety::hostIsSafe` does real DNS before HTTP (documented crawler test
  constraint).
- Catalogue invariants: the existing bijection + severity tests must stay green with the 12 new
  active codes and the new category (they enforce catalogue ⇔ `IssueCode`).
- Gates: `ddev php artisan test` green; `ddev npm run build` green; `ddev exec vendor/bin/pint
  --test` clean.

## Risks / decisions

- **Off-host targets are status-only** (locked with user): checks 8–12 are same-host clusters;
  cross-domain reciprocity/consistency is out of scope this phase (would require fetching+parsing
  arbitrary external pages — a separate future phase). This is the honest limit of a same-host
  crawler; the UI/help text should not imply cross-domain reciprocity was checked.
- **Code validation is BCP-47-lenient** (accepts optional script subtag; validates language +
  region against ISO sets) to avoid false positives on valid-but-uncommon locales.
- **`Link:` header parsing** is best-effort per RFC 8288 (angle-bracket URL + `;`-params,
  comma-separated); malformed entries are ignored, not flagged.
- **New category is additive** — the sidebar/checks-table/CATEGORY_ORDER absorb it with no
  redesign; the bijection tests guard the catalogue⇔enum contract.
- **`hreflang_unlinked` vs `orphan_page`**: unlinked is hreflang-specific (target referenced only
  via hreflang, not via `<a>`); it can co-occur with orphan but is reported under hreflang.
