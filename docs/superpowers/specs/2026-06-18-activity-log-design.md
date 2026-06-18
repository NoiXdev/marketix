# Activity Log — Design Spec

**Date:** 2026-06-18
**Status:** Approved for planning
**Package:** `spatie/laravel-activitylog`

## Purpose

Add an activity log serving two audiences:

1. **Audit trail** — accountability for "who changed what, when" across content, membership, and security events. Surfaced to super admins.
2. **User-facing activity feed** — a friendly per-project timeline shown to project members.

## Scope

**Logged (scope B):** content CRUD on `Url`, `Domain`, `QrCode`, `Pixel`, `Project`; membership changes (add/remove/role change); invitations (sent/accepted/revoked/resent); security events (login, password change/reset, 2FA enable/disable, passkey add/remove).

**Not logged:** `Statistic` (click tracking is its own subsystem — logging clicks here would be massive duplication and noise).

## Approach

**Approach 1 (chosen):** Spatie's `LogsActivity` trait on each content model with declarative `getActivitylogOptions()`. Non-CRUD events (membership, invitation, security) logged manually via an `activity()` helper. The trait gives automatic create/update/delete logging with attribute diffs; manual logging covers events that can't be derived from a model save.

Rejected: fully manual logging (verbose, easy to miss call sites, loses diffing); observer-based logging (reinvents the trait; would muddy the existing single-purpose `UrlObserver`).

## Architecture

### 1. Package & schema

- Install `spatie/laravel-activitylog`; publish migration + config.
- **Migration 1 (stock):** `activity_log` table — `log_name`, `description`, `subject` morphs, `causer` morphs, `properties` JSON, `event`, `batch_uuid`.
- **Migration 2 (custom):** add `project_id` — nullable ULID (matches model keys), FK to `projects`, indexed. Add composite index `(project_id, created_at)` for the feed query.

### 2. Custom Activity model

`App\Models\Activity extends Spatie\Activitylog\Models\Activity`:
- `project(): BelongsTo` relation.
- `scopeForProject($project)` — `where('project_id', $project->id)`.
- Registered via config `activitylog.activity_model`.

### 3. Project tagging

- Each loggable content model exposes `resolveActivityProjectId(): ?string`:
  - `Url`, `Domain`, `QrCode`, `Pixel` → own `project_id`.
  - `Project` → own `id`.
- A `SetsActivityProject` concern wires `getActivitylogOptions()`'s `tap`/`tapActivity` callback to set `activity->project_id` from the subject's `resolveActivityProjectId()`.
- Manual event logs set `project_id` explicitly (the project for membership/invitation; **null** for security events).
- Causer = authenticated user (Spatie sets automatically).

### 4. Model logging config (auto CRUD)

Each of `Url`, `Domain`, `QrCode`, `Pixel`, `Project`:
- `LogsActivity` trait + `SetsActivityProject`.
- `logOnly([...explicit attribute list...])` — never `*`, so `id`/`created_at`/`updated_at` and sensitive fields are excluded by construction.
- `logOnlyDirty()`, `dontSubmitEmptyLogs()`.
- `useLogName('<model>')` — `url`, `domain`, `qrcode`, `pixel`, `project` — drives UI filtering/icons.
- `setDescriptionForEvent()` → `created` | `updated` | `deleted`.

**Attribute lists (draft, refine in plan):**
- `Url`: `slug`, `target`, `type`, `password` (redacted), `expires_at`, `status`, targeting + AB JSON fields. **`Url.password` value is redacted** to a `••••` sentinel — the audit records that it changed, never the value.
- `Domain`: `domain`, redirect rule fields, `status`.
- `QrCode`: design/target fields.
- `Pixel`: name/type/config fields.
- `Project`: `name` + settings.

> **Decision note:** AB/targeting JSON is **included** in `Url`'s logged attributes (an earlier draft excluded it; reversed by user during review).

### 5. Manual event logging (non-CRUD)

A thin `ActivityLogger` helper (or `LogsProjectActivity` trait) keeps call sites to one-liners with consistent `log_name`, causer, `project_id`, description, and `properties`. No sensitive values ever placed in `properties`.

**Membership & invitations** (`project_id` = the project):
- Member added → `log_name: membership`; properties: target user, role.
- Member removed → `membership`; target user.
- Role changed → `membership`; old role → new role.
- Invitation sent → `invitation`; invitee email, role.
- Invitation accepted → `invitation`; who accepted.
- Invitation revoked/resent → `invitation`.

**Security events** (`project_id` = **null**, causer = the user; capture `request()->ip()` + user agent into `properties`):
- Login succeeded — **every** successful login.
- Password changed / reset.
- 2FA enabled / disabled.
- Passkey added / removed.

Call sites: `AuthController`, `PasswordResetController`, the membership endpoints, invitation handlers, 2FA + passkey controllers.

### 6. UI surfaces & access control

All three read the same `activity_log` table; they differ by scope and gate.

**A) Project activity feed** — `GET /project/{project}/activity`, name `app.<...>.activity.index`.
- Auth + `ProjectBindingMiddleware` (any member).
- `Activity::forProject($project)->with('causer','subject')->latest()->paginate()`.
- React `Pages/Activity/Index.tsx`: timeline (causer initials/avatar, human description, relative time, log-name badge), filter dropdown by `log_name`, Inertia pagination.
- **Security events (`project_id` null) never appear here.**

**B) Per-resource history** — on existing `Url`/`Domain`/`QrCode`/`Pixel` edit/detail pages.
- **Lazy Inertia partial prop** — history loads only when the panel opens (`$subject->activities()->latest()->limit(N)->get()`), avoiding payload bloat on every page load.
- React: collapsible "History" panel rendering attribute diffs (old → new). Access = same as viewing the resource.

**C) Global admin audit view** — `GET /admin/activity`, name `app.admin.activity.index`.
- Inside the existing `['auth', 'super_admin']` `/admin` route group; uses `AdminLayout`. No new gate (the `super_admin` middleware already gates the admin area).
- Shows **all** activity across all projects **plus** the null-project security events.
- Filters: project, `log_name`, causer, date range. Paginated.
- React `Pages/Admin/Activity/Index.tsx`, reusing the timeline component from A with extra filter controls.

### 7. Retention

Schedule `activitylog:clean` daily in `routes/console.php` (alongside existing `Schedule::` calls), keeping `activitylog.delete_records_older_than_days = 365`.

## Testing (PHPUnit, run via `ddev`)

- **Model logging:** create/update/delete on each content model writes one activity with correct `log_name`, `event`, `project_id`, and dirty-attribute diff; `Url.password` redacted; `created_at`/`updated_at` excluded; `dontSubmitEmptyLogs` honored.
- **Manual events:** member add/remove/role-change, invitation sent/accepted, and each security event write the expected activity with correct `project_id` (null for security) and properties (IP/UA present on security).
- **Authorization:** project feed shows only that project's rows and never security events; non-members blocked; admin view requires `super_admin`; per-resource lazy prop returns only that subject's activity.
- **Tagging:** `resolveActivityProjectId()` returns the correct project per model.

## Conventions

- All shell commands run via DDEV (`ddev composer`, `ddev php`, `ddev npm`).
- Ziggy `route()` calls use object params (`{ project: id }`).
- Frontend gate is `npm run build` (lint is broken in this repo).
- ULID keys throughout (`project_id` column must be ULID-compatible).
