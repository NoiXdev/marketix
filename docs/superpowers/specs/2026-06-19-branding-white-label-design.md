# White-Label Branding — Design Spec

**Date:** 2026-06-19
**Branch:** `new-features`
**Status:** Approved design, pending implementation plan

## Summary

Allow **super admins** to white-label the application — replacing the hardcoded
"Marketix" brand (name, logo, favicon) with their own — through a single global
settings page at `/admin/branding`. Branding is instance-wide (one set of values
for the whole installation), not per-project.

## Goals

- Super admins can override: **app name** (text), **light-mode logo**,
  **dark-mode logo**, a **dedicated email/PDF logo**, and the **favicon**.
- Custom branding appears across the web UI, browser tab title, favicon, emails,
  and PDF report covers.
- When a field is unset, the app falls back to the current Marketix defaults
  (Lucide `Link2` icon + "Marketix" text, `/favicon.ico`).

## Non-Goals

- Per-project branding (this is a single global instance brand).
- Accent / theme color customization (explicitly out of scope for now).
- Changing the mail **sender name** — that already lives in `MailSettings.from_name`
  under Admin → Mailer and is not duplicated here.

## Existing infrastructure leveraged

- `User.super_admin` boolean + `EnsureSuperAdmin` middleware (alias `super_admin`).
- `/admin` route group already guarded by `['auth','super_admin']`.
- **Spatie Laravel Settings** already in use (`App\Settings\MailSettings` +
  `Admin/Mailer/Edit.tsx` + `Admin/MailerController`) — copied as the pattern here.
- `HandleInertiaRequests::share()` — global Inertia prop injection point.
- `AdminSidebar.tsx` — admin navigation.

## Brand touchpoints to replace (inventory)

User-facing occurrences of "Marketix" found in the codebase:

- **Web UI (React):** `Sidebar.tsx`, `AdminSidebar.tsx`, `GuestLayout.tsx`
  (desktop + mobile), `ProfileLayout.tsx`, `ChooseProject.tsx`.
- **Emails / PDF (Blade):** `project-invitation.blade.php`, `test.blade.php`,
  `reports/layout.blade.php`, and `TestMail.php` (subject line).
- **Head:** browser tab title (currently from build-time `VITE_APP_NAME`) and
  favicon (`app.blade.php`).

## Design

### 1. Data model & storage

`App\Settings\BrandingSettings` (Spatie, group `branding`):

```php
class BrandingSettings extends Settings {
    public ?string $app_name;        // null → "Marketix"
    public ?string $logo_light_path; // path on default disk
    public ?string $logo_dark_path;
    public ?string $logo_email_path; // used in emails / PDF only
    public ?string $favicon_path;

    public static function group(): string { return 'branding'; }

    // Convenience accessors for server-side (mail/PDF/head) consumers:
    public function appName(): string;      // value-or-"Marketix"
    public function emailLogoUrl(): ?string; // resolved URL or null
}
```

- Spatie settings migration `database/settings/..._create_branding_settings.php`
  seeds all five fields to `null`.
- Image files are stored on the **default filesystem disk**
  (`config('filesystems.default')`, typically S3 in production) under `branding/`,
  written with **`public` visibility**. On replace, the previous file is deleted.
- The DB stores only the **path**. Public URLs are derived at read time via
  `Storage::disk()->url($path)`, which works for both `local`/`public` and `s3`
  disks (no reliance on the storage symlink).

### 2. Backend — controller, request, routes

`App\Http\Controllers\Admin\BrandingController` (mirrors `Admin\MailerController`):

- `edit(BrandingSettings $settings)` → `inertia('Admin/Branding/Edit', [...])`.
  Returns `app_name` plus the **resolved public URL** of each image (or `null`)
  for previews — never raw storage paths.
- `update(BrandingSettingsRequest $request, BrandingSettings $settings)`:
  - saves `app_name`;
  - for each image field: store new upload (deleting the old file), leave
    untouched if no new file and no remove flag, or clear the path + delete the
    file when `remove_<field>` is set;
  - redirects back with a `success` flash.

`App\Http\Requests\BrandingSettingsRequest`:

- `app_name` — `nullable|string|max:255`
- `logo_light`, `logo_dark`, `logo_email` — `nullable|image|max:2048`
- `favicon` — `nullable|file|mimes:ico,png,svg,jpg|max:2048`
- `remove_logo_light`, `remove_logo_dark`, `remove_logo_email`,
  `remove_favicon` — `boolean`

Routes (inside the existing `['auth','super_admin']` `/admin` group):

```php
Route::get('admin/branding',  [BrandingController::class,'edit'])->name('app.admin.branding.edit');
Route::post('admin/branding', [BrandingController::class,'update'])->name('app.admin.branding.update');
```

POST (not PUT) is used because uploads are `multipart/form-data`.

### 3. Global sharing & frontend consumption

`HandleInertiaRequests::share()` adds:

```php
'branding' => [
    'appName'   => $b->app_name ?: 'Marketix',
    'logoLight' => $b->logo_light_path ? Storage::disk()->url($b->logo_light_path) : null,
    'logoDark'  => $b->logo_dark_path  ? Storage::disk()->url($b->logo_dark_path)  : null,
    'favicon'   => $b->favicon_path    ? Storage::disk()->url($b->favicon_path)    : null,
],
```

(The email logo is server-side only and is omitted from the shared prop.)

A new **`resources/js/Components/Brand.tsx`** is the single source of truth for
rendering the logo + name. It reads `branding` from shared props and:

- if logo(s) are set, renders **both** the light and dark logo `<img>` and toggles
  them with Tailwind: light = `block dark:hidden`, dark = `hidden dark:block`
  (falling back to the light logo when no dark variant is uploaded);
- otherwise falls back to the current `Link2` icon + `appName` text.

`Sidebar`, `AdminSidebar`, `GuestLayout`, `ProfileLayout`, and `ChooseProject`
are refactored to use `<Brand>` instead of hardcoded "Marketix" markup.

### 4. Browser tab title & favicon (Blade head)

These live in `resources/views/app.blade.php`, not React, so they are wired
server-side. `BrandingSettings` is resolved once in the root view (via a view
composer / `View::share` or directly in the template):

- **Title** — inject the resolved app name to drive the default `<title>` /
  Inertia title callback, replacing the build-time `VITE_APP_NAME` so the custom
  name shows without a frontend rebuild.
- **Favicon** — output the custom favicon URL when set, else fall back to the
  existing `/favicon.ico`.

### 5. Emails & PDF reports

These render server-side (Blade) and read `BrandingSettings` directly using the
convenience accessors:

- `project-invitation.blade.php`, `test.blade.php`, `reports/layout.blade.php`:
  render `<img>` with the **email logo** when set (`emailLogoUrl()`), else fall
  back to the app-name text (current behavior).
- `TestMail.php` subject uses `appName()`.

### 6. Admin frontend page & navigation

- **`resources/js/Pages/Admin/Branding/Edit.tsx`** — mirrors `Mailer/Edit.tsx`:
  a text input for the app name; for each image, a preview of the current asset +
  a file input + a "Remove" toggle. Submitted as `multipart/form-data` via Inertia
  with `forceFormData: true`.
- **`AdminSidebar.tsx`** — add a "Branding" nav item alongside "Mailer".

## Testing

Feature tests (using `Storage::fake()`):

- Non-super-admin receives **403** on both `edit` and `update`.
- Super admin can load the edit page (200, correct Inertia component + props).
- Updating `app_name` persists and is reflected in shared Inertia props.
- Uploading a logo stores a file on the faked disk and saves its path.
- `remove_<field>` clears the path and deletes the stored file.
- Invalid upload (non-image / oversized) is rejected with validation errors.

## Implementation file checklist

- `app/Settings/BrandingSettings.php` (new)
- `database/settings/..._create_branding_settings.php` (new)
- `config/settings.php` — register `BrandingSettings::class`
- `app/Http/Controllers/Admin/BrandingController.php` (new)
- `app/Http/Requests/BrandingSettingsRequest.php` (new)
- `routes/web.php` — two routes in the `/admin` group
- `app/Http/Middleware/HandleInertiaRequests.php` — share `branding`
- `resources/views/app.blade.php` — title + favicon wiring (+ view composer if used)
- `resources/js/Components/Brand.tsx` (new)
- `resources/js/Layouts/{GuestLayout,ProfileLayout}.tsx`,
  `resources/js/Components/{Sidebar,AdminSidebar}.tsx`,
  `resources/js/Pages/ChooseProject.tsx` — use `<Brand>`
- `resources/js/Pages/Admin/Branding/Edit.tsx` (new)
- Email/PDF Blade templates + `TestMail.php`
- Feature test(s) for `BrandingController`
