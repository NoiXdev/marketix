# Clicks-by-Country Choropleth Map — Design

**Date:** 2026-06-25
**Status:** Approved (pending spec review)

## Summary

Add a world choropleth map to the statistics UI showing which countries generate
the most clicks. Countries are shaded by click volume (darker = more clicks). The
map appears on **both** statistics surfaces — the project-level dashboard and the
per-link stats page — via a single shared React component.

## Problem & Context

Clicks already record geo data. `GeoIpService` resolves a country name, city,
and ISO `country_code` from the visitor's (hashed) IP on every click. Today the
statistics table persists only the country **name** string (e.g. `"Germany"`),
and both stats pages render a "top countries" table/bar breakdown. There is no
geographic visualization, and the persisted name is locale/spelling-sensitive,
which makes it a poor key for coloring map regions.

A choropleth keys on **ISO 3166-1 alpha-2 codes**. We already compute the code
on every click — we just discard it before saving.

## Architecture

```
Click → RedirectController → GeoIpService.lookup()  ──┐ (country, city, country_code)
                                                       ▼
                                  RecordClickStatisticJob
                                  persists country, city, country_code   ◄── NEW: country_code
                                                       ▼
                                          statistics table (+ country_code col)
                                                       ▼
                          StatisticsAggregator.breakdownByCountryCode()   ◄── NEW
                                                       ▼
            StatisticsController / UrlController → Inertia prop `clicksByCountry`  ◄── NEW
                                                       ▼
                          <WorldMap data={clicksByCountry} />   ◄── NEW shared component
                                                       ▼
                  Statistics/Index.tsx  &  Links/Show.tsx  (both reuse it)
```

## Data Layer

### Migration
- Add nullable `country_code` (`CHAR(2)`, ISO 3166-1 alpha-2) to the `statistics`
  table, indexed alongside the existing geo columns.
- Forward-only migration, matching the existing migration style in this repo.

### Write path
- `RecordClickStatisticJob` already receives the full geo array
  (`['country' => 'Germany', 'country_code' => 'DE', 'city' => ..., ...]`) from
  `GeoIpService`. It currently saves `country`/`city` and drops the code.
- Change: also persist `country_code` from the geo array. No changes to
  `GeoIpService` or `RedirectController` — the code is already computed and passed.

### Backfill
- A one-time backfill (migration step or artisan command) maps existing distinct
  `country` name values → ISO codes using a static name→code lookup table, updating
  historical rows.
- Rows whose name is not in the table remain `country_code = null`. They are simply
  absent from the map; they remain fully counted in totals and in the existing
  name-based table breakdown.

### Aggregation
- Add `breakdownByCountryCode()` to `StatisticsAggregator`, grouping by
  `country_code` and returning rows shaped:
  ```php
  { country_code: "DE", country: "Germany", count: 234 }
  ```
  (`country` is the representative name for tooltip/label display.)
- Rows with `country_code = null` are excluded from this aggregation.
- The window/filtering honors the same `$since` / `days` range already passed to
  the other aggregations.

### Controller integration
- `StatisticsController` (project level) and `UrlController::show` (per link) each
  pass a new Inertia prop `clicksByCountry` produced by `breakdownByCountryCode()`,
  using the same range window already in use.
- The existing `topCountries` prop and its table breakdown are **untouched**.

## Frontend Rendering

### Library choice
Render with a **custom SVG component built on `d3-geo` + `topojson-client`** —
not `react-simple-maps`. Rationale:
- `react-simple-maps`' published peer deps lag React 19 (this project is on
  React 19) and it has been lightly maintained — install friction and future churn.
- A custom component matches the existing hand-rolled SVG charts (e.g. the raw-SVG
  `BarChart` in `Statistics/Index.tsx`), needs no API key, carries no React-version
  risk, and gives full control over theming and tooltips.
- A tile map (Mapbox/Leaflet) is overkill: API key/tiles, heavy bundle, and a
  pannable globe is the wrong tool for a static "top countries" summary.

**Dependencies added:** `d3-geo`, `topojson-client`, and a small bundled world
TopoJSON (~100KB) served as a static asset.

### Component
- `resources/js/Components/WorldMap.tsx`
- Props: `{ data: { country_code: string; country: string; count: number }[] }`
- Behavior:
  - Load the world TopoJSON once; project with `geoNaturalEarth1()`.
  - Render one `<path>` per country.
  - Fill via a **quantized** color scale (≈5 buckets) from a light neutral to the
    brand color, computed from the max count in the current dataset.
  - Countries with zero clicks render in a faint "no data" fill.
  - Hover tooltip shows country name + click count.
  - Small legend indicating the scale.
- Reused verbatim on both the project and per-link pages.

## Page Integration

- Drop `<WorldMap data={clicksByCountry} />` into both `Statistics/Index.tsx` and
  `Links/Show.tsx`, placed **above** the existing country table breakdown — the map
  gives the overview, the table gives exact numbers.
- Both surfaces continue to respect the existing `days` range filter, since
  `clicksByCountry` is computed with the same window as the other aggregations.

## States & Error Handling

- **Empty** (no geo-tagged clicks yet): render the world map greyed out with a small
  "No location data yet" caption rather than hiding it, so layout stays stable.
- **Unknown / null codes**: rows with `null` country_code are silently excluded from
  the map but remain counted in totals and the name-based table.
- **TopoJSON load**: bundled as a static asset and imported, so there is no network
  failure path. If the import ever fails, the component renders the caption fallback
  and never throws.

## Testing

### Backend
- `breakdownByCountryCode()` groups by code and returns the joined representative name.
- `RecordClickStatisticJob` persists `country_code` from the geo array.
- Backfill maps known names to codes and leaves unknown names `null`.
- Follow existing statistics test patterns (use `route()`, not bare paths, per the
  project's test-host gotcha).

### Frontend
- Gate is `npm run build` (TypeScript check), since `npm run lint` is broken in this
  project.
- Verify the component renders with sample data and renders the empty state.
- No new test runner introduced.

## Out of Scope (YAGNI)

- City-level or lat/lng marker maps.
- Pan/zoom or interactive drill-down.
- Sub-national (region/state) choropleth.
- Changing or removing the existing country table/bar breakdown.
