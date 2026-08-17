# Page-detail checks table — Design

**Status:** awaiting user review
**Scope:** `resources/js/Pages/Crawls/Page.tsx` (+ a tiny `CrawlController::pageDetail` addition,
a shared category-order module, and lang keys). Builds on the crawler-UI redesign.

## Goal

On the page-detail view, checks that have **no meaningful per-page evidence** (a pure
present/absent verdict — e.g. the security-header checks) should no longer each get their own
sidebar tab. Instead, consolidate them onto the **Überblick** tab as **checklist tables grouped
by category**. Security shows a real ✓/✗ audit (✓ = check passed); other categories list their
failing detail-less checks as ✗ rows with the existing help text. Checks that DO have evidence
(broken links, images-without-alt, redirect chain, headings, meta values, inlinks) keep their
own sidebar tab exactly as today.

Decisions locked with the user: **all categories, grouped** (security ✓/✗, others ✗-only); and
the moved checks are **removed from the sidebar** (they live only in the table).

## Key insight (why this is honest and cheap)

Every check emits its own issue code **only when it fails**. So for any check, "passed" is
derivable as *the code is absent from `page.issues`* — no positive data column needed, and this
automatically respects the analyzer's exceptions (e.g. `x-frame-options` counts as satisfied
when CSP `frame-ancestors` is present, because `MissingXFrameOptions` is then never emitted).
The one exception is **HSTS**, which the analyzer only evaluates on HTTPS; on an `http` page it
is shown as **n/a**, not ✓. Security checks only run for HTML pages, so the security checklist
renders only when `content_category === 'html'`.

Consequence: we do **not** expose the raw `security_headers` column to the client, and we do not
add any DB column or positive-signal persistence.

## Definitions

- **Evidence checks (keep a sidebar tab):** the 17 codes the current `issueEvidence()` switch
  maps to a real card — `broken_link`, `missing_alt_text`, `redirect_chain`,
  `missing_structured_data`, `missing_h1`, `multiple_h1`, `heading_order_skip`, `missing_title`,
  `title_too_long`, `missing_meta_description`, `duplicate_title`, `duplicate_meta_description`,
  `noindex`, `canonical_mismatch`, `robots_blocked`, `orphan_page`, `not_in_sitemap`.
  Encoded as a frontend `EVIDENCE_CODES` set (this is already implicit in `issueEvidence`).
- **Detail-less checks:** every other failing code → goes to the checklist table, not a tab.
- **Security header checklist codes** (frontend constant, ordered), each with a neutral label
  key and an `httpsOnly` flag for HSTS:
  `missing_hsts_header` (hsts, httpsOnly), `missing_csp_header` (csp),
  `missing_x_content_type_options` (x_content_type_options),
  `missing_x_frame_options` (x_frame_options), `missing_referrer_policy` (referrer_policy).

## Data contract

**Backend — `CrawlController::pageDetail` adds exactly one field** to the `page` payload:

- `issue_categories: Record<string,string>` — `{ code => CheckCatalog::categoryOf(code) }` for
  each code in `issues` (mirrors how `issue_severities` is built; drop codes whose category is
  null). Authoritative category mapping; avoids duplicating the 111-entry catalog on the client.

Everything else (`issues`, `issue_severities`, `url`, `final_url`, `content_category`, …) stays
as-is. No new DB, no `security_headers` exposure, no controller test-shape break beyond the one
added key.

**Frontend derives the rest** from `issues` + `issue_categories` + the page URL scheme.

## Frontend structure

**New shared module `resources/js/lib/crawlerCategories.ts`:** extract the `CATEGORY_ORDER`
array (the 14 `IssueCategory` slugs, security first) currently inline in `Show.tsx`, so both
`Show.tsx` and `Page.tsx` import one ordered list. `Show.tsx` is refactored to import it — no
behavior change (pure move; reviewer verifies the array is identical).

**`Page.tsx`:**

- `EVIDENCE_CODES: Set<string>` (the 17 above). **Sidebar `items` = `page.issues` filtered to
  `EVIDENCE_CODES`** (worst-severity-first, unchanged otherwise). Detail-less codes no longer
  appear in the sidebar. `issueEvidence()` and the per-issue tab body are unchanged.
- `SECURITY_HEADER_CHECKS` constant (the 5 codes + label keys + httpsOnly).
- Build `checkGroups: { category: string; items: CheckItem[] }[]` where
  `CheckItem = { key: string; label: string; passed: boolean | null; severity?: Severity; help?: string }`:
  - **Security group** (only when `isHtml`): a leading **HTTPS** row `{passed: isHttps}`; then one
    row per security-header code — `passed = !issues.includes(code)`, overridden to `null` (n/a)
    when `httpsOnly && !isHttps`; then any **other failing security detail-less codes** (in the
    `security` category, not in `SECURITY_HEADER_CHECKS`, not an evidence code) as ✗ rows
    (`passed:false`, with severity + help). Row labels: security-header/HTTPS rows use new neutral
    `crawler.check.<labelKey>` keys; other ✗ rows use `crawler.issue.<code>`.
  - **Every other category** in `CATEGORY_ORDER`: collect `issues` whose `issue_categories[code]`
    equals that category, are **not** evidence codes, and (for security's overlap safety) not
    already emitted — each as a ✗ row `{passed:false, severity, help}`, label `crawler.issue.<code>`.
    Emit the group only if it has ≥1 row.
  - `isHttps` derived from `new URL(page.final_url ?? page.url).protocol === 'https:'` (guarded).
- **Render** a "Checks" section on the Überblick grid (a `Card`, `md:col-span-2`, placed after the
  SERP/screenshot cards and before the raw data cards): a heading per non-empty category group
  (`crawler.category_group.<cat>`), then rows. Row = status glyph + label (+ severity `Badge` and a
  muted help subline for ✗ rows). Status: ✓ = `text-success-foreground`; ✗ = `text-danger-foreground`;
  n/a = muted "—". Use `aria-label` per row (`crawler.check_passed` / `check_failed` / `check_na`) so
  the glyph is accessible. If `checkGroups` is empty, omit the section entirely.

Sidebar/`tab` state, all existing cards, `issueEvidence`, and routing are otherwise unchanged.

## Files

**Create**
- `resources/js/lib/crawlerCategories.ts` — exported `CATEGORY_ORDER: string[]`.

**Modify**
- `app/Http/Controllers/CrawlController.php` — add `issue_categories` to the `pageDetail` payload.
- `resources/js/Pages/Crawls/Page.tsx` — checklist derivation + Überblick "Checks" section;
  sidebar filtered to evidence codes; add `issue_categories` to the `CrawlPageDetail` type.
- `resources/js/Pages/Crawls/Show.tsx` — import `CATEGORY_ORDER` from the new module (remove the
  inline copy). No behavior change.
- `lang/en/crawler.php`, `lang/de/crawler.php` — add `checks` (section heading), `check.https`,
  `check.hsts`, `check.csp`, `check.x_content_type_options`, `check.x_frame_options`,
  `check.referrer_policy`, and `check_passed` / `check_failed` / `check_na` (aria).

**No changes:** models, migrations, analyzers, `CheckCatalog`/`IssueCode` (categoryOf already
exists), other components.

## Testing / verification

- Frontend gate: `ddev npm run build` green (tsc strict + vite).
- Backend: extend `CrawlControllerTest`'s page-detail test to assert the new `issue_categories`
  key is present and maps a known failing code to its category. Full suite stays green.
- Reviewer verifies: security ✓/✗ derivation (code-absent = ✓; HSTS n/a on http; only when HTML);
  detail-less codes removed from the sidebar and not duplicated between the security group and the
  generic category groups; evidence-code tabs unchanged; `CATEGORY_ORDER` move is identical; real
  token classes (success/danger foreground) exist; no raw hex.
- Live look is the real acceptance (offer a screenshot of a page detail with security findings).

## Risks / decisions

- **"Passed" = code absent from issues.** Honest for checks the analyzer always evaluates on the
  page type; HSTS's HTTPS-only evaluation is the one special case (→ n/a on http). Documented in code.
- **Mixed label sources** (neutral `crawler.check.*` for the ✓-capable security rows; problem-phrased
  `crawler.issue.*` for ✗-only rows) — acceptable because ✗ rows read naturally as problems; only the
  rows that can show ✓ get neutral labels.
- **Only security shows ✓** (the sole category with a clean positive signal); other categories are
  ✗-only, per the user's chosen option. Extensible later if other positive signals appear.
- **Non-HTML pages** get no security checklist (analyzers didn't run); their detail-less failures
  (e.g. `wrong_content_type`, size issues) still surface as ✗ rows in their category groups.
- **`EVIDENCE_CODES` lives on the frontend** (it mirrors `issueEvidence`'s own switch, the natural
  owner of "does this check have a card"); the authoritative *category* still comes from the backend.
