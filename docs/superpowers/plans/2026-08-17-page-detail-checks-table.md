# Page-detail checks table — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Consolidate detail-less checks (e.g. security headers) on the page-detail Überblick tab as category-grouped checklist tables (security = real ✓/✗, others = ✗ rows), and remove those checks from the sidebar. Checks with real evidence keep their tab.

**Architecture:** One new backend field (`issue_categories` on the page-detail payload) supplies the authoritative category per failing code. A shared `CATEGORY_ORDER` module is extracted. `Page.tsx` derives the checklist entirely client-side: "passed" = the check's issue code is absent from `page.issues` (HSTS is n/a on http; security checklist only for HTML). No DB/model/analyzer change, no raw-header exposure.

**Tech Stack:** Laravel 13 / PHP 8.3, Inertia + React 19 + TS, Tailwind + design tokens, `@/Components/ui`.

**Spec:** docs/superpowers/specs/2026-08-17-page-detail-checks-table-design.md

## Global Constraints

- No DB/model/migration/analyzer/`CheckCatalog`/`IssueCode` changes. The only backend change is adding one key to `CrawlController::pageDetail`'s payload.
- "Passed" is derived as *code absent from `issues`* — do not invent positive-signal persistence, do not send `security_headers` to the client.
- Use real project token classes (Tailwind silently no-ops unknown classes): grep `resources/css/app.css` `@theme` + the UI kit; confirm `text-success-foreground` / `text-danger-foreground` / `text-muted` / `divide-line` exist before using them.
- TypeScript strict. Reuse the UI kit (`Card`, `Badge`) + `@/lib/severity`.
- Lang keys added to BOTH `lang/en/crawler.php` and `lang/de/crawler.php`, matching the existing nested-array structure; Pint-clean.
- Gate: `ddev npm run build` green; `ddev php artisan test` green (regression); lint is broken — build is the frontend gate.

---

## Task 1: backend — `issue_categories` on the page-detail payload

**Files:** Modify `app/Http/Controllers/CrawlController.php`; Test: `tests/Feature/CrawlControllerTest.php` (extend the existing page-detail test).

**Interfaces:**
- Produces: `page.issue_categories: Record<string,string>` — `{ code => category-slug }` for each failing code (nulls dropped). Consumed by Task 3.

- [ ] **Step 1: Add the field.** In `pageDetail()`'s `inertia('Crawls/Page', ['page' => [ ... ]])` array, right after the `issue_severities` entry, add:

```php
'issue_categories' => collect($pageModel->issues ?? [])
    ->mapWithKeys(fn ($code) => [$code => CheckCatalog::categoryOf($code)])
    ->filter()
    ->all(),
```

Ensure `use App\Crawler\CheckCatalog;` is present at the top of the file (add it if missing; `IssueCode` is already imported).

- [ ] **Step 2: Extend the page-detail feature test.** Locate the existing test in `tests/Feature/CrawlControllerTest.php` that hits the page-detail route (search for `crawls.pages.show` / `pageDetail` / a test asserting `page.issues`). It already seeds a `CrawlPage` with an `issues` array. Add assertions on the same Inertia response that:
  1. the `page.issue_categories` prop exists, and
  2. for a known failing code already in that page's `issues` (pick one the test seeds, e.g. a security code like `missing_csp_header` if seeded, else whatever code the test uses), `page.issue_categories[<code>]` equals `CheckCatalog::categoryOf(<code>)` (a non-null category slug).

Use the project's existing Inertia assertion style in that test file (e.g. `->assertInertia(fn (Assert $p) => $p->has('page.issue_categories')->where('page.issue_categories.<code>', '<category>'))`). If the seeded page has no issue with a non-null category, add one issue code with a known category to the seed so the mapping is exercised.

- [ ] **Step 3: Run the test.**

Run: `ddev php artisan test --filter=CrawlControllerTest`
Expected: PASS (all, including the new assertions).

- [ ] **Step 4: Pint + commit.**

Run: `ddev exec vendor/bin/pint app/Http/Controllers/CrawlController.php tests/Feature/CrawlControllerTest.php`
```bash
git add app/Http/Controllers/CrawlController.php tests/Feature/CrawlControllerTest.php
git commit -m "feat(crawler): expose issue_categories on the page-detail payload"
```
(End the commit body with `Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>`.)

---

## Task 2: shared `CATEGORY_ORDER` module

**Files:** Create `resources/js/lib/crawlerCategories.ts`; Modify `resources/js/Pages/Crawls/Show.tsx`.

**Interfaces:**
- Produces: `export const CATEGORY_ORDER` — the ordered category-slug list. Consumed by Show (Task 2) + Page (Task 3).

- [ ] **Step 1: Read Show.tsx's current `CATEGORY_ORDER`.** Open `resources/js/Pages/Crawls/Show.tsx`, find the inline `CATEGORY_ORDER` array, and copy it **verbatim** (same values, same order — security first).

- [ ] **Step 2: Create the module.** `resources/js/lib/crawlerCategories.ts`:

```ts
/** Category slugs (App\Crawler\IssueCategory) in display order, security first. */
export const CATEGORY_ORDER = [
  'security',
  'response_codes',
  'url',
  'page_title',
  'meta_description',
  'meta_keywords',
  'h1',
  'h2',
  'content',
  'images',
  'canonicals',
  'pagination',
  'links',
  'other',
] as const;
```

If Show.tsx's existing array differs in values or order from the above, use **Show.tsx's exact array** (it is the incumbent source of truth) and adjust this block to match it — the move must be behavior-preserving.

- [ ] **Step 3: Refactor Show.tsx to import it.** Remove the inline `CATEGORY_ORDER` declaration from `Show.tsx` and add `import { CATEGORY_ORDER } from '@/lib/crawlerCategories';`. No other change. `CATEGORY_ORDER` is used the same way (`.map`, `.includes`) — a readonly tuple supports both.

- [ ] **Step 4: Build + commit.**

Run: `ddev npm run build` → green.
```bash
git add resources/js/lib/crawlerCategories.ts resources/js/Pages/Crawls/Show.tsx
git commit -m "refactor(crawler-ui): extract shared CATEGORY_ORDER module"
```
(End body with the Co-Authored-By line.)

---

## Task 3: `Page.tsx` — checklist tables + sidebar filter + lang keys

**Files:** Modify `resources/js/Pages/Crawls/Page.tsx`, `lang/en/crawler.php`, `lang/de/crawler.php`.

**Interfaces:**
- Consumes: `page.issue_categories` (Task 1), `CATEGORY_ORDER` (Task 2), `@/lib/severity`.

- [ ] **Step 1: Type + imports.** In `Page.tsx`: add `issue_categories: Record<string, string>;` to the `CrawlPageDetail` interface (near `issue_severities`). Add `useMemo` to the `react` import; add `Severity` to the `@/lib/severity` import (keep `severityBadgeVariant`/`severityDotClass`/`severityRank`); add `import { CATEGORY_ORDER } from '@/lib/crawlerCategories';`.

- [ ] **Step 2: Module-level constants** (top of file, outside the component):

```tsx
/** Failing codes that have a dedicated evidence card → keep their own sidebar tab. */
const EVIDENCE_CODES = new Set<string>([
  'broken_link', 'missing_alt_text', 'redirect_chain', 'missing_structured_data',
  'missing_h1', 'multiple_h1', 'heading_order_skip',
  'missing_title', 'title_too_long', 'missing_meta_description', 'duplicate_title', 'duplicate_meta_description',
  'noindex', 'canonical_mismatch', 'robots_blocked',
  'orphan_page', 'not_in_sitemap',
]);

const SECURITY_HEADER_CHECKS: { code: string; labelKey: string; httpsOnly?: boolean }[] = [
  { code: 'missing_hsts_header', labelKey: 'hsts', httpsOnly: true },
  { code: 'missing_csp_header', labelKey: 'csp' },
  { code: 'missing_x_content_type_options', labelKey: 'x_content_type_options' },
  { code: 'missing_x_frame_options', labelKey: 'x_frame_options' },
  { code: 'missing_referrer_policy', labelKey: 'referrer_policy' },
];

type CheckItem = { key: string; label: string; passed: boolean | null; severity?: 'error' | 'warning' | 'notice' | 'info'; help?: string };
type CheckGroup = { category: string; items: CheckItem[] };
```

- [ ] **Step 3: Derive `checkGroups`** inside the component (after `isHtml` is computed):

```tsx
const isHttps = (() => {
  try { return new URL(page.final_url ?? page.url).protocol === 'https:'; } catch { return true; }
})();

const checkGroups: CheckGroup[] = useMemo(() => {
  const issues = page.issues;
  const cat = page.issue_categories ?? {};
  const shown = new Set<string>();
  const groups: CheckGroup[] = [];

  const failRow = (code: string): CheckItem => {
    shown.add(code);
    return {
      key: code,
      label: t(`crawler.issue.${code}`),
      passed: false,
      severity: page.issue_severities[code] ?? 'notice',
      help: t(`crawler.issue_help.${code}`),
    };
  };

  // Security group — HTML pages only: real ✓/✗ audit.
  if (isHtml) {
    const items: CheckItem[] = [];
    items.push({ key: 'https', label: t('crawler.check.https'), passed: isHttps });
    for (const c of SECURITY_HEADER_CHECKS) {
      const passed = c.httpsOnly && !isHttps ? null : !issues.includes(c.code);
      items.push({ key: c.code, label: t(`crawler.check.${c.labelKey}`), passed });
      shown.add(c.code);
    }
    for (const code of issues) {
      if (shown.has(code) || EVIDENCE_CODES.has(code)) continue;
      if (cat[code] !== 'security') continue;
      items.push(failRow(code));
    }
    groups.push({ category: 'security', items });
  }

  // Every other category: failing detail-less checks as ✗ rows.
  for (const category of CATEGORY_ORDER) {
    if (category === 'security') continue;
    const items: CheckItem[] = [];
    for (const code of issues) {
      if (shown.has(code) || EVIDENCE_CODES.has(code)) continue;
      if ((cat[code] ?? 'other') !== category) continue;
      items.push(failRow(code));
    }
    if (items.length) groups.push({ category, items });
  }

  return groups;
}, [page, isHtml, isHttps, t]);
```

- [ ] **Step 4: The Checks card.** Add a card builder (near the other `const xxxCard = (...)` builders). Confirm `text-success-foreground` exists in `app.css @theme`; if not, use the token the `Badge` `success` variant uses (grep `resources/js/Components/ui/Badge.tsx`).

```tsx
const checksCard = checkGroups.length > 0 && (
  <Card className="p-4 md:col-span-2">
    <h3 className="mb-3 font-medium text-foreground">{t('crawler.checks')}</h3>
    <div className="space-y-4">
      {checkGroups.map((g) => (
        <div key={g.category}>
          <h4 className="mb-1 text-xs font-semibold uppercase tracking-wide text-muted">{t(`crawler.category_group.${g.category}`)}</h4>
          <ul className="divide-y divide-line">
            {g.items.map((it) => (
              <li key={it.key} className="flex items-start gap-2 py-1.5 text-sm">
                <span
                  className="mt-0.5 shrink-0 font-semibold"
                  aria-label={t(it.passed === null ? 'crawler.check_na' : it.passed ? 'crawler.check_passed' : 'crawler.check_failed')}
                >
                  {it.passed === null ? (
                    <span className="text-muted">—</span>
                  ) : it.passed ? (
                    <span className="text-success-foreground">✓</span>
                  ) : (
                    <span className="text-danger-foreground">✗</span>
                  )}
                </span>
                <span className="min-w-0 flex-1">
                  <span className="flex flex-wrap items-center gap-2">
                    <span className="text-foreground">{it.label}</span>
                    {it.passed === false && it.severity && (
                      <Badge variant={severityBadgeVariant(it.severity)}>{t(`crawler.severity_${it.severity}`)}</Badge>
                    )}
                  </span>
                  {it.passed === false && it.help && <span className="mt-0.5 block text-muted">{it.help}</span>}
                </span>
              </li>
            ))}
          </ul>
        </div>
      ))}
    </div>
  </Card>
);
```

- [ ] **Step 5: Place the card + filter the sidebar.**
  - In the Überblick grid (the `tab === 'overview'` branch), insert `{checksCard}` right after the SERP/screenshots cards and before `{fileCard}`.
  - In the `CrawlSidebar` `items` prop, add a `.filter((code) => EVIDENCE_CODES.has(code))` step **before** the existing `.sort(...)` so detail-less codes no longer become sidebar tabs. (If a user is on a now-removed tab this can't happen from navigation since tabs are the only entry, but keep the existing `tab` default of `'overview'`.)

- [ ] **Step 6: Lang keys.** Add to `lang/en/crawler.php` (match the existing nested-array structure; place near other crawler keys):

```php
'checks' => 'Checks',
'check' => [
    'https' => 'HTTPS',
    'hsts' => 'Strict-Transport-Security (HSTS)',
    'csp' => 'Content-Security-Policy',
    'x_content_type_options' => 'X-Content-Type-Options',
    'x_frame_options' => 'X-Frame-Options',
    'referrer_policy' => 'Referrer-Policy',
],
'check_passed' => 'Passed',
'check_failed' => 'Failed',
'check_na' => 'Not applicable',
```

And `lang/de/crawler.php` (same `check.*` header names — they are proper nouns):

```php
'checks' => 'Prüfungen',
'check' => [
    'https' => 'HTTPS',
    'hsts' => 'Strict-Transport-Security (HSTS)',
    'csp' => 'Content-Security-Policy',
    'x_content_type_options' => 'X-Content-Type-Options',
    'x_frame_options' => 'X-Frame-Options',
    'referrer_policy' => 'Referrer-Policy',
],
'check_passed' => 'Erfüllt',
'check_failed' => 'Nicht erfüllt',
'check_na' => 'Nicht anwendbar',
```

Grep first to confirm these keys don't already exist and that the `crawler.php` file returns a nested array (the existing `crawler.issue.*` / `crawler.category_group.*` keys imply nested arrays — add `check`/`checks`/`check_*` at the same top level within the returned array).

- [ ] **Step 7: Build + regression + Pint.**

Run: `ddev npm run build` → green.
Run: `ddev php artisan test --filter=CrawlControllerTest` → PASS.
Run: `ddev exec vendor/bin/pint --test lang/en/crawler.php lang/de/crawler.php` → PASS.

- [ ] **Step 8: Commit.**
```bash
git add resources/js/Pages/Crawls/Page.tsx lang/en/crawler.php lang/de/crawler.php
git commit -m "feat(crawler-ui): category-grouped checks table on the page detail overview"
```
(End body with the Co-Authored-By line.)

---

## Final verification (whole plan)

- [ ] `ddev npm run build` — green.
- [ ] `ddev php artisan test` — full suite green.
- [ ] `ddev exec vendor/bin/pint --test` — clean.
- [ ] Manual/live: view a page detail with security findings — the sidebar no longer lists header checks; the Überblick tab shows a "Checks" section with a security ✓/✗ audit (HSTS n/a on an http page) and ✗ rows for other detail-less findings.

## Self-Review notes (author)

- **Spec coverage:** backend `issue_categories` (T1), shared `CATEGORY_ORDER` (T2), Page checklist + sidebar filter + lang (T3). ✓
- **Type consistency:** `issue_categories: Record<string,string>` produced by T1 and consumed by T3; `CheckItem.severity` uses the same union as `issue_severities`. ✓
- **No placeholders:** derivation + render + lang given verbatim. ✓
- **Honesty:** passed = code-absent; HSTS n/a on http; security only when HTML; dedupe via `shown` so a security detail-less failure isn't double-listed. ✓
- **Token risk:** `text-success-foreground` flagged to verify (fallback to Badge success token). ✓
- **No dup category logic:** category authority is backend (`issue_categories`); frontend only orders via `CATEGORY_ORDER`. ✓
