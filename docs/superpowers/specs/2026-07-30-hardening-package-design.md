# Hardening Package — Design

**Date:** 2026-07-30
**Status:** Approved (pending spec review)

## Summary

A focused security/robustness hardening package addressing the four MUST-level
gaps found in the production-readiness review. Four independent workstreams in
one spec:

- **A** — Rate-limiting on authentication routes (brute-force / enumeration).
- **B** — Database index on the redirect hot-path lookup (`domains.name`).
- **C** — Bounded growth of the `statistics` table via a scheduled prune with a
  configurable retention window (default 12 months).
- **D** — Feature tests for redirect correctness (expiry / password gate /
  deactivated / archived) — critical logic that exists but is currently untested.

Explicitly out of scope (separate future work): bot filtering, link
pagination/search, public API / Sanctum tokens, error monitoring, backups, GDPR
self-service, and the `member_added` activity-log gap.

## Problem & Context

`marketix` is a Laravel 13 + React/Inertia multi-tenant URL shortener. The
review confirmed the codebase is clean and its design specs are implemented, but
surfaced four production-hardening gaps that carry security, correctness, or
data-growth risk:

1. `AuthController::login` and the forgot-password / reset routes have **no rate
   limiting** → unlimited password guessing and user/email enumeration. (2FA
   challenge is already throttled and stays as-is.)
2. The redirect hot-path resolves the host via `Domain::where('name', $host)`,
   but `domains.name` has **no index** → an unindexed scan on the hottest query.
3. The `statistics` table **grows unbounded** — no pruning or rollup — causing DB
   bloat and progressively slower live aggregation.
4. Redirect correctness (expiry → 410/not-found, password gate, deactivated /
   archived handling) exists in `RedirectController` but has **no test coverage**;
   a regression would silently mis-serve links.

## Workstream A — Auth Rate Limiting

**Login** (`POST /auth/login`):
- A named limiter `RateLimiter::for('login', ...)` keyed on **`email + IP`**,
  **5 attempts/minute**. On exceed, respond with HTTP 429 / a "too many attempts"
  validation error including a retry-after countdown (Laravel's standard
  `ThrottleRequests` behavior).
- A successful login clears the counter.
- Apply via `throttle:login` middleware on the login POST route.

**Forgot-password** (`POST /auth/forgot-password`) and **reset-submit**
(`POST /auth/reset-password`):
- **5 requests/minute per IP** via `throttle` middleware (mitigates email-bombing
  and enumeration through the reset flow).

**Unchanged:** the 2FA challenge routes keep their existing throttle.

**Registration point:** define the `login` limiter in `AppServiceProvider::boot`
(or the app's existing bootstrap for rate limiters) alongside Laravel's
conventions; attach `throttle` middleware in `routes/web.php`.

## Workstream B — `domains.name` Index

- New forward-only migration adding an index on `domains.name`.
- During implementation, verify whether `domains.name` values are globally unique
  (a hostname resolving to exactly one project). If they are, use a **`unique`**
  index (integrity + speed); otherwise a plain index. The migration must account
  for the possibility of pre-existing duplicates if choosing unique.
- Style matches existing statistics/domain migrations (forward-only, explicit
  index name).

## Workstream C — Statistics Pruning

- **Config:** `config/statistics.php` exposing
  `retention_months => env('STATISTICS_RETENTION_MONTHS', 12)`.
- **Command:** `statistics:prune` — computes a cutoff of
  `now()->subMonths(retention_months)->startOfDay()` and **hard-deletes** (force
  delete, bypassing SoftDeletes) all `statistics` rows with
  `created_at < cutoff`, in **chunks** (e.g. delete-by-id batches) to avoid long
  locks on large tables. Idempotent and safe to re-run.
- **Schedule:** run `statistics:prune` **daily** in `routes/console.php`.
- **Explicit invariant:** the denormalized `urls.clicks` / `urls.unique_clicks`
  counters are incremented per click and are NOT derived from `statistics` rows,
  so all-time click *counts* are unaffected by pruning. Only the breakdowns
  (country/browser/os/referrer/city) and day-series lose rows older than the
  retention window — the accepted trade-off of a raw-delete (vs rollup) policy.

## Workstream D — Redirect Correctness Tests

Feature tests exercising `RedirectController@handle` on the fallback route:

- **Expired link** (`expired_at` in the past) → the not-found redirect / `abort(410)`
  behavior as implemented.
- **Password-protected link** → unauthenticated request is gated (no target
  leak); providing the correct password sets the `url_verified_{id}` session key
  and allows through; wrong password is rejected.
- **Deactivated** (`UrlStatus::DEACTIVATED`) and **archived** links → 404 /
  redirect as implemented.

Tests assert against the real controller behavior (route-based, using `route()`
per the repo's host-binding test convention), no changes to production code in
this workstream.

## Testing

- Backend gate: `ddev php artisan test`. Frontend untouched.
- Per workstream:
  - **A:** login lockout after 5 failed attempts (429 / error), counter reset on
    success; forgot-password throttle after 5/min.
  - **B:** schema assertion that the index exists on `domains.name`.
  - **C:** prune deletes rows older than the cutoff, keeps newer rows, is
    idempotent, and honors a configured retention value; verify `urls.clicks`
    counters are untouched.
  - **D:** the redirect cases above.
- Follow existing test patterns (`RefreshDatabase`, factories, `route()` not bare
  paths).

## Delivery

Each workstream is an independently reviewable, independently testable unit and
should land as its own commit / PR (repo allows rebase-merge only; CI Pint +
PHPUnit must pass). Recommended order: B (index, lowest risk) → A (throttling)
→ C (pruning) → D (tests), though D can also be written first against the
existing behavior.

## Out of Scope (YAGNI)

Bot/crawler filtering for stats, redirect-route throttling, link
pagination/search, tags/folders, UTM builder, public API / Sanctum tokens, GDPR
export & self-service account deletion, error monitoring, backups, rollup
aggregation of statistics, and the `member_added` activity-log deviation. Each is
a candidate for a later, separately-scoped change.
