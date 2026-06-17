# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Stack

**Laravel 13 + PHP 8.3** backend with **React 19 + TypeScript** frontend connected via **Inertia.js**. Default database is MariaDB (DDEV); SQLite also supported via `.env`. No Filament — all UI is custom React.

## Commands

```bash
# Full project setup (install deps, .env, key, migrate, build)
composer run setup

# Development (Laravel server + queue + logs + Vite, all concurrent)
composer run dev

# Tests
composer run test

# Frontend only
npm run dev        # Vite dev server
npm run build      # TypeScript check + Vite production build
npm run lint       # ESLint with auto-fix
```

## Architecture

### Multi-tenant URL management system

**Users** belong to **Projects** (many-to-many via `project_user` pivot with JSON `permissions` and `active` flag). Each Project has **Domains** (custom domains with redirect rules) and **URLs** (short links with slug, target, type, password, expiry). **Statistics** track clicks per URL (IP, geo, device, referrer).

### Auth flow

- **Register** → user logged in → redirected to `/`, which forwards to their first project's dashboard
- **Login** → redirected to `/` on success (no email verification)
- **Root `/`** → authenticated users go to their first project's dashboard, or `abort(403)` if they belong to no project; guests go to login
- Password reset uses Laravel's built-in `Password::sendResetLink()` / `Password::reset()`
- All project routes protected by `auth` and `ProjectBindingMiddleware`

Controllers: `AuthController`, `PasswordResetController`, `DashboardController`

### Tenant system (Project)

`ProjectBindingMiddleware` resolves the `{project}` route parameter, checks `User::canAccessProject()`, then:
- Makes the `Project` model available via `$request->get('project')`
- Shares it to Inertia via `HandleInertiaRequests::share()` as `currentProject`
- Also shares `projects` (all user projects) for the tenant switcher

Route structure:
- `/auth/*` — guest-only auth pages
- `/project/{project}/*` — tenant-scoped app pages (auth + ProjectBindingMiddleware)

### Frontend layouts

- **`GuestLayout`** (`resources/js/Layouts/GuestLayout.tsx`) — two-column layout for auth pages; left panel is branding, right panel is the form card
- **`AppLayout`** (`resources/js/Layouts/AppLayout.tsx`) — full-height flex with `Sidebar` + scrollable `<main>`; reads `currentProject` from shared Inertia props

### Sidebar structure

`Sidebar` → navigation items + `ProjectSwitcher` + `UserMenu` (bottom). No top navigation bar.

`ProjectSwitcher` renders a dropdown from the shared `projects` prop and navigates to `/project/{id}/dashboard` on switch. `UserMenu` shows user initials avatar + logout.

### Inertia shared props (`HandleInertiaRequests`)

Every page receives:
- `auth.user` — authenticated user (or null)
- `projects` — all projects the user belongs to (for switcher)
- `currentProject` — the resolved project for the current route (null on auth pages)

### TypeScript types

`resources/js/types/index.d.ts` exports `User`, `Project`, and `PageProps<T>`.

### Enums

`UrlStatus` and `RedirectType` use a plain `label(): string` method — not the old Filament `HasLabel` interface.

### Observer pattern

`UrlObserver` auto-sets `user_id` on URL creation. Register new observers in `AppServiceProvider` via `#[ObservedBy]` attribute on the model.
