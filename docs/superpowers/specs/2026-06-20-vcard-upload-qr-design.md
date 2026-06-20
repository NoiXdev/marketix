# Upload / drag-and-drop vCard when creating a QR code

**Date:** 2026-06-20
**Branch:** `new-features`
**Status:** Approved design

## Problem

The QR editor already supports a **vCard** type with six fields (name, org, phone,
email, url, address). Users who already have a contact as a `.vcf` file must
re-type it by hand. We want them to drop (or click-to-upload) a `.vcf`, have it
parsed in the browser, and have the form pre-filled — while keeping any contact
data richer than our six fields so the saved contact stays complete.

## Approach (decided)

- **Blend, not replace.** Parse the `.vcf`, map known properties into the six
  existing fields, and **preserve every unmapped line verbatim** so the generated
  contact loses nothing.
- **Client-side parse-and-forget.** Read the file with `FileReader.readAsText()`,
  parse in TypeScript, populate form state. The file itself is never uploaded or
  stored — same pattern as the custom-logo upload in `QrStyleForm`.
- **Multi-contact files → picker.** A `.vcf` may hold several contacts; the user
  picks which one to use.
- **Extras are transparent.** Preserved lines are surfaced in a read-only summary
  with a single "Clear" action.
- **Hand-rolled parser**, no new npm dependency.

## Data model

**No migration.** Extras are stored inside the existing `content` JSON column
under a new key, as a **newline-joined string** so the frontend's
`Record<string, string>` content type stays intact (no type churn):

```
content.extra: string   // raw vCard property lines we did not map, one per line, e.g.
                        // "BDAY:1990-01-01\nTITLE:CTO\nTEL;TYPE=HOME:+49 30 0000"
```

The in-memory parser result still exposes extras as a `string[]`
(`ParsedVCard.extra`); they are joined with `\n` only when merged into
`content`, and split again on output. Version snapshots (`qr_code_versions`),
the form request, and persistence all already round-trip `content` as JSON, so
extras travel with the QR for free.

## Components

### 1. Parser — `resources/js/lib/vcard.ts` (new, pure, unit-tested)

```ts
export interface ParsedVCard {
  displayName: string;            // FN → N → email → "Contact N"  (for the picker)
  fields: Record<string, string>; // name, org, phone, email, url, address
  extra: string[];                // every other property line, verbatim
}

export function parseVCards(text: string): ParsedVCard[];
```

Behaviour:

- Split the input into cards on `BEGIN:VCARD` … `END:VCARD`.
- **Unfold** folded continuation lines (a line starting with a space or tab is a
  continuation of the previous line) before parsing.
- Map the **first** occurrence of each known property:
  - `FN` → `name` (fallback: derive from `N` when `FN` absent)
  - `ORG` → `org`
  - first `TEL` → `phone`
  - first `EMAIL` → `email`
  - `URL` → `url`
  - `ADR` → `address` — join the structured ADR parts into a single string,
    mirroring how the backend writes `ADR:;;<address>;;;`.
- **Everything else → `extra`, verbatim** — including *additional* `TEL`/`EMAIL`
  lines beyond the first, and parameters left intact (e.g. `TEL;TYPE=CELL:`).
- Drop `BEGIN`, `END`, and `VERSION` lines (they are regenerated on output).
- Property-name based, so vCard **2.1 / 3.0 / 4.0** all parse; unrecognised lines
  are simply preserved.
- Malformed / empty input returns an empty array.

### 2. UI — `resources/js/Pages/QrCodes/partials/QrContentForm.tsx` (vCard case)

- A dashed-border drop zone + "click to upload" affordance at the top of the
  vCard section, reusing the `QrStyleForm` logo-upload styling.
  `accept=".vcf,text/vcard,text/x-vcard"`, with `onDragOver` / `onDrop` handlers
  in addition to the hidden `<input type="file">`.
- On file read → `parseVCards()`:
  - **0 contacts** → inline error ("Couldn't read a contact from this file").
  - **1 contact** → fill the six fields and set `content.extra`.
  - **>1 contacts** → render a small picker list of `displayName`s; on select,
    fill from the chosen contact.
- Filling **overwrites** the six fields with whatever the chosen card provides; a
  property absent from the card leaves the existing field value untouched.
- Read-only extras summary below the fields, shown only when `extra.length > 0`:
  *"Extra fields kept from file (3): TITLE, BDAY, TEL"* with a **Clear** button
  that empties `content.extra` in one action (no per-line removal).

### 3. Generation — append extras before `END:VCARD`

- **Frontend** `buildVCard()` in `resources/js/data/qrTypes.ts`: append
  `content.extra` lines immediately before `END:VCARD`.
- **Backend** `QrCode::vCardString()` (`app/Models/QrCode.php`): append
  `$c['extra'] ?? []` immediately before `END:VCARD`.

Both already filter empty values; extras slot in identically so the previewed QR
and the served `.vcf` stay byte-aligned.

### 4. Validation — `app/Http/Requests/QrCodeRequest.php`

Add:

```php
'content.extra' => ['nullable', 'string'],
```

vCard is already exempt from redirect-target validation
(`withValidator()` skips `type === 'vcard'`), so no other backend change.

### 5. i18n — `lang/{en,de,nl,fr}/qr.php`

New keys under a `vcard` group:

- upload label ("Import from vCard file")
- drag hint ("Drop a .vcf file here, or click to upload")
- parse error ("Couldn't read a contact from this file")
- multi-contact picker title ("This file has :count contacts — pick one")
- extras summary ("Extra fields kept from file (:count): :fields")
- clear button ("Clear extras")

## Data flow

```
.vcf file
  → FileReader.readAsText()
  → parseVCards(text): ParsedVCard[]
  → (0 → error | 1 → fill | N → picker → fill)
  → form state: content.{name,org,phone,email,url,address} + content.extra[]
  → normal QR submit (no separate upload)
  → buildVCard()/vCardString() emit fields + extras
```

## Testing

- **`resources/js/lib/vcard.test.ts`** (Vitest): line unfolding; single vs
  multi-contact; field mapping; `ADR` joining; extras preservation (including
  additional `TEL`/`EMAIL` and intact parameters); 2.1 / 3.0 / 4.0 samples;
  garbage/empty input → `[]`.
- **qrTypes test** (Vitest): `buildVCard` emits `content.extra` in the correct
  position (after mapped fields, before `END:VCARD`).
- **PHPUnit**: extend a QR test so `QrCode::vCardString()` includes
  `content.extra`; assert `QrCodeRequest` accepts an `extra` array.

## Out of scope

- Storing the original `.vcf` file.
- Editing preserved extras inline or removing them individually.
- Promoting exotic vCard properties (job title, birthday, second phone, photo)
  to first-class form fields.
- Exporting a contact back out as `.vcf` from the editor (the public redirect
  endpoint already serves `.vcf` downloads).
