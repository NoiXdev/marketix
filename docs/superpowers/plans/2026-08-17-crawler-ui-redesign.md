# Crawler UI redesign — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Redesign `Crawls/Show` (left category sidebar + dashboard Overview, replacing the ~17-tab bar + count-card grid) and `Crawls/Page` (same sidebar shell + consistent severity). Frontend only — no controller/prop/data changes.

**Architecture:** A shared `lib/severity.ts` (severity→token helpers) + a reusable presentational `CrawlSidebar.tsx`; both pages restructure into a two-column (sidebar + main) layout, deriving all counts client-side from the existing `catalog` / `issue_severities` props. Routing (`go()` query params on Show; local `tab` state on Page) and every existing tab body are preserved verbatim — only moved into the main column.

**Tech Stack:** React 19 + TypeScript, Inertia, Tailwind + the project's design tokens, the `@/Components/ui` kit.

## Global Constraints

- **No backend changes.** Do not touch `CrawlController`, models, migrations, `IssueCode`/`CheckCatalog`, or the Inertia prop shapes. The PHP suite must stay green unchanged.
- **Use the project's real token class names.** Tailwind classes that don't exist silently no-op (no build error), so DO NOT invent tokens — grep `@/Components/ui` + the current `Show.tsx`/`Page.tsx` for the actual classes in use (e.g. the active-tab treatment, `bg-elevated`, `text-muted`, `text-foreground`, `border-line`, `bg-danger-soft`/`text-danger-foreground`, `bg-warning-soft`/`text-warning-foreground`, `bg-neutral-soft`/`text-neutral-foreground`, the accent-soft pair) and reuse those exact names. Where this plan shows a class, treat it as the intended role — swap to the project's actual token if the name differs.
- Theme-aware (light/dark) via those tokens; no raw hex.
- Reuse the UI kit (`Badge`, `StatusPill`, `Card`, `TableCard`, `Pagination`, `Select`, `Link`, `PageHeader`, `BackLink`, `Flash`).
- **Gate:** `ddev npm run build` (tsc strict + vite) green; `ddev php artisan test` green (regression — unchanged). Lint is broken; the build is the frontend gate.

---

## Task 1: shared `severity` helpers + `CrawlSidebar` component

**Files:** Create `resources/js/lib/severity.ts`, `resources/js/Components/CrawlSidebar.tsx`.

**Interfaces:** Produces `Severity`, `severityRank`, `severityDotClass`, `severityBadgeVariant`, and the `CrawlSidebar` component consumed by Tasks 2 & 3.

- [ ] **Step 1: `lib/severity.ts`.** Create it:

```ts
export type Severity = 'error' | 'warning' | 'notice' | 'info';

const RANK: Record<Severity, number> = { error: 3, warning: 2, notice: 1, info: 0 };
const DOT: Record<Severity, string> = {
  error: 'bg-danger-foreground',
  warning: 'bg-warning-foreground',
  notice: 'bg-neutral-foreground',
  info: 'bg-neutral-foreground',
};

export function severityRank(sev: Severity): number {
  return RANK[sev] ?? 0;
}

export function severityDotClass(sev: Severity): string {
  return DOT[sev] ?? 'bg-neutral-foreground';
}

export function severityBadgeVariant(sev: Severity): 'danger' | 'warning' | 'neutral' {
  return sev === 'error' ? 'danger' : sev === 'warning' ? 'warning' : 'neutral';
}
```

  (Confirm the dot background classes match the ones `Page.tsx` currently uses — reuse those exact names.)

- [ ] **Step 2: `Components/CrawlSidebar.tsx`.** A presentational sidebar: a primary nav group, then an optional labelled section of entries (each with an optional count badge + severity dot + dimmed state). Desktop = vertical sidebar; below `md` = primary as a wrapped button row + the section as a `<Select>`. Parents pass items + `onSelect`; the component holds no routing logic.

```tsx
import { Select } from '@/Components/ui';

export type CrawlNavItem = {
  key: string;
  label: string;
  active: boolean;
  onSelect: () => void;
  count?: number;
  dotClass?: string;
  dimmed?: boolean;
};

export function CrawlSidebar({
  primary,
  sectionLabel,
  items = [],
}: {
  primary: CrawlNavItem[];
  sectionLabel?: string;
  items?: CrawlNavItem[];
}) {
  const all = [...primary, ...items];
  const item = (it: CrawlNavItem) => (
    <button
      key={it.key}
      type="button"
      aria-current={it.active ? 'page' : undefined}
      onClick={it.onSelect}
      className={`flex w-full items-center justify-between gap-2 rounded-md px-3 py-1.5 text-left text-sm transition ${
        it.active ? 'bg-accent-soft font-medium text-accent-soft-foreground' : 'text-muted hover:bg-elevated hover:text-foreground'
      } ${it.dimmed ? 'opacity-50' : ''}`}
    >
      <span className="flex min-w-0 items-center gap-2">
        {it.dotClass && <span className={`h-1.5 w-1.5 shrink-0 rounded-full ${it.dotClass}`} />}
        <span className="truncate">{it.label}</span>
      </span>
      {it.count !== undefined && <span className="shrink-0 text-xs text-muted">{it.count}</span>}
    </button>
  );

  return (
    <>
      <nav className="hidden space-y-0.5 md:block md:w-56 md:flex-none">
        {primary.map(item)}
        {sectionLabel && items.length > 0 && (
          <>
            <div className="px-3 pb-1 pt-4 text-xs text-muted">{sectionLabel}</div>
            {items.map(item)}
          </>
        )}
      </nav>

      <div className="mb-4 space-y-2 md:hidden">
        <div className="flex flex-wrap gap-1">
          {primary.map((it) => (
            <button
              key={it.key}
              type="button"
              onClick={it.onSelect}
              className={`rounded-md px-3 py-1.5 text-sm ${it.active ? 'bg-accent-soft font-medium text-accent-soft-foreground' : 'text-muted hover:text-foreground'}`}
            >
              {it.label}
            </button>
          ))}
        </div>
        {items.length > 0 && (
          <Select
            value={items.find((i) => i.active)?.key ?? ''}
            onChange={(e) => all.find((i) => i.key === e.target.value)?.onSelect()}
            className="w-full"
          >
            <option value="" disabled>{sectionLabel}</option>
            {items.map((it) => (
              <option key={it.key} value={it.key}>
                {it.label}
                {it.count !== undefined ? ` (${it.count})` : ''}
              </option>
            ))}
          </Select>
        )}
      </div>
    </>
  );
}
```

  Align the active-state classes (`bg-accent-soft`/`text-accent-soft-foreground`) to whatever the project actually defines — grep the UI kit; if there's no `accent-soft` pair, use the existing active treatment (e.g. `bg-accent text-accent-foreground` or the current tab's `text-foreground` + an accent marker). The component must render correctly, not silently no-op.

- [ ] **Step 3: Build gate.**

Run: `ddev npm run build` → green (the new files compile; unused-until-Task-2/3 is fine — they're imported next).
(If the build tree-shakes/errors on an unused export, that's expected to resolve once Tasks 2-3 import them; a bare `build` of just these two files should still tsc-check clean.)

- [ ] **Step 4: Commit.** `git add resources/js/lib/severity.ts resources/js/Components/CrawlSidebar.tsx && git commit -m "feat(crawler-ui): shared severity helpers + CrawlSidebar component"`

---

## Task 2: `Crawls/Show` — sidebar layout + dashboard Overview

**Files:** Modify `resources/js/Pages/Crawls/Show.tsx` (+ `lang/en/crawler.php`, `lang/de/crawler.php` if new headings are used).

**Interfaces:** Consumes `CrawlSidebar` + `lib/severity` (Task 1). Props unchanged.

- [ ] **Step 1: Derive severity data from `catalog`.** In `CrawlsShow`, add memoised derivations from the existing `catalog` prop:
  - `severityTotals` = `{error, warning, notice}` — iterate every `catalog[cat].checks` with `status === 'active'`, add `check.count` to the bucket for `check.severity` (skip `info`).
  - `topIssues` = flatten active checks with `count > 0` to `{code, severity, count, category}`, sort by `count` desc, take the first 8.
  - `categoryDot(cat)` = `severityDotClass` of the worst severity (`severityRank`) among `catalog[cat].checks` that are `active` && `count > 0`; `undefined` when the category has none.

- [ ] **Step 2: Replace the tab bar with `CrawlSidebar` + a two-column wrapper.** Remove the `<div … role="tablist">…TabButton…</div>` bar and the local `TabButton` function. Wrap the content region in `<div className="flex flex-col gap-6 md:flex-row">`, with `<CrawlSidebar …/>` first and `<div className="min-w-0 flex-1">…main…</div>` second. Build the sidebar props:
  - `primary`: Überblick → `go({})` (active when `activeTab === 'overview'`), Alle URLs → `go({ category: filters.category })` (active `'all'`), Ressourcen → `go({ view: 'resources' })` (active `'resources'`) — labels `crawler.tab_overview` / `crawler.all_urls` / `crawler.tab_resources`.
  - `sectionLabel`: `t('crawler.category_group_section')` (new key, or reuse an existing "Probleme nach Kategorie"-style label — add `category_group_section` EN "Issues by category" / DE "Probleme nach Kategorie").
  - `items`: `CATEGORY_ORDER.map((key) => ({ key, label: t(\`crawler.category_group.${key}\`), active: activeTab === key, onSelect: () => go({ group: key }), count: catalog[key]?.count ?? 0, dotClass: categoryDot(key), dimmed: (catalog[key]?.count ?? 0) === 0 }))`.
  - `activeTab` derivation is unchanged.

- [ ] **Step 3: Dashboard Overview.** Replace the `activeTab === 'overview'` body (the summary-card grid + `pagesTable()`) with the dashboard:
  - **Severity tiles**: a 3-col grid of tiles for Errors / Warnings / Notices using `severityTotals` — danger / warning / neutral soft-surface tiles (mirror the existing card/tile styling; number + label). Labels: reuse `crawler.severity_error` / `_warning` / `_notice`.
  - **"Häufigste Probleme"** heading (add key `crawler.dashboard_top_issues` EN "Most common issues" / DE "Häufigste Probleme") + a bordered list (`TableCard` or a bordered `div`): each `topIssues` row = severity dot (`severityDotClass`) + `t(\`crawler.issue.${code}\`)` + count, the whole row a button calling `go({ group: category, issue: code })`. If `topIssues` is empty, show a friendly "no issues" line (reuse `crawler.category_no_issues`).
  - Do NOT render the full pages table on Overview (it now lives under Alle URLs).

- [ ] **Step 4: Keep the other tab bodies.** The `activeTab === 'all'`, `CATEGORY_ORDER.includes(activeTab)`, and `activeTab === 'resources'` blocks (content-type filter + `pagesTable()`; per-check dropdown + planned/no-issues empty states; resources chips/filter/table + refs panel) move INTO the main column unchanged. `pagesTable()`, `hasActiveChecks`, `go()`, `formatBytes`, the resources rendering, and all prop types stay as-is.

- [ ] **Step 5: Lang keys.** Add to BOTH lang files any new keys used: `category_group_section`, `dashboard_top_issues` (EN/DE as above). Reuse `severity_error`/`_warning`/`_notice`, `category_no_issues`, existing tab/category labels.

- [ ] **Step 6: Build + regression.**

Run: `ddev npm run build` → green.
Run: `ddev php artisan test --filter=CrawlControllerTest` → PASS (props unchanged).
Run: `ddev exec vendor/bin/pint --test lang/en/crawler.php lang/de/crawler.php` → PASS.

- [ ] **Step 7: Commit.** `git add resources/js/Pages/Crawls/Show.tsx lang/en/crawler.php lang/de/crawler.php && git commit -m "feat(crawler-ui): category sidebar + dashboard overview on the crawl show page"`

---

## Task 3: `Crawls/Page` — sidebar shell + consistent severity

**Files:** Modify `resources/js/Pages/Crawls/Page.tsx`.

**Interfaces:** Consumes `CrawlSidebar` + `lib/severity` (Task 1). Props + local `tab` state unchanged.

- [ ] **Step 1: Use the shared severity helpers.** Remove the local `severityVariant` / `severityDot` maps; import `severityBadgeVariant` / `severityDotClass` / `severityRank` from `@/lib/severity` and use them in the issue-body header badge (`severityBadgeVariant(page.issue_severities[tab] ?? 'notice')`) and the sidebar dots.

- [ ] **Step 2: Replace the tab bar with `CrawlSidebar` + two-column wrapper.** Remove the `<div … role="tablist">…tabs.map…</div>` bar. Wrap the content in `<div className="flex flex-col gap-6 md:flex-row">` with `<CrawlSidebar …/>` + `<div className="min-w-0 flex-1">…main…</div>`. Sidebar props:
  - `primary`: a single item — Übersicht → `setTab('overview')`, active when `tab === 'overview'`, label `crawler.tab_overview`.
  - `sectionLabel`: `t('crawler.issues')` (existing key "Issues"/"Probleme").
  - `items`: `page.issues` mapped to `{ key: code, label: t(\`crawler.issue.${code}\`), active: tab === code, onSelect: () => setTab(code), dotClass: severityDotClass(page.issue_severities[code] ?? 'notice') }`, **sorted worst-severity-first** via `severityRank(page.issue_severities[b]) - severityRank(page.issue_severities[a])`.

- [ ] **Step 3: Keep the bodies.** The `tab === 'overview'` card grid and the issue-body (`severity badge + issue_help + issueEvidence(tab)`) move INTO the main column unchanged (except the badge now uses `severityBadgeVariant`). All the card builders (`serpCard`, `fileCard`, `metaCard`, …) and `issueEvidence()` stay as-is.

- [ ] **Step 4: Build.**

Run: `ddev npm run build` → green.
Run: `ddev php artisan test --filter=CrawlControllerTest` → PASS (sanity — page-detail route/props unchanged).

- [ ] **Step 5: Commit.** `git add resources/js/Pages/Crawls/Page.tsx && git commit -m "feat(crawler-ui): sidebar shell + consistent severity on the page detail view"`

---

## Final verification (whole plan)

- [ ] `ddev npm run build` — green (tsc strict + vite).
- [ ] `ddev php artisan test` — full suite green (unchanged backend).
- [ ] `ddev exec vendor/bin/pint --test` — clean (only the two lang files touched PHP-side).
- [ ] Manual/live: launch the app and view a crawl's show page + a page detail to confirm the redesign renders as intended (the real acceptance for a visual change).

## Self-Review notes (author)

- **Spec coverage:** shared helpers + sidebar (Task 1), Show sidebar + dashboard (Task 2), Page sidebar + severity (Task 3). ✓
- **No backend/prop change** — PHP suite unaffected; only two lang files gain keys (Pint-checked). ✓
- **Routing/state preserved:** Show still navigates via `go()` query params; Page still via local `tab`; only the *presentation* of the selector moves from tabs to sidebar. ✓
- **Data derived from existing `catalog`** (severity tiles/top issues/category dots) — no new endpoint. ✓
- **Token risk called out:** implementer must use real token class names (Tailwind no-ops unknown classes); reviewer verifies against the UI kit + that the layout matches the approved mockup. ✓
- **Weak automated coverage (visual):** build-green + unchanged PHP suite are the gates; final acceptance is a live look. ✓
