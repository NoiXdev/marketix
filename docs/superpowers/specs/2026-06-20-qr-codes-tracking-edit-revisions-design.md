# QR Codes: Optional Tracking, Edit-Confirmation, and Revisions

**Date:** 2026-06-20
**Branch:** `new-features`
**Status:** Approved design — ready for implementation plan

## Summary

Three related enhancements to the QR code feature:

1. **Optional tracking** — let users generate a fully static (no-redirect, no-stats) QR for *every* meaningful type, not just the few currently offered.
2. **Confirm before a risky edit** — warn before saving an edit that would invalidate already-distributed (printed) codes.
3. **Revisions** — a dedicated version history that is both viewable and recoverable (restore a past version).

## Guiding principle

A QR's **scannable image** is what gets printed and distributed. The only thing that can be changed safely after distribution is a **dynamic QR's redirect target** — the printed image still encodes the same short link, only the destination behind it changes. Everything else (mode, type, style, slug/domain, or any static content) changes the encoded image itself, so already-printed codes break.

This principle drives Feature 2: the confirmation fires exactly when an edit would change the scannable image.

## Current state (as of this branch)

- `QrCode` model: ULID, `SoftDeletes`, Spatie `LogsActivity` (logs `name/type/is_dynamic/content/style`, last 50 shown in the Edit sidebar via `ActivityHistory`).
- A QR is **dynamic** (backed by a `Url` short link that records scans) or **static** (encodes the payload directly, no tracking). The `is_dynamic` flag already exists.
- The editor (`resources/js/Pages/QrCodes/partials/QrEditor.tsx`) already has a **Static / Dynamic toggle** and a **"Trackable / Not tracked"** badge per type.
- `buildQrContent()` (`resources/js/data/qrTypes.ts`) already produces correct static payloads for every type (`mailto:`, `tel:`, `wa.me`, raw URL, vCard, etc.).
- Backend `store`/`update` (`QrCodeController`) already skip all link/domain creation when `is_dynamic=false`; `QrCodeRequest` skips link/domain validation in that case.
- A reusable confirmation helper exists: `resources/js/lib/confirm.ts` → `confirmDelete()` (SweetAlert2, theme-aware, i18n via `t()`).
- i18n: custom `useTranslation()` hook (`resources/js/lib/i18n.ts`); per-area catalogs in `lang/{locale}/*.php`.

## Feature 1 — Optional tracking (static for every type)

The capability is mostly built; the only gap is **type categorization**.

### Changes

- **`resources/js/data/qrTypes.ts`:** recategorize `link, email, phone, application, file, whatsapp, crypto` from `category: 'dynamic'` to `category: 'both'`. Leave `text, wifi, event` as `'static'` (a redirect is meaningless for them) and `sms, vcard` as `'both'`. This makes every redirect-capable type also selectable when the toggle is set to **Static QR**.
- `STATIC_TYPES`/`DYNAMIC_TYPES` derive from `category`, so they update automatically. `qrTypeTrackable()` already returns `isDynamic && category !== 'static'`, which stays correct.
- `buildQrContent()` — no change (static payloads already implemented for all types).
- Backend — no migration, no controller change. `is_dynamic=false` already bypasses link/domain logic and validation.

### Verification

- For each newly-static-capable type, confirm `QrContentForm` renders its input fields in static mode (the form is keyed by `type`, independent of `is_dynamic`, so this is expected to already work — verify).
- Confirm the "Not tracked" badge shows for these types in static mode.

## Feature 2 — Confirm before a risky edit

The Edit page (`resources/js/Pages/QrCodes/Edit.tsx`) currently calls `put()` directly on submit. Intercept submit; when the edit would change the scannable image, show a confirmation dialog before sending.

### Risk rule

Compare the current form `data` against the original `qrCode` props.

**Risky → confirm:**
- mode switch (`is_dynamic` changed, either direction)
- `type` changed
- any `style` change
- `slug` or `domain_id` change on a dynamic QR (changes the encoded short link)
- any `content` change on a **static** QR

**Safe → save without prompting:**
- `name` change
- editing a **dynamic** QR's destination/`content` (redirect target only — the encoded image is unchanged)

### Changes

- Generalize `resources/js/lib/confirm.ts` to add `confirmAction({ title, text, confirmText })` (same SweetAlert2 styling/theme handling as `confirmDelete`; non-destructive default styling rather than red). Keep `confirmDelete` as-is.
- In `Edit.tsx`, wrap the submit handler: compute `isRiskyEdit(data, qrCode)`; if risky, `await confirmAction(...)` and only `put()` on confirm.
- Add i18n keys for the dialog (title/body/confirm) in `lang/{locale}/*.php` for `en/de/nl/fr`, routed through `t()`.

## Feature 3 — Revisions (dedicated table, viewable + recoverable)

### Data model

New migration `qr_code_versions`:

- `id` — ulid, primary key
- `qr_code_id` — ulid foreign key → `qr_codes.id`, cascade on delete
- `version` — unsigned integer, incrementing per QR code (1, 2, 3, …)
- `name` — string (snapshot)
- `type` — string (snapshot)
- `is_dynamic` — boolean (snapshot)
- `content` — json (snapshot)
- `style` — json (snapshot)
- `created_by` — ulid foreign key → `users.id`, nullable (causer)
- `created_at` — timestamp (no `updated_at`; versions are immutable)

New `QrCodeVersion` model (`HasUlids`, casts `content`/`style` to array). `QrCode hasMany versions`.

### When snapshots are written

- **v1** on QR creation (`QrCodeController@store`).
- A new snapshot after every successful `QrCodeController@update`, capturing the new current state.
- A new snapshot on restore (the restore action is itself recorded — see below).

Snapshot writing is centralized in a small helper (e.g. a `recordVersion()` method on the controller or a dedicated service) called inside the existing `DB::transaction` blocks so a version is never written for a failed save.

### Viewable

- The Edit sidebar's activity-log feed is **replaced** by a **Versions panel** (Spatie activity logging keeps running in the background for audit; it is simply no longer surfaced here).
- `QrCodeController@edit` passes the version list (e.g. last 50, newest first) instead of / in addition to the activity feed; the `history` Inertia prop is repurposed to versions.
- Each version row shows: version number, timestamp, who (`created_by`), type, dynamic/static, and a **Restore** button. The current (latest) version is marked and has no Restore button.

### Restore

- Route: `POST /project/{project}/qr-codes/{qrCode}/versions/{version}/restore`, name `app.project.qrcodes.versions.restore`.
- Controller action loads the target version (scoped to the project's QR), then applies its snapshot **through the same update path** used by `update()` — so the backing short link is created/deleted correctly when `is_dynamic` differs between the restored snapshot and the current state.
- Restore is **non-destructive**: it records a **new version** for the restored state. Nothing is deleted; history is append-only.
- A restore inherently changes the scannable image, so the **Restore button always shows the Feature 2 confirmation** before firing.

### Retention

Keep all versions (QR edits are infrequent; unbounded growth is not a practical concern).

## Decisions

1. `text/wifi/event` remain **static-only** (no dynamic variant).
2. Restore is **non-destructive** and appends a new version.
3. The Versions panel **replaces** the activity-log sidebar on the Edit page; activity logging still runs in the background.
4. **Slug/domain changes on a dynamic QR are risky** (they change the encoded short link) and trigger the confirmation.

## Out of scope

- Recovering soft-deleted QR codes (a separate "trash/restore deleted" view). Revisions cover undoing *edits*, not undoing *deletes*.
- Any change to how the QR image is rendered or to scan statistics tracking.

## Affected files (anticipated)

**Backend**
- `database/migrations/*_create_qr_code_versions_table.php` (new)
- `app/Models/QrCodeVersion.php` (new)
- `app/Models/QrCode.php` (add `versions` relation)
- `app/Http/Controllers/QrCodeController.php` (record versions in `store`/`update`; new `restore` action; `edit` passes versions)
- `routes/web.php` (restore route)

**Frontend**
- `resources/js/data/qrTypes.ts` (recategorize types)
- `resources/js/lib/confirm.ts` (add `confirmAction`)
- `resources/js/Pages/QrCodes/Edit.tsx` (risky-edit confirmation; render Versions panel; restore action)
- `resources/js/Components/` (new Versions panel component; may adapt `ActivityHistory`)
- `lang/{en,de,nl,fr}/*.php` (new i18n keys for confirmation + versions UI)

## Testing

- **Feature 1:** static QR can be created/saved for each newly-static type without a backing `Url`; no `Url` row is created; content stored verbatim.
- **Feature 2:** unit-test `isRiskyEdit` for each risky/safe case; confirm safe dynamic-target edit saves without a prompt.
- **Feature 3:** v1 written on create; update appends a version; restore applies the snapshot, handles `is_dynamic` transitions (Url create/delete), and appends a new version; versions scoped to project/QR (no cross-tenant access).
