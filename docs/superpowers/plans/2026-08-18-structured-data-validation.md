# Structured-data validation — Implementation Plan (A3B-384)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Multi-format structured-data extraction (JSON-LD full; Microdata/RDFa best-effort) + validation (parse / missing-type / required-field), and a per-page listing of every detected item on the URL-detail page regardless of errors.

**Architecture:** New `structured_data` category + 3 checks (and `missing_structured_data` moves into it). A rewritten `StructuredDataAnalyzer` emits a detailed per-item list into a new `crawl_pages.structured_data_items` column, validated against a curated `SchemaRules` required-field map. The detail page renders all items.

**Tech Stack:** Laravel 13 / PHP 8.3, Symfony DomCrawler, React/TS, PHPUnit, Pint.

**Spec:** docs/superpowers/specs/2026-08-18-structured-data-validation-design.md

## Global Constraints

- 3 new codes + move `missing_structured_data`; all `status = active`. Keep the catalogue⇔`IssueCode` **bijection** + **severity-match** + **category-match** invariants green (change severity()/category()/CheckCatalog consistently).
- Severities: **warning** = `structured_data_parse_error`, `structured_data_invalid`; **notice** = `structured_data_missing_type`, `missing_structured_data`.
- Microdata/RDFa parsing is **best-effort** (common patterns); JSON-LD is fully supported. Required-field ruleset is **conservative** (unknown types never flagged; prefer under-flagging).
- `CrawlLangCoverageTest` enforces `issue.<code>` + `issue_help.<code>` (en+de) for every active problem code → the 3 new codes need keys.
- Analyzer test helper `runAnalyzer()` (not `run()` — PHPUnit collision).
- Gates: `ddev php artisan test` green; `ddev npm run build` green; `ddev exec vendor/bin/pint --test` clean.

---

## Task 1: catalogue + column

**Files:** Modify `app/Crawler/IssueCategory.php`, `app/Crawler/IssueCode.php`, `app/Crawler/CheckCatalog.php`, `app/Models/CrawlPage.php`; Create migration `database/migrations/2026_08_18_000001_add_structured_data_items_to_crawl_pages.php`.

- [ ] **Step 1: `IssueCategory`.** Add `case StructuredData = 'structured_data';` (after `Links`, before `Other` — or wherever keeps a sensible order; position isn't asserted).

- [ ] **Step 2: `IssueCode` cases.** Add:
```php
case StructuredDataParseError = 'structured_data_parse_error';
case StructuredDataMissingType = 'structured_data_missing_type';
case StructuredDataInvalid = 'structured_data_invalid';
```

- [ ] **Step 3: `severity()`.** Add `self::StructuredDataParseError, self::StructuredDataInvalid` to the `'warning'` arm; add `self::StructuredDataMissingType` to the `'notice'` arm. (`MissingStructuredData` stays `notice` — unchanged.)

- [ ] **Step 4: `category()`.** Add the 3 new cases AND `self::MissingStructuredData` to the arm returning `IssueCategory::StructuredData`. **Remove `MissingStructuredData` from whatever arm currently returns `IssueCategory::Other`.**

- [ ] **Step 5: `CheckCatalog::all()`.** Change the existing `missing_structured_data` entry's category `'other'` → `'structured_data'`. Add 3 entries (place them together as a `// structured_data` block):
```php
['code' => 'missing_structured_data', 'category' => 'structured_data', 'severity' => 'notice', 'status' => self::A],
['code' => 'structured_data_parse_error', 'category' => 'structured_data', 'severity' => 'warning', 'status' => self::A],
['code' => 'structured_data_missing_type', 'category' => 'structured_data', 'severity' => 'notice', 'status' => self::A],
['code' => 'structured_data_invalid', 'category' => 'structured_data', 'severity' => 'warning', 'status' => self::A],
```
(Move the existing `missing_structured_data` line out of the `other` block so it's not duplicated.)

- [ ] **Step 6: Migration + cast.** Create the migration adding a nullable JSON `structured_data_items` column to `crawl_pages` (mirror `2026_08_15_000004_add_pagination_urls_to_crawl_pages.php`) with a real `down()`. Add `'structured_data_items' => 'array',` to `CrawlPage::casts()`.

- [ ] **Step 7: tests + Pint + commit.**
Run: `ddev php artisan test --filter="CheckCatalog|IssueCode"` → PASS (bijection + severity + category invariants).
Run: `ddev exec vendor/bin/pint app/Crawler app/Models database`.
```bash
git add app/Crawler/IssueCode.php app/Crawler/IssueCategory.php app/Crawler/CheckCatalog.php app/Models/CrawlPage.php database/migrations/2026_08_18_000001_add_structured_data_items_to_crawl_pages.php
git commit -m "feat(crawler): structured_data category + validation codes + items column"
```
(End body with `Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>`.)

---

## Task 2: `SchemaRules` required-field map

**Files:** Create `app/Crawler/SchemaRules.php`, `tests/Unit/Crawler/SchemaRulesTest.php`.

**Interfaces:** `SchemaRules::requiredFor(string $type): string[]` — required property tokens for a type; `[]` for unknown types. A token containing `|` means "at least one of these" (the analyzer treats it specially).

- [ ] **Step 1: Test first** (`tests/Unit/Crawler/SchemaRulesTest.php`): known types return their list (e.g. `Product` → `['name']`, `Event` → `['name','startDate']`, `BreadcrumbList` → `['itemListElement']`, `ImageObject` → `['contentUrl|url']`); unknown type (`'Frobnicate'`) → `[]`; type match is case-sensitive exact (schema.org types are PascalCase).

- [ ] **Step 2: RED.** `ddev php artisan test --filter=SchemaRulesTest` → fails.

- [ ] **Step 3: Implement** `SchemaRules` with a private `const RULES` map (conservative — Google-documented required props; keep minimal to avoid false positives):
```php
private const RULES = [
    'Organization' => ['name'],
    'LocalBusiness' => ['name', 'address'],
    'WebSite' => ['name', 'url'],
    'WebPage' => [],
    'Article' => ['headline'],
    'BlogPosting' => ['headline'],
    'NewsArticle' => ['headline'],
    'Product' => ['name'],
    'Offer' => ['price', 'priceCurrency'],
    'AggregateRating' => ['ratingValue'],
    'Review' => ['reviewRating'],
    'BreadcrumbList' => ['itemListElement'],
    'ListItem' => ['position', 'name'],
    'FAQPage' => ['mainEntity'],
    'Question' => ['name', 'acceptedAnswer'],
    'Answer' => ['text'],
    'Event' => ['name', 'startDate'],
    'Recipe' => ['name'],
    'Person' => ['name'],
    'ImageObject' => ['contentUrl|url'],
    'VideoObject' => ['name', 'thumbnailUrl', 'uploadDate'],
];

public static function requiredFor(string $type): array
{
    return self::RULES[$type] ?? [];
}
```
(Finalise the list against Google's docs if any look wrong; keep conservative.)

- [ ] **Step 4: GREEN + Pint + commit.**
Run: `ddev php artisan test --filter=SchemaRulesTest` → PASS. `ddev exec vendor/bin/pint app/Crawler tests`.
```bash
git add app/Crawler/SchemaRules.php tests/Unit/Crawler/SchemaRulesTest.php
git commit -m "feat(crawler): SchemaRules curated required-field map"
```

---

## Task 3: `StructuredDataAnalyzer` rewrite (multi-format + validation)

**Files:** Modify `app/Crawler/Analyzers/StructuredDataAnalyzer.php`, `app/Observers/CrawlPageObserver.php` (verify only); Test: `tests/Unit/Crawler/StructuredDataAnalyzerTest.php` (rewrite/extend).

**Interfaces:** Consumes `SchemaRules` (T2) + the 3 codes (T1). Produces `data['structured_data']` (types) + `data['structured_data_items']` (detail).

- [ ] **Step 1: Write the test first** covering (helper `runAnalyzer()`): valid Organization JSON-LD (`{"@context":"https://schema.org","@type":"Organization","name":"X"}`) → 1 item `{format:'json-ld',type:'Organization',valid:true,missing:[]}`, no issues, `structured_data`=`['Organization']`. Invalid JSON block → a `{format:'json-ld',type:null,valid:false,error:'parse'}` item + `structured_data_parse_error`. `@graph` with 2 typed nodes → 2 items. A Product JSON-LD missing `name` → `structured_data_invalid` + item `missing:['name']`. A JSON-LD node with no `@type` → `structured_data_missing_type` + item `error:'no_type'`. A Microdata Product (`<div itemscope itemtype="https://schema.org/Product"><span itemprop="name">…</span></div>`) → item `{format:'microdata',type:'Product',valid:true}`. An RDFa block (`<div typeof="Product"><span property="name">…</span></div>` or with `schema:` prefix) → item `{format:'rdfa',type:'Product',…}`. A page with none → `missing_structured_data` + `structured_data_items`=[]. Assert `valid` for a known type with all required present, and the `a|b` case (ImageObject with only `url` → valid).

- [ ] **Step 2: RED.** `ddev php artisan test --filter=StructuredDataAnalyzerTest` → fails.

- [ ] **Step 3: Implement.** Rewrite `analyze()` to build a unified `$items` array `[{format,type,valid,missing,error?}]` and emit issues. Structure:
  - **JSON-LD**: for each `<script type="application/ld+json">`: `json_decode($node->text(''), true)`; if `null`/not array → add `{format:'json-ld',type:null,valid:false,error:'parse'}`, set `$parseError=true`, continue. Else flatten to nodes: if top-level is a list, iterate; expand `@graph`. For each node with an `@type`, one item per type value; property names = `array_keys($node)`. A node with no `@type` → `{type:null,error:'no_type'}`.
  - **Microdata**: `$dom->filter('[itemscope]')->each(...)`: type = last URL segment of `itemtype` (or null); property names = the `itemprop` tokens of descendants that are NOT inside a nested `[itemscope]` (approximate: collect `[itemprop]` under the node, minus those under a descendant `[itemscope]`). Build an item.
  - **RDFa**: `$dom->filter('[typeof]')->each(...)`: type = the `typeof` value with a leading `schema:`/`Schema:` prefix stripped (keep local name; take the first token if multiple); property names = `property` tokens of descendants (best-effort, similar scoping).
  - **Validate each item** (skip parse/no_type items): `$req = SchemaRules::requiredFor($type)`; a token `"a|b"` is satisfied if any of its `|`-parts is a present property; `missing` = required tokens not satisfied; `valid = ($req === [] ? true : $missing === [])`. A present property = key/itemprop/property present AND (for JSON-LD) non-empty.
  - **Emit**: `structured_data_parse_error` if any parse item; `structured_data_missing_type` if any `no_type` item; `structured_data_invalid` if any item with `missing !== []`; `missing_structured_data` if `$items === []`.
  - `data['structured_data']` = unique non-null types (backward-compatible); `data['structured_data_items']` = `$items` (or `null` if empty — but keep `[]`→ store `null` so the column is null when none; the missing check uses `$items===[]`). Keep a small private helper for property-presence and type-resolution.
  Keep JSON-LD `@graph`/array handling from the current `extractTypes()` but extended to capture properties + per-node items.

- [ ] **Step 4: Observer.** Confirm `data['structured_data_items']` reaches the column via the existing `create(array_merge($data, [...]))` (same mechanism as `structured_data`); no code change expected — verify by reading `recordResponse`.

- [ ] **Step 5: GREEN + regression + Pint + commit.**
Run: `ddev php artisan test --filter="StructuredDataAnalyzer|Crawler"` → PASS.
Run: `ddev exec vendor/bin/pint app/Crawler app/Observers tests`.
```bash
git add app/Crawler/Analyzers/StructuredDataAnalyzer.php tests/Unit/Crawler/StructuredDataAnalyzerTest.php
git commit -m "feat(crawler): multi-format structured-data extraction + validation"
```

---

## Task 4: UI (per-page items) + payload + category + lang

**Files:** Modify `app/Http/Controllers/CrawlController.php` (pageDetail), `resources/js/Pages/Crawls/Page.tsx`, `resources/js/lib/crawlerCategories.ts`, `lang/en/crawler.php`, `lang/de/crawler.php`.

- [ ] **Step 1: pageDetail payload.** Add `'structured_data_items' => $pageModel->structured_data_items ?? [],` to the `page` array (near `structured_data`).

- [ ] **Step 2: `CATEGORY_ORDER`.** Insert `'structured_data'` before `'other'` in `resources/js/lib/crawlerCategories.ts`.

- [ ] **Step 3: `Page.tsx`.**
  - Add to `CrawlPageDetail`: `structured_data_items: { format: string; type: string | null; valid: boolean; missing: string[]; error?: string }[];`.
  - **Rework `structuredDataCard`** into a complete list of `page.structured_data_items`, shown on the Überblick grid whenever `isHtml` (independent of errors). Each row: a format badge (`json-ld`/`microdata`/`rdfa`), the type (or `t('crawler.sd_no_type')` when `error==='no_type'`/type null, or `t('crawler.sd_parse_error')` when `error==='parse'`), and a status — ✓ (`text-success-foreground`) when `valid`, else ✗ (`text-danger-foreground`) with `t('crawler.sd_missing_fields')`: `{missing.join(', ')}`. Empty list → `t('crawler.structured_data_none')`. Reuse Card/TableCard styling; multiple items rendered (not deduped).
  - **Remove `'missing_structured_data'` from `EVIDENCE_CODES`** and remove its `case 'missing_structured_data': return structuredDataCard;` from `issueEvidence()` — the structured-data checks are detail-less now (they show in the Checks table under "Structured Data"), and the rich card is a standalone overview card. Keep `{isHtml && structuredDataCard}` in the overview grid (now always shown, with its own empty state).

- [ ] **Step 4: Lang keys** (BOTH en + de; grep placement/collisions):
  - `category_group.structured_data` → EN "Structured data" / DE "Strukturierte Daten".
  - `issue.*` + `issue_help.*` for `structured_data_parse_error`, `structured_data_missing_type`, `structured_data_invalid` (EN + DE; concise, GEO/SEO rationale). `missing_structured_data` label already exists — keep.
  - Card strings: `structured_data_none` ("No structured data" / "Keine strukturierten Daten"), `sd_no_type` ("No @type" / "Kein @type"), `sd_parse_error` ("Invalid JSON-LD" / "Ungültiges JSON-LD"), `sd_missing_fields` ("Missing required fields" / "Fehlende Pflichtfelder").

- [ ] **Step 5: Build + regression + Pint + commit.**
Run: `ddev npm run build` → green.
Run: `ddev php artisan test --filter="CrawlController|CrawlLangCoverage|StructuredData"` → PASS.
Run: `ddev exec vendor/bin/pint --test lang app/Http/Controllers/CrawlController.php`.
```bash
git add app/Http/Controllers/CrawlController.php resources/js/Pages/Crawls/Page.tsx resources/js/lib/crawlerCategories.ts lang/en/crawler.php lang/de/crawler.php
git commit -m "feat(crawler-ui): structured-data category + full per-page items list"
```

---

## Final verification (whole plan)

- [ ] `ddev php artisan test` — full suite green (invariants + SchemaRules + analyzer + coverage).
- [ ] `ddev npm run build` — green.
- [ ] `ddev exec vendor/bin/pint --test` — clean.
- [ ] Manual/live: crawl a site with schema → the page detail lists every structured-data item (type/format/validity) regardless of errors; invalid/parse/missing-type issues show under a "Structured data" category (sidebar + checks table); a page with none shows the empty state + `missing_structured_data`.

## Self-Review notes (author)

- **Spec coverage:** category+codes+column (T1); SchemaRules (T2); multi-format analyzer+validation (T3); UI items list + payload + category + lang (T4). ✓
- **Invariants:** severities listed once in Global Constraints; category move handled in both `IssueCode::category()` and `CheckCatalog`; bijection/severity/category tests are the gate. ✓
- **Lang coverage:** 3 new codes get issue+issue_help (CrawlLangCoverageTest enforces). ✓
- **Per-page listing independent of errors** = the rich `structuredDataCard` over `structured_data_items`, always on overview; `missing_structured_data` removed from EVIDENCE_CODES so it lands in the checks table. ✓
- **Best-effort microdata/RDFa** + conservative ruleset documented; unknown types never flagged. ✓
