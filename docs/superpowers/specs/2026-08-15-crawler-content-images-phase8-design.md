# Crawler Phase 8 — Content (Tier-A) / Images — Design

**Status:** approved for planning
**Depends on:** Phases 0-7. The final activation phase: Tier-A content + image checks.
Tier-C content checks (AI/NLP) and `incorrectly_sized_images` stay planned by design.

## Goal

Activate the algorithmic content checks (`lorem_ipsum`, cross-page `exact_duplicates`) and
five image checks. After this phase every catalogued check is active except the documented
Tier-C content checks (spelling, grammar, near/semantic-duplicate, low-relevance, soft-404,
readability) and `incorrectly_sized_images` (needs rendered dimensions). Also folds in the
Phase-7 `::1` `isLocalhost` fix.

## Scope

**In (7 checks flip planned→active):**

*Content — Tier-A (2):* `lorem_ipsum` (warning), `exact_duplicates` (warning, cross-page).

*Images (5):* `image_over_100kb` (notice), `image_missing_size_attributes` (notice),
`image_missing_alt_attribute` (notice), `image_alt_over_100_chars` (notice),
`background_images` (notice).

**Deferred (stay planned/greyed):**
- Content Tier-C (8): `readability_hard`, `readability_very_hard` (language-dependent
  Flesch — user decision), `near_duplicates`, `semantically_similar`, `low_relevance`,
  `soft_404`, `spelling_errors`, `grammar_errors` (AI/NLP).
- `incorrectly_sized_images` (needs rendered vs natural image dimensions).

**Also:** fix `LinkChecks::isLocalhost` so IPv6 `[::1]` matches (Phase-7 parked minor).

**Out:** no new severity; categories/severities/labels already exist (Phase 0) — no
frontend or lang changes.

## Global constraints (carried)

- Bijection stays exact: active catalogue codes === `IssueCode` cases. This phase adds 7
  `IssueCode` cases and flips 7 catalogue entries planned→active, so both grow
  **104 → 111**.
- `IssueCode::category()` and `::severity()` stay exhaustive `match` (no default arm); the
  7 new cases get arms in both — 2 → `Content`, 5 → `Images`. Severities per the scope
  list (match the catalogue; the generalized severity-match test enforces this).
- The 7 lang labels already exist (Phase 0) — no rename, no lang change.
- Run via DDEV. Frontend gate `ddev npm run build` (no change expected). Pint clean.

## Content checks

`MetaAnalyzer` already strips the body to compute `word_count`/`thin_content`. Extend it
(reusing the same stripped body text `$text = strip_tags($this->bodyHtml($dom))`):

- **`lorem_ipsum`**: `stripos($text, 'lorem ipsum') !== false` → issue.
- **`exact_duplicates`** (storage side): compute a content fingerprint and store it as
  `$data['content_hash']` = `md5(preg_replace('/\s+/', ' ', mb_strtolower(trim($text))))`,
  but ONLY when `$words >= 100` (non-thin pages); otherwise `null`. This avoids flagging
  boilerplate-only / thin pages as duplicates.

Cross-page detection in `AggregateCrawlJob`: add `content_hash` to the `identities`
`->select([...])`, compute `$dupHashes = $this->duplicates($identities, 'content_hash')`,
thread into the chunk closure, and flag `exact_duplicates` when
`$page->content_hash !== null && ($dupHashes[$page->content_hash] ?? 0) > 1` (mirrors
`duplicate_title`). The shared `duplicates()` helper already skips null/empty.

## Image checks

**`ImageAnalyzer` (extend)** — one `img` pass collecting flags (keep the existing
`images_missing_alt` data + `missing_alt_text` issue):
- `image_missing_alt_attribute`: an `<img>` with NO `alt` attribute at all
  (`$node->attr('alt') === null`) — distinct from `missing_alt_text` (empty OR missing).
- `image_missing_size_attributes`: an `<img>` missing `width` OR `height`.
- `image_alt_over_100_chars`: an `<img alt>` with `mb_strlen > 100`.
- `background_images`: a separate scan — any element whose inline `style` attribute
  contains `background-image` (`stripos`). Heuristic: inline styles only (external CSS is
  not parsed); documented.

**`image_over_100kb`** (observer, not analyzer): image resources are crawled as their own
pages. In `CrawlPageObserver::recordResponse()`'s non-HTML branch, when
`$category === ResourceClassifier::IMAGE && $size > 102400` (100 KB), append
`IssueCode::ImageOver100kb`. (This is separate from the existing `large_resource`
size-tier check; both may fire.)

## Storage

- **Migration** `add_content_hash_to_crawl_pages`: nullable `string content_hash` (md5 is
  32 hex chars). Persists via the observer's `array_merge($data, …)` once `MetaAnalyzer`
  adds it. No model change (`$guarded = ['id']`).

## `::1` fix

In `app/Crawler/LinkChecks.php::isLocalhost`, strip brackets from the parsed host before
comparison: `$host = trim(strtolower(parse_url($url, PHP_URL_HOST) ?? ''), '[]');` so
`http://[::1]/` matches. Add a covering assertion to `LinkChecksTest`.

## Files

**Create**
- `database/migrations/2026_08_15_000005_add_content_hash_to_crawl_pages.php`

**Modify**
- `app/Crawler/IssueCode.php` — 7 cases + arms.
- `app/Crawler/CheckCatalog.php` — flip the 7 entries planned→active.
- `app/Crawler/LinkChecks.php` — `::1` bracket fix.
- `app/Crawler/Analyzers/MetaAnalyzer.php` — `lorem_ipsum` + `content_hash`.
- `app/Crawler/Analyzers/ImageAnalyzer.php` — the 4 image DOM checks.
- `app/Observers/CrawlPageObserver.php` — `image_over_100kb`.
- `app/Jobs/AggregateCrawlJob.php` — cross-page `exact_duplicates`.
- Tests: `CheckCatalogTest`, `LinkChecksTest`, `MetaAnalyzerTest`, `ImageAnalyzerTest`,
  `CrawlPageObserverSecurityTest` (or a new observer test), `AggregateCrawlJobTest`,
  `CrawlControllerTest`.

**No changes:** `PageContext`, other analyzers, frontend, lang.

## Testing

- **`CheckCatalogTest`**: content active 3 (1 prior + 2), images active 6 (1 + 5);
  bijection 111; generalized severity-match still passes.
- **`LinkChecksTest`**: `isLocalhost('http://[::1]/')` is true.
- **`MetaAnalyzerTest`**: a body containing "Lorem ipsum dolor…" → `lorem_ipsum`; a
  ≥100-word body → non-null `content_hash`; a thin (<100-word) body → null `content_hash`;
  identical normalized bodies → identical hash (differing whitespace/case ignored).
- **`ImageAnalyzerTest`**: an `<img>` with no `alt` attr → `image_missing_alt_attribute`
  (and still `missing_alt_text`); an `<img>` missing width/height →
  `image_missing_size_attributes`; a >100-char alt → `image_alt_over_100_chars`; an inline
  `style="background-image:url(x)"` → `background_images`; a clean fully-specified `<img>`
  → none of these.
- **Observer test**: an image resource (`image/png`, > 100 KB body) →
  `image_over_100kb`; a small image → not.
- **Aggregate test**: two non-thin pages with identical `content_hash` → both
  `exact_duplicates`; a unique page does not.
- **Controller feature test**: `group=content` and `group=images` each list a page carrying
  a new code.
- Gates: crawler namespace green, full suite green, Pint clean, `ddev npm run build` green.

## Risks / decisions

- **Readability deferred** (language-dependent Flesch + syllable heuristic) — user decision.
- **`exact_duplicates` guard** (`word_count >= 100`) avoids flagging thin/boilerplate pages
  as duplicates; the normalizer (lowercase + whitespace-collapse) makes trivially-different
  markup hash-equal.
- **`background_images` is inline-only** — external stylesheet background images are not
  detected (no CSS parsing); documented as a known limitation.
- **`image_over_100kb`** keys on the crawled image resource's `size_bytes`, independent of
  the existing `large_resource`/`oversized_resource` tiers.
- After Phase 8, the only inactive catalogue checks are the documented Tier-C content
  checks + `incorrectly_sized_images` — all intentionally deferred.
