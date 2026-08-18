# Structured-data validation — Phase design (A3B-384)

**Status:** awaiting user review
**Scope:** upgrade structured-data handling from "list @types" to **multi-format extraction +
validation**, and give the page-detail view a **complete per-page listing of every structured-data
item present (regardless of errors, multiple items/types per page)**.

## Goal

1. Parse structured data in **three formats** — JSON-LD (full), Microdata and RDFa (best-effort,
   common patterns) — into a unified per-item list.
2. **Validate** each item: parse errors (JSON-LD), missing type, and **required-field** checks for
   a curated set of common schema.org types (Google's required properties).
3. On the crawler URL-detail page, show **all** detected items per page — type, format, and
   validity — independent of whether the page has any error, including multiple occurrences.

Decisions locked with the user: depth = **parse + missing-type + required-field** (no
"recommended-field" checks); formats = **all three** (JSON-LD + Microdata + RDFa, the latter two
best-effort).

## Checks — new category `structured_data` (17th `IssueCategory`)

- `missing_structured_data` (notice) — **MOVED** from category `other` to `structured_data`
  (page has zero structured-data items). Same code; only its category mapping changes.
- `structured_data_parse_error` (warning) — a `<script type="application/ld+json">` block contains
  invalid JSON.
- `structured_data_missing_type` (notice) — a detected item has no resolvable type
  (no `@type` / `itemtype` / `typeof`).
- `structured_data_invalid` (warning) — a recognised type is missing ≥1 **required** property.

Severities: 2 warning (`parse_error`, `invalid`), 2 notice (`missing_structured_data`,
`missing_type`). All `status = active`. (No "recommended-field" checks per the chosen depth.)

## Data model

- **New column** `crawl_pages.structured_data_items` (JSON, nullable) — the per-page detail:
  `[{ format: "json-ld"|"microdata"|"rdfa", type: string|null, valid: bool, missing: string[], error?: "parse"|"no_type" }]`.
  One entry per detected schema node (a page with 3 JSON-LD blocks + a microdata Product → 4+
  entries; `@graph` expands to one entry per node). Migration + `'array'` cast.
- Keep the existing `structured_data` column (string[] of unique types) — still populated (from the
  items' types) so nothing that reads it breaks; the missing check is now driven by
  `structured_data_items === []`.

## Components

- **`App\Crawler\SchemaRules`** — `requiredFor(string $type): string[]` returning Google's required
  properties for a curated type set; `[]` (⇒ not validated / treated valid) for unknown types.
  Curated (conservative, to avoid false positives), e.g.:
  Organization→[name]; LocalBusiness→[name,address]; WebSite→[name,url];
  Article/BlogPosting/NewsArticle→[headline]; Product→[name]; Offer→[price,priceCurrency];
  BreadcrumbList→[itemListElement]; ListItem→[position,name]; FAQPage→[mainEntity];
  Question→[name,acceptedAnswer]; Event→[name,startDate]; Recipe→[name];
  Review→[reviewRating]; AggregateRating→[ratingValue]; Person→[name];
  ImageObject→[contentUrl|url]; VideoObject→[name,thumbnailUrl,uploadDate].
  (Exact list finalised in the plan against Google's docs; keep minimal & accurate. A required
  entry expressed as `a|b` means "at least one of".)
- **`StructuredDataAnalyzer`** rewrite (`implements Analyzer`, same slot) → produces `structured_data`
  (types, as today) + `structured_data_items` (detailed), and emits the checks. Extraction:
  - **JSON-LD**: each `<script type="application/ld+json">` → `json_decode`; on failure → a
    `{format:'json-ld', type:null, valid:false, error:'parse'}` item + `parse_error`. Expand
    `@graph`; `@type` may be an array (one item per type) or absent (`no_type`). Property presence
    = the node's object keys.
  - **Microdata**: `[itemscope]` elements; type = last path segment of `itemtype` URL; properties =
    `itemprop` names within that scope (not descending into nested `itemscope`). Nested scopes are
    their own items.
  - **RDFa**: `[typeof]` elements; type = `typeof` (strip a `schema:`/vocab prefix, keep the local
    name); properties = `property` names within that scope. Best-effort (no full CURIE/prefix
    resolution).
  - Per item: resolve type → if none, `missing_type`; else `missing = SchemaRules::requiredFor(type)
    not present in the item's properties` (a `a|b` requirement satisfied if either present);
    `valid = (type recognised ? missing===[] : true)`. Emit `invalid` if any item has missing
    required fields; `missing_structured_data` if the page yields no items.
- **Observer**: persist `structured_data_items` (like the other `data[...]` keys via the existing
  `create(array_merge($data, …))`).

## UI (page detail)

- Rework `structuredDataCard` into a **complete list of every item** (`page.structured_data_items`),
  shown on the Überblick grid whenever the page is HTML — **independent of errors**:
  each row = format badge + type (or "kein Typ"/"ungültiges JSON-LD") + status (✓ gültig / ✗
  fehlende Pflichtfelder: `field, field`). Multiple items rendered (not deduped). Empty →
  "keine strukturierten Daten". The structured-data check codes are detail-less → they also appear
  in the Checks table under a **Structured Data** group (via the new category).
- Add `structured_data_items` to the `CrawlPageDetail` type + the `pageDetail` payload.
- `CATEGORY_ORDER` gains `'structured_data'` (before `'other'`).

## Files

**Create:** `app/Crawler/SchemaRules.php`, the migration, and tests
(`SchemaRulesTest`, `StructuredDataAnalyzerTest`).
**Modify:** `IssueCategory.php` (+`StructuredData`), `IssueCode.php` (+3 cases + severity/category;
move `MissingStructuredData` category→structured_data), `CheckCatalog.php` (3 new entries + move
`missing_structured_data`), `StructuredDataAnalyzer.php` (rewrite), `CrawlPageObserver.php`
(persist column — likely automatic via data merge), `CrawlPage.php` (cast),
`CrawlController.php` (`structured_data_items` in pageDetail), `resources/js/lib/crawlerCategories.ts`
(+`structured_data`), `resources/js/Pages/Crawls/Page.tsx` (rich card + type),
`lang/en/crawler.php`, `lang/de/crawler.php` (category label + issue/issue_help for the 3 new codes;
`missing_structured_data` label already exists).

## Testing

- `SchemaRulesTest`: known types return their required list; `a|b` semantics; unknown type → `[]`.
- `StructuredDataAnalyzerTest` (helper `runAnalyzer()`): a valid Organization JSON-LD → one valid
  item, no issues; invalid JSON block → `parse_error` + a parse item; `@graph` with 2 nodes → 2
  items; a Product missing required `name` → `structured_data_invalid` + item.missing; an item with
  no `@type` → `missing_type`; a Microdata Product (`itemscope itemtype=…/Product` with `itemprop`)
  → an item with format `microdata`; an RDFa `typeof` block → a `rdfa` item; a page with none →
  `missing_structured_data` + empty items.
- Catalogue invariants (bijection + severity match) stay green with the 3 new active codes + the new
  category + the moved code.
- `CrawlControllerTest`: pageDetail exposes `structured_data_items`.
- `CrawlLangCoverageTest` already enforces issue+issue_help for every active problem code → the 3
  new codes need en+de keys.
- Gates: `ddev php artisan test` green; `ddev npm run build` green; `ddev exec vendor/bin/pint
  --test` clean.

## Risks / decisions

- **Microdata/RDFa are best-effort** (common patterns; no full RDFa CURIE/prefix resolution, no
  cross-referencing `itemref`). Documented; JSON-LD is the fully-supported path.
- **Required-field ruleset is curated & conservative** — better to under-flag than raise false
  `invalid` on valid schemas; unknown types are never flagged. Google's *rich-result* validator is
  intentionally NOT replicated.
- **Presence-based validation** — a required property counts as present if its key exists and is
  non-empty; nested-object required fields are validated only at the level they appear as items.
- **Moving `missing_structured_data`** from `other`→`structured_data` changes only its category
  grouping/counts, not the emitted code; the bijection test still passes.
- **New column, no backfill** — old crawl rows lack `structured_data_items` (show empty until
  re-crawled); acceptable on the unmerged v3 branch.
- Score/report unaffected structurally (the new checks flow through the existing
  category/score/report machinery automatically).
