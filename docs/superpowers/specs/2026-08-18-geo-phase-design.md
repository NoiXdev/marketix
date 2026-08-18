# GEO checks — Phase design (from A3B-384)

**Status:** awaiting user review
**Ticket:** A3B-384 „KI-Optimierung Websites (GEO)" — brings the crawler-doable GEO requirements
from the ticket/concept into the v3 crawler as a new check category. Explicitly OUT of scope
(per the concept): Core Web Vitals / PageSpeed (needs an external PSI API) and content
AI-readiness / Q&A quality (needs an LLM); and a GEO score + customer report (separate later
phase).

## Goal

Add a `geo` check category (16th `IssueCategory`) with 5 checks that judge how readable/citable a
site is for AI answer engines — using only the crawler's own capabilities (no external APIs, no
LLM). Mirrors the Phase-6/Hreflang architecture (per-page analyzer + site-level checks in the
aggregate job + catalogue codes + UI category).

Decisions locked with the user: all 5 checks; the "readable without JS" check uses a **real
raw-vs-rendered diff for the start page only** (1× headless render per crawl) plus a cheap
heuristic for every other page; **checks only** this phase (score/report later).

## The 5 checks (category `geo`)

**Site-level** (fetched once per crawl for the start host; findings attached to the start page —
the crawl already identifies `$startPageId`/`$isStart` in `AggregateCrawlJob`):

1. `ai_crawler_blocked` (warning) — the site's `robots.txt` disallows ≥1 major AI crawler from the
   root. Bots checked: GPTBot, OAI-SearchBot, ChatGPT-User, ClaudeBot, anthropic-ai, Claude-Web,
   PerplexityBot, Google-Extended, CCBot, Bytespider, Applebot-Extended, Amazonbot,
   Meta-ExternalAgent. Missing robots.txt ⇒ not flagged (AI allowed by default).
2. `missing_llms_txt` (notice) — no readable `llms.txt` at the origin root (`/llms.txt`).

**Per-page** (`GeoAnalyzer`, HTML pages):

3. `no_semantic_html` (notice) — the page has no primary content landmark: heuristic = neither
   `<main>` nor `<article>` present.
4. `missing_date_signal` (notice) — no freshness/date signal anywhere: none of JSON-LD
   `datePublished`/`dateModified`, `<time datetime>`, `<meta property="article:published_time|
   article:modified_time|og:updated_time">`, `<meta name="date">`.
5. `js_dependent_content` (warning) — the page's content appears to require JS. Per-page
   **heuristic**: the server-rendered `<body>` has little visible text (below a small threshold
   after stripping script/style/markup) AND a SPA root marker is present (an (near-)empty
   `#root` / `#app` / `#__next`, or `[data-reactroot]` / `[ng-app]` / `[data-server-rendered]`).
   For the **start page**, `AggregateCrawlJob` additionally does the authoritative
   **raw-vs-rendered diff** (see below) and sets/clears this flag on the start page from that.

Severities: 2 warning (`ai_crawler_blocked`, `js_dependent_content`), 3 notice
(`missing_llms_txt`, `no_semantic_html`, `missing_date_signal`). All `status = active`.

## Start-page raw-vs-rendered diff (the accurate JS check)

In `AggregateCrawlJob`, once per crawl: fetch the start URL's **raw HTTP HTML** (`Http::get`, like
`SitemapReader`) and its **rendered DOM** (reuse `SafeBrowsershotRenderer`, SSRF-safe), extract the
visible text length of each, and decide JS-dependence: rendered text is substantially richer than
raw (e.g. raw visible text < ~25% of rendered, or raw below the thin threshold while rendered is
well above it). Set `js_dependent_content` on the start page accordingly — this overrides the
per-page heuristic for the start page (authoritative). Wrapped in try/catch: a render failure
leaves the heuristic result in place and never breaks aggregation (the renderer already swallows
failures). Mode-independent (works whether or not the crawl itself used `render_js`).

## New components / data

- **`App\Crawler\RobotsTxtReader`** — `blockedAiBots(string $startUrl): string[]` (fetch
  `origin/robots.txt`; parse User-agent groups + Disallow; return the AI bot names disallowed from
  `/`, most-specific group wins, falling back to the `*` group). Mirrors `SitemapReader`'s
  fetch/try-catch shape.
- **`App\Crawler\LlmsTxtReader`** — `exists(string $startUrl): bool` (GET `origin/llms.txt`, true
  only on a successful, non-empty response).
- **`App\Crawler\Analyzers\GeoAnalyzer`** (`implements Analyzer`) — per-page checks 3–5 (heuristic).
- No new DB column (all findings live in the existing `crawl_pages.issues`). No new migration.

## Files

**Create:** `app/Crawler/RobotsTxtReader.php`, `app/Crawler/LlmsTxtReader.php`,
`app/Crawler/Analyzers/GeoAnalyzer.php`, and their tests.
**Modify:** `app/Crawler/IssueCategory.php` (+`Geo`), `app/Crawler/IssueCode.php` (+5 cases +
severity/category arms), `app/Crawler/CheckCatalog.php` (+5 active entries),
`app/Crawler/PageAnalyzer.php` (register `GeoAnalyzer`), `app/Jobs/AggregateCrawlJob.php`
(site-level readers on start page + start-page render diff), `resources/js/lib/crawlerCategories.ts`
(+`geo`), `lang/en/crawler.php`, `lang/de/crawler.php` (category label + 5×issue + 5×issue_help).
**No change:** the crawl engine/profile, the score/report (separate phase), other analyzers.

## Testing / verification

- `RobotsTxtReaderTest` — a robots.txt blocking GPTBot (`User-agent: GPTBot` / `Disallow: /`)
  returns it; an allow-all returns none; `*`-blocked returns all AI bots; missing file → none.
  Use `Http::fake` with **resolvable public hosts** (example.com), never RFC-2606 TLDs (UrlSafety
  does real DNS before HTTP).
- `LlmsTxtReaderTest` — 200 non-empty → true; 404/empty → false (resolvable hosts).
- `GeoAnalyzerTest` — semantic (has `<main>` → no flag; neither `<main>`/`<article>` → flag);
  date signal present/absent; JS heuristic (thin body + `#root` → flag; rich body → no flag).
  Helper named `runAnalyzer()` (not `run()` — PHPUnit collision).
- Aggregate feature test — a crawl whose start host robots.txt blocks an AI bot → start page gets
  `ai_crawler_blocked`; no llms.txt → `missing_llms_txt`; start-page render diff sets
  `js_dependent_content` when the raw HTML is thin but rendered is rich (fake the raw via
  `Http::fake` + a rendered result; resolvable hosts). Bijection + severity catalogue tests stay
  green with the 5 new active codes + the new category.
- Gates: `ddev php artisan test` green; `ddev npm run build` green; `ddev exec vendor/bin/pint
  --test` clean.

## Risks / decisions

- **robots.txt parsing** is best-effort (group-by User-agent, Disallow `/` detection, most-specific
  wins); not a full spec implementation — enough to catch the common "AI bot blocked" case.
- **Start-page-only render diff** keeps headless cost at 1× per crawl (locked with user); other
  pages rely on the heuristic, which can have false positives/negatives on unusual SPAs — acceptable
  for a signal-level check; the help text frames it as "likely".
- **`missing_date_signal` on every HTML page** may be noisy on pages that legitimately have no date
  (e.g. contact pages); it's a `notice` and filterable in the UI. Could later restrict to
  article-like pages — out of scope now.
- **New category is additive** — sidebar/checks-table/CATEGORY_ORDER absorb it; bijection tests
  guard the catalogue⇔enum contract (same as Hreflang).
- **Deliberately excluded** (concept): CWV/PageSpeed (PSI API), content AI-readiness (LLM), the GEO
  score + customer PDF report — the latter is the natural next phase on top of these checks.
