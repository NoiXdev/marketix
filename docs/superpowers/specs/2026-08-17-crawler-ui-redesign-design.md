# Crawler UI redesign — Design

**Status:** approved for planning
**Scope:** `resources/js/Pages/Crawls/Show.tsx` + `resources/js/Pages/Crawls/Page.tsx`.
Frontend only — **no controller/backend/data changes** (all needed data is already in the
Inertia props), so the existing PHP suite stays green.

## Goal

Replace the crawl overview's flat ~17-tab bar + flat count-card grid with **direction A**
(chosen from the mockups): a left **category navigation sidebar** (counts + severity dots)
and a **dashboard-style Overview** (severity tiles + a "most common issues" list). Apply
the same sidebar + consistent-severity treatment to the page-detail view (`Crawls/Page`),
which is also tab-heavy. Purely presentational — routing (query params on Show, local tab
state on Page) and all data contracts are unchanged.

## Design reference

The two approved mockups from the brainstorm: (1) `Crawls/Show` = left sidebar (Overview /
All URLs / Resources, then "Probleme nach Kategorie" with per-category count + severity
dot) + Overview dashboard (Errors/Warnings/Notices tiles + "Häufigste Probleme" list with
click-through). Page-detail gets the same left-sidebar shell (Übersicht + one entry per
issue, severity-dotted) with the selected content in the main area.

## Non-goals / constraints

- No changes to `CrawlController`, models, migrations, lang keys' meaning, or the check
  logic. New lang keys only if a new visible label is needed (e.g. a "severity totals"
  heading) — reuse existing keys where possible.
- Reuse the existing UI kit (`Badge`, `StatusPill`, `Card`, `TableCard`, `Pagination`,
  `Select`, `Link`) and design tokens (`bg-surface`, `text-foreground`, `text-muted`,
  `border-line`, `bg-elevated`, `bg-accent`/`text-accent`, and the danger/warning/neutral
  `-foreground`/`-soft` roles). No raw hex, no new color system.
- Theme-aware (light/dark) via the existing tokens.
- Gate: `ddev npm run build` green (tsc + vite); the PHP suite stays green (unchanged);
  Pint only where PHP is touched (expected: none).

## Data derivation (all client-side from existing props)

`Show` receives `catalog: Record<category, { count: number; checks: {code, severity,
status, count}[] }>` and `crawl.summary`. From `catalog` alone:

- **Severity tiles** (Overview): iterate every active check across all categories, sum its
  `count` into an `error` / `warning` / `notice` bucket by `severity` (skip `info`). Render
  three tiles (danger / warning / neutral tokens).
- **"Häufigste Probleme"** (Overview): flatten active checks with `count > 0` → sort by
  `count` desc → take the top ~8 → each row = severity dot + issue label
  (`crawler.issue.<code>`) + count, clicking navigates `go({ group: category, issue: code })`.
- **Category sidebar entries**: iterate `CATEGORY_ORDER`; entry = label
  (`crawler.category_group.<cat>`) + `catalog[cat].count` badge + a **severity dot** =
  worst severity among that category's active checks with `count > 0`
  (error > warning > notice > info); a category with `count === 0` renders dimmed.

`Page` receives `issue_severities: Record<code, severity>` + `issues: string[]` — the
sidebar issue order is worst-severity-first.

A shared helper module provides: `severityRank(sev)`, `severityDotClass(sev)`,
`severityBadgeVariant(sev)` (reused by both pages, replacing the per-file maps).

## `Crawls/Show` layout

Two-column below the existing `PageHeader` + `Flash` + error card:

- **Left sidebar** (`md:w-56 flex-none`, top-aligned): a primary group — Überblick /
  Alle URLs / Ressourcen (icons + active state via `bg-accent`/`text-accent`) — then a
  "Probleme nach Kategorie" label and the 14 category entries (count badge + severity dot;
  dimmed when 0). Each item calls the existing `go()` with the same params it uses today
  (`{}`, `{category}`, `{view:'resources'}`, `{group}`), so routing is unchanged. The
  active item is derived from `activeTab` exactly as now.
- **Main area** (`flex-1 min-w-0`):
  - **Overview** → the dashboard: the three severity tiles (grid) + the "Häufigste
    Probleme" list (bordered rows). (The old Overview pages-table is dropped — the full
    page list lives under "Alle URLs".)
  - **All URLs** → the existing content-type filter + `pagesTable()`.
  - **A category** → the existing per-check dropdown + `pagesTable()` (+ the
    `category_planned_note` / `category_no_issues` empty states, unchanged).
  - **Resources** → the existing chips + type filter + resources table, or the refs panel
    when `filters.resource` is set (Increment-3 behaviour, unchanged).
- **Responsive:** below `md`, the sidebar collapses — render the primary group as a small
  segmented row and the categories as a `<Select>` (label + count) so the page stays usable
  on narrow screens. (The tab bar's current wrapping is what we're removing.)

## `Crawls/Page` layout

Same shell, driven by the existing local `tab` state (no query params):

- **Left sidebar**: "Übersicht" + one entry per `page.issues` code (severity dot +
  `crawler.issue.<code>` label), ordered worst-severity-first. Active item = `tab`.
  Responsive: `<Select>` below `md`.
- **Main area**: Overview → the existing card grid (SERP, screenshots, file, found-on,
  meta, headings, out-links, structured-data, images, redirect-chain — unchanged); an issue
  → the existing severity badge + `issue_help.<code>` + `issueEvidence(code)`.
- Consistent severity colouring via the shared helper (replaces the local `severityVariant`
  / `severityDot` maps).

## Files

**Create**
- `resources/js/lib/severity.ts` — `severityRank` / `severityDotClass` / `severityBadgeVariant` (+ types).
- `resources/js/Components/CrawlSidebar.tsx` — the reusable sidebar shell (primary items + a
  labelled section of nav entries, each with optional count badge + severity dot; active
  state; `md` sidebar / sub-`md` Select). Presentational; parents pass items + onSelect.

**Modify**
- `resources/js/Pages/Crawls/Show.tsx` — sidebar layout + dashboard Overview; keep `go()`
  routing + all existing tab bodies (All URLs / category / Resources) intact.
- `resources/js/Pages/Crawls/Page.tsx` — sidebar layout + consistent severity; keep tab
  bodies intact.
- `lang/en/crawler.php`, `lang/de/crawler.php` — only if a new heading is needed
  (candidates: `dashboard_top_issues`, `dashboard_severity` — add if used).

**No changes:** controllers, models, migrations, `IssueCode`/`CheckCatalog`, other components.

## Testing / verification

- No React unit tests exist in this project; the gate is **`ddev npm run build`** (tsc
  strict + vite) — the redesign must compile with the existing prop types.
- The PHP suite (`ddev php artisan test`) stays green because no backend/props changed —
  run it as a regression sanity check.
- `CrawlControllerTest`'s Inertia assertions are unaffected (same prop shapes).
- Reviewer verifies: routing (`go()` params) and data contracts unchanged; `activeTab`
  derivation intact; severity derivation from `catalog` correct; empty/planned states
  preserved; the layout matches the approved mockup; theme tokens (no raw hex).
- After merge, a live look is the real check — offer to launch the app / screenshot the
  crawl show page.

## Risks / decisions

- **Purely visual, weak automated coverage** — build-green + unchanged PHP suite + a
  diff review are the gates; final acceptance is the user viewing it live.
- **Severity totals are client-derived** from `catalog` (no backend) — a category's dot =
  its worst active check with count>0; tiles sum active-check counts by severity (info
  excluded), consistent with the existing "info not counted" rule.
- **Overview no longer shows the full pages table** (moved to "Alle URLs") — intentional;
  Overview becomes a scannable dashboard.
- **Sidebar scales to 14 categories** (scroll) and collapses to a `<Select>` on mobile —
  the core fix for the tab-wrap problem.
