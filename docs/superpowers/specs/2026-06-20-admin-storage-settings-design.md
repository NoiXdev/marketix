# Admin Storage Settings — Design Spec

**Date:** 2026-06-20
**Branch:** `new-features`
**Status:** Approved (pending implementation plan)

## Summary

Add an admin **Storage** settings page (`app.admin.storage.edit`) that lets an
admin choose where the app stores uploaded files and configure S3-compatible
credentials in the UI instead of `.env`. It mirrors the existing **Mailer**
settings page (`MailSettings` + `MailSettingsServiceProvider`) exactly.

Today, all app uploads (branding logos, favicons; later, generated PDFs) resolve
through `Storage::disk()` — i.e. the global default disk (`filesystems.default`,
currently `local`). The storage backend can only be changed via the
`FILESYSTEM_DISK` env var; there is no UI. This feature gives admins runtime
control over the backend, the same way the Mailer page gives runtime control
over mail transport.

## Goals

- Admin can choose the storage backend: **Local disk** or **S3-compatible**.
- "S3-compatible" covers AWS S3, Cloudflare R2, MinIO, DigitalOcean Spaces,
  Hetzner — via the existing `s3` disk (key/secret/region/bucket/endpoint/
  path-style).
- Credentials entered in the UI, persisted in DB settings, S3 secret encrypted
  and masked.
- A **Test connection** button verifies the configured disk before relying on it.

## Non-goals (YAGNI)

- No storage usage dashboard / quotas / retention policies.
- No file-management/browser UI.
- No FTP/SFTP drivers.
- **No file migration.** Switching the disk applies **going forward only**:
  existing files stay on the previous disk and may need re-uploading. The UI
  warns about this.

## Established pattern being followed

The Mailer feature is the direct precedent. The Storage feature is the filesystem
analog of every Mailer piece:

| Mailer | Storage (new) |
|---|---|
| `App\Settings\MailSettings` | `App\Settings\StorageSettings` |
| `database/settings/..._create_mail_settings.php` | `..._create_storage_settings.php` |
| `MailSettingsServiceProvider` (overrides `mail.default`) | `StorageSettingsServiceProvider` (overrides `filesystems.default`) |
| `Admin\MailerController` (edit/update/test) | `Admin\StorageController` (edit/update/test) |
| `MailSettingsRequest` | `StorageSettingsRequest` |
| `Pages/Admin/Mailer/Edit.tsx` | `Pages/Admin/Storage/Edit.tsx` |
| sidebar `Mailer` entry | sidebar `Storage` entry |

## Components

### 1. `App\Settings\StorageSettings` (group `storage`)

| Property | Type | Notes |
|---|---|---|
| `driver` | `string` | `'local'` or `'s3'` |
| `s3_key` | `string` | access key id |
| `s3_secret` | `string` | **encrypted**, masked in UI |
| `s3_region` | `string` | |
| `s3_bucket` | `string` | |
| `s3_endpoint` | `string` | blank = AWS; set for R2/MinIO/Spaces/Hetzner |
| `s3_use_path_style` | `bool` | path-style endpoint toggle |

- `group(): string` returns `'storage'`.
- `encrypted(): array` returns `['s3_secret']` (same as `postal_key`/`smtp_password`).

### 2. Settings migration

`database/settings/2026_06_20_000000_create_storage_settings.php` seeds defaults
from existing env so current config carries over:

- `storage.driver` → `'local'`
- `storage.s3_key` → `env('AWS_ACCESS_KEY_ID', '')`
- `storage.s3_secret` → `env('AWS_SECRET_ACCESS_KEY', '')`
- `storage.s3_region` → `env('AWS_DEFAULT_REGION', '')`
- `storage.s3_bucket` → `env('AWS_BUCKET', '')`
- `storage.s3_endpoint` → `env('AWS_ENDPOINT', '')`
- `storage.s3_use_path_style` → `(bool) env('AWS_USE_PATH_STYLE_ENDPOINT', false)`

Register `StorageSettings::class` in `config/settings.php`.

### 3. `StorageSettingsServiceProvider`

A near-copy of `MailSettingsServiceProvider`. Applies settings on `boot()`, on
`JobProcessing`, and on Octane `RequestReceived` (so long-lived queue/Octane
workers don't serve stale config). Forces a fresh `->refresh()` load each time.

Apply logic:

- **`driver = 'local'`** → leave `filesystems.default` unchanged (current behaviour).
- **`driver = 's3'`** → override the `s3` disk config from DB settings:
  ```php
  config([
      'filesystems.disks.s3.key' => $settings->s3_key,
      'filesystems.disks.s3.secret' => $settings->s3_secret,
      'filesystems.disks.s3.region' => $settings->s3_region,
      'filesystems.disks.s3.bucket' => $settings->s3_bucket,
      'filesystems.disks.s3.endpoint' => $settings->s3_endpoint ?: null,
      'filesystems.disks.s3.use_path_style_endpoint' => $settings->s3_use_path_style,
      'filesystems.default' => 's3',
  ]);
  ```

Wrapped in `try/catch (Throwable)` so a missing settings table / no-DB boot falls
back to `.env`/config defaults — same as `MailSettingsServiceProvider`.

Registered alongside `MailSettingsServiceProvider` (`bootstrap/providers.php`).

**Why overriding `filesystems.default` is sufficient:** every upload path resolves
through `Storage::disk()` with no argument — branding upload
(`BrandingController.php:49`, via `storePublicly`), delete
(`BrandingController.php:61`), and URL generation
(`BrandingSettings.php:52`, `Storage::disk()->url()`). Overriding the global
default makes all of them follow the admin's choice with **zero refactor**. This
is the exact analog of Mailer overriding `mail.default`.

### 4. `Admin\StorageController`

- **`edit(StorageSettings $settings)`** → renders `Admin/Storage/Edit` with all
  fields **except** `s3_secret`, plus `has_s3_secret` (bool) for the masked
  placeholder. Same shape as Mailer's `has_smtp_password`.
- **`update(StorageSettingsRequest $request, StorageSettings $settings)`** →
  saves validated data; overwrites `s3_secret` **only when a new value is
  supplied** (mask behaviour). Redirects back with a success flash.
- **`test(Request $request)`** → builds a throwaway disk from the **submitted**
  form values (so the admin can test before saving), writes a tiny temp file
  (`storage-test-{token}.txt`), reads it back, deletes it; returns a success or
  error flash with the exception message. On `Throwable`, returns the error flash.

### 5. `StorageSettingsRequest`

- `driver` → `required|in:local,s3`
- `s3_key` → `required_if:driver,s3`
- `s3_region` → `required_if:driver,s3`
- `s3_bucket` → `required_if:driver,s3`
- `s3_secret` → `nullable|string` (effectively required only if `driver=s3` and
  nothing stored yet — controller keeps existing value when blank)
- `s3_endpoint` → `nullable|url`
- `s3_use_path_style` → `boolean`

### 6. Routes

`routes/web.php`, in the admin group after the branding routes (line ~175):

```php
Route::get('/storage', [StorageController::class, 'edit'])->name('app.admin.storage.edit');
Route::put('/storage', [StorageController::class, 'update'])->name('app.admin.storage.update');
Route::post('/storage/test', [StorageController::class, 'test'])->name('app.admin.storage.test');
```

### 7. React page `resources/js/Pages/Admin/Storage/Edit.tsx`

Built on `AdminLayout` with `useForm`, matching Mailer/Branding:

- **Driver select** — `Local disk` / `S3-compatible`.
- **S3 fieldset** — shown only when `driver === 's3'`: key; secret (password
  input, placeholder `•••••• (unchanged)` when `has_s3_secret`); region; bucket;
  endpoint with helper text *"Leave blank for AWS; set for Cloudflare R2, MinIO,
  DigitalOcean Spaces, Hetzner"*; path-style checkbox.
- **Warning banner** — shown when the selected driver differs from the saved one:
  *"Existing files (logos, favicons) stay on the previous disk and may need
  re-uploading. New uploads will use the selected disk."*
- **Buttons** — `Save` (`put` to `storage.update`) and `Test connection` (`post`
  current form values to `storage.test`, shows the resulting flash). Reuses the
  existing success/error flash pattern.

All `route()` calls use the Ziggy object form: `route('app.admin.storage.update', { ... })` where params are needed (per project convention).

### 8. Sidebar

`resources/js/Components/AdminSidebar.tsx`, between Branding and Activity:

```ts
{ label: 'Storage', icon: HardDrive, routeName: 'app.admin.storage.edit' },
```

(`HardDrive` imported from `lucide-react`.)

## Testing

Feature tests under `tests/Feature/Admin/`, mirroring `MailSettingsTest` /
`MailerSettingsControllerTest`. Routes are hit via `route()` (never bare paths)
per the project's test-domain memory.

- **Access guard** — non-admin → 403; admin → 200 on `edit`.
- **`edit` render** — Inertia page receives the fields; `s3_secret` absent;
  `has_s3_secret` reflects stored state.
- **`update` persists** — driver + s3 fields saved to `StorageSettings`.
- **Secret masking** — blank `s3_secret` keeps the stored value; a new value
  overwrites it.
- **Validation** — `driver=s3` missing bucket/region/key fails; bad
  `s3_endpoint` URL fails.
- **Runtime application** — after saving `driver=s3`, the apply path sets
  `config('filesystems.default') === 's3'` and the `s3` disk config reflects the
  saved values.
- **Test endpoint** — success flash on a working (faked) disk; error flash when
  the write throws.

## Verification gates (project conventions)

- All php/composer/npm commands run via DDEV (`ddev composer test`, `ddev npm run build`).
- Frontend gate is `ddev npm run build` (lint is broken — ESLint 9 `--ignore-path`).

## Risks / notes

- Overriding the global `filesystems.default` could affect any code that assumes
  a private default disk. Current audit shows only branding uses `Storage::disk()`
  with no argument, and it wants public-style URLs — so the override is safe.
  Implementation should re-confirm no new private-default dependency exists.
- "Going forward only" means a disk switch can orphan existing assets. Accepted
  for v1; the UI warning makes it explicit. Migration can be a later feature.
