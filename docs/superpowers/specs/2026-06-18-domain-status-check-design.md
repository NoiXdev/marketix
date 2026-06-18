# Domain Section Refactor: CNAME Info Box & Status Check

**Date:** 2026-06-18
**Status:** Approved (design)
**Topic:** Domain DNS/reachability/SSL status checking + CNAME setup guidance

## Background

Domains today are minimal: `name`, `redirect_root`, `redirect_not_found` (see
`app/Models/Domain.php`, migration `2026_04_03_163010_create_domains_table.php`).
There is no status tracking or verification.

Routing and SSL are already handled by **Traefik**: `DomainObserver` triggers
`RegenerateTraefikConfigJob`, which writes `custom-domains.yml` with one HTTP router
per domain (Host rule) pointing at the central app, and enables Let's Encrypt TLS via
`certResolver`. So when a user adds a custom domain, their only job is to point a DNS
record at `APP_DOMAIN` (`config('app.domain')`); Traefik then routes the host and
auto-issues a certificate.

This feature adds (1) an info box telling the user to create that DNS record, and
(2) a three-stage status check — **DNS configured → reachable through Traefik →
valid SSL** — surfaced in the UI.

## Decisions

- **Check triggers:** both on-demand (button) **and** background (scheduled). Status
  is persisted so the UI shows last-known state.
- **DNS verification:** lenient — confirm the custom domain ultimately *resolves to the
  same place* as `APP_DOMAIN` (IP-set intersection). The info box still recommends a
  simple CNAME as the easy path, but the check is record-type-agnostic.
- **Reachability depth:** confirm it reaches **our** app by fetching a known marker
  route and matching our signature (not just "any server answered").
- **Cadence:** scheduler every ~15 min over all domains, plus on-create and on-demand.

## Data Model

New migration adds to the `domains` table:

| Column            | Type                | Purpose                                                        |
|-------------------|---------------------|----------------------------------------------------------------|
| `dns_ok`          | `boolean` nullable  | Domain resolves to the same place as `APP_DOMAIN`?             |
| `reachable_ok`    | `boolean` nullable  | HTTPS request returns our app signature?                       |
| `ssl_ok`          | `boolean` nullable  | Valid, unexpired TLS cert served for the domain?              |
| `check_details`   | `json` nullable     | Diagnostics: resolved IPs, `APP_DOMAIN` IPs, cert expiry, per-check error messages |
| `last_checked_at` | `timestamp` nullable| When the last check ran                                        |

- `null` = never checked / unknown (default for new domains).
- **Overall status** is *derived* in a model accessor, not stored:
  `healthy` when `dns_ok && reachable_ok && ssl_ok`; `pending` when any is `null` and
  none is `false`; `error` otherwise. The three checks stay separate so the UI can show
  exactly which stage fails.

`Domain` model: add `dns_ok`, `reachable_ok`, `ssl_ok`, `last_checked_at`,
`check_details` to `$fillable`/`$casts` as appropriate (`boolean`, `datetime`,
`array`/`AsArrayObject`). Add a `status` / `isHealthy()` accessor for the derived state.

## Status-Checker Service

`App\Services\DomainStatusChecker` — single responsibility: `check(Domain $domain): array`
returning the three booleans plus a `details` array. Three independent staged checks,
each wrapped in try/catch with a short timeout (~5s) so one failure never aborts the
others:

1. **DNS** — resolve the custom domain's final A records and resolve
   `config('app.domain')`'s A records; `dns_ok` = the two IP sets intersect. Store both
   resolved IP lists in `check_details`.
2. **SSL** — open a raw TLS stream socket to `{domain}:443`, read the peer certificate,
   verify it is not expired and its CN/SAN covers the domain. Store cert expiry in
   `check_details`.
3. **Reachable + signature** — HTTPS GET the marker route on the domain; confirm the
   response carries our signature. Records HTTP status / error in `check_details`.

**Local/DDEV behavior:** these are real network operations. Locally (`*.ddev.site`) they
typically report unreachable / no-SSL. The service degrades gracefully — every check
catches its own errors, records them in `check_details`, and returns `false`; it never
throws into the UI or the job.

### App signature route

`GET /.well-known/marketix` → returns `{"app":"marketix"}` with HTTP 200. Public (no
auth). Because Traefik routes every custom domain's host to the central app, this route
is reachable on each custom domain; fetching it over HTTPS and receiving our JSON proves
DNS + Traefik routing + the app are all wired correctly. The specific path does not
collide with short-link slugs handled by `RedirectController`.

### Testability seams

- DNS resolution behind a small injectable `DnsResolver` interface.
- TLS cert read behind a small injectable cert-reader interface.
- HTTP via Laravel's `Http` facade (fakeable).

This makes `DomainStatusChecker` unit-testable with no real network.

## Jobs, Scheduler & On-Demand Endpoint

`App\Jobs\CheckDomainStatusJob` (queued) — takes a `Domain`, runs `DomainStatusChecker`,
persists the three booleans + `check_details` + `last_checked_at`. This is the **single
write path** for status, shared by all triggers so they cannot diverge.

Triggers:

1. **On create** — `DomainObserver::created()` (already regenerates Traefik config) also
   dispatches `CheckDomainStatusJob::dispatch($domain)` for an initial status.
2. **Scheduled (~15 min)** — in `routes/console.php`,
   `Schedule::call(...)->everyFifteenMinutes()` dispatches the job for every domain.
   (Production already runs queue + scheduler.)
3. **On-demand** — new route
   `POST /project/{project}/domains/{domain}/check` → `DomainController@check`. Runs the
   checker **synchronously** (so the user sees fresh results immediately), persists, and
   returns an Inertia redirect back; the page reloads with updated status. Bounded by the
   ~5s-per-check timeouts. Project-scoped via `ProjectBindingMiddleware` (cannot check
   another project's domain).

## Frontend

Expose `appDomain` (`config('app.domain')`) as an Inertia prop from `DomainController`
on `create`, `edit`, and `index`.

### Info box

New component `resources/js/Pages/Domains/Partials/DnsInfoBox.tsx`, shown on **Create**
and **Edit**:

> **Connect your domain**
> At your DNS provider, point your domain to us with a CNAME record:
> `Type: CNAME · Host: <your-subdomain> · Value: {appDomain}`
> Once DNS propagates we automatically issue an SSL certificate — this can take a few minutes.

Includes a copy-to-clipboard button on the `{appDomain}` value. Styling follows existing
custom-React/Tailwind patterns (no new UI lib).

### Status display

- **Index.tsx** — new column with three small pills (DNS / Reachable / SSL): green ✓ /
  red ✗ / grey "–" (unknown), a relative `last_checked_at` ("checked 3 min ago"), and a
  per-row **"Check now"** button (spinner while posting).
- **Edit.tsx** — fuller status panel: each of the three checks with detail lines from
  `check_details` (resolved IPs, cert expiry, or error message) and a prominent
  **"Check now"** button — the place a user debugging a misconfiguration will look.

### Types

`resources/js/types/index.d.ts` `Domain` gains `dns_ok`, `reachable_ok`, `ssl_ok`
(`boolean | null`), `check_details` (typed shape), `last_checked_at`.

## Testing

Run via DDEV (`ddev composer run test`).

**Unit — `DomainStatusChecker`** (inject fakes, no real network):
- DNS: shared IP → `dns_ok = true`; disjoint IPs → `false`; resolver throws → `false`
  with error in `check_details`.
- Reachable: `Http::fake()` returning `{"app":"marketix"}` → `true`; wrong/empty body →
  `false`; timeout → `false`.
- SSL: fake cert reader — valid future expiry → `true`; expired → `false`; SAN mismatch
  → `false`.

**Feature:**
- `GET /.well-known/marketix` returns 200 + signature JSON.
- `POST .../domains/{domain}/check` runs the checker, persists the three booleans +
  `last_checked_at`, redirects back; enforces project scoping (mirrors existing
  cross-project isolation test pattern).
- `CheckDomainStatusJob` persists results for a domain.
- `DomainObserver::created()` dispatches the job (`Queue::fake()` assertion).

**Frontend gate:** `ddev npm run build` (TS check + Vite). Lint is known-broken; build is
the gate.

## Out of Scope

- Apex-domain A-record guidance / server-IP env var (lenient DNS check covers resolution
  regardless of record type).
- Per-domain verification tokens (`.well-known` marker signature is sufficient).
- Manual SSL cert upload / management (Traefik + Let's Encrypt is automatic).
- Faster polling for not-yet-healthy domains (flat 15-min cadence for now).
