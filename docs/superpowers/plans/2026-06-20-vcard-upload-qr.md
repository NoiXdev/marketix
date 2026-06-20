# vCard File Upload on QR Creation — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let users drop or upload a `.vcf` file when creating a vCard QR code; parse it client-side, fill the six existing fields, and preserve any unmapped vCard lines verbatim.

**Architecture:** A new pure TypeScript parser (`resources/js/lib/vcard.ts`) turns `.vcf` text into `ParsedVCard[]`. The vCard content form gains a drop zone that reads the file with `FileReader.readAsText()`, parses it, fills the form, and (for multi-contact files) shows a picker. Unmapped lines are kept as a newline-joined `content.extra` string and re-emitted before `END:VCARD` by both the frontend `buildVCard()` and the backend `QrCode::vCardString()`.

**Tech Stack:** React 19 + TypeScript, Inertia.js, Vitest (frontend tests), Laravel 13 + PHP 8.3, PHPUnit (backend tests). No new npm or composer dependencies.

## Global Constraints

- No new npm/composer dependencies — parser is hand-rolled.
- Extras are stored as a **newline-joined string** under `content.extra`, never an array, so `QrFormData.content` stays `Record<string, string>`.
- Run all PHP/Node tooling through DDEV: `ddev php`, `ddev composer`, `ddev npm`, `ddev exec`.
- Frontend gate is `ddev npm run build` (tsc + vite); `npm run lint` is known-broken — do not rely on it.
- Frontend unit tests run with `ddev npm run test:js` (Vitest).
- User-facing strings use the `useTranslation()` hook (`t('qr.vcard.x', { count })`); add keys to all four `lang/{en,de,nl,fr}/qr.php`.
- vCard generation stays **vCard 3.0**; mapped output lines are unchanged from today (`FN`, `ORG`, `TEL`, `EMAIL`, `URL`, `ADR:;;<address>;;;`).

---

## File Structure

- **Create** `resources/js/lib/vcard.ts` — pure parser + helpers (`parseVCards`, `mergeVCardIntoContent`, `extraSummary`).
- **Create** `resources/js/lib/vcard.test.ts` — Vitest unit tests for the above.
- **Modify** `resources/js/data/qrTypes.ts` — `buildVCard()` emits `content.extra` lines.
- **Create** `resources/js/data/qrTypes.test.ts` — Vitest test for `buildVCard` extras placement.
- **Modify** `app/Models/QrCode.php` — `vCardString()` emits `content.extra` lines.
- **Modify** `app/Http/Requests/QrCodeRequest.php` — accept `content.extra` string.
- **Create** `tests/Feature/QrCodeVCardTest.php` — backend tests for `vCardString` extras + request validation.
- **Modify** `lang/{en,de,nl,fr}/qr.php` — add `vcard` translation group.
- **Modify** `resources/js/Pages/QrCodes/partials/QrContentForm.tsx` — add `VCardImport` drop zone in the `vcard` case.

---

## Task 1: vCard parser and helpers

**Files:**
- Create: `resources/js/lib/vcard.ts`
- Test: `resources/js/lib/vcard.test.ts`

**Interfaces:**
- Consumes: nothing.
- Produces:
  - `interface ParsedVCard { displayName: string; fields: Record<string, string>; extra: string[] }`
  - `parseVCards(text: string): ParsedVCard[]`
  - `mergeVCardIntoContent(content: Record<string, string>, parsed: ParsedVCard): Record<string, string>`
  - `extraSummary(extra: string | undefined): { count: number; names: string[] }`

- [ ] **Step 1: Write the failing tests**

Create `resources/js/lib/vcard.test.ts`:

```ts
import { describe, expect, it } from 'vitest';
import { parseVCards, mergeVCardIntoContent, extraSummary } from './vcard';

const card = (body: string) => `BEGIN:VCARD\nVERSION:3.0\n${body}\nEND:VCARD`;

describe('parseVCards', () => {
  it('returns [] for garbage input', () => {
    expect(parseVCards('not a vcard')).toEqual([]);
    expect(parseVCards('')).toEqual([]);
  });

  it('maps the six known properties', () => {
    const [c] = parseVCards(card(
      'FN:Jane Doe\nORG:Acme\nTEL:+49 30 1\nEMAIL:jane@example.com\nURL:https://example.com\nADR:;;Main St;Berlin;;12345;Germany',
    ));
    expect(c.fields).toEqual({
      name: 'Jane Doe', org: 'Acme', phone: '+49 30 1',
      email: 'jane@example.com', url: 'https://example.com',
      address: 'Main St, Berlin, 12345, Germany',
    });
    expect(c.extra).toEqual([]);
    expect(c.displayName).toBe('Jane Doe');
  });

  it('keeps the first TEL/EMAIL and preserves the rest as extras', () => {
    const [c] = parseVCards(card('FN:Jane\nTEL:111\nTEL;TYPE=HOME:222\nEMAIL:a@x.io\nEMAIL:b@x.io'));
    expect(c.fields.phone).toBe('111');
    expect(c.fields.email).toBe('a@x.io');
    expect(c.extra).toEqual(['TEL;TYPE=HOME:222', 'EMAIL:b@x.io']);
  });

  it('preserves unmapped properties verbatim, including N and parameters', () => {
    const [c] = parseVCards(card('FN:Jane\nN:Doe;Jane;;;\nTITLE:CTO\nBDAY:1990-01-01'));
    expect(c.extra).toEqual(['N:Doe;Jane;;;', 'TITLE:CTO', 'BDAY:1990-01-01']);
  });

  it('derives the name from N when FN is absent', () => {
    const [c] = parseVCards(card('N:Doe;Jane;;;\nTEL:111'));
    expect(c.fields.name).toBe('Jane Doe');
    expect(c.displayName).toBe('Jane Doe');
  });

  it('unfolds folded continuation lines', () => {
    const [c] = parseVCards('BEGIN:VCARD\nVERSION:3.0\nFN:Jane\nNOTE:line one\n  line two\nEND:VCARD');
    expect(c.extra).toEqual(['NOTE:line one line two']);
  });

  it('parses multiple contacts', () => {
    const cards = parseVCards(`${card('FN:Alice')}\n${card('FN:Bob')}`);
    expect(cards.map(c => c.displayName)).toEqual(['Alice', 'Bob']);
  });

  it('falls back to email then a generated label for displayName', () => {
    const cards = parseVCards(`${card('EMAIL:only@x.io')}\n${card('TEL:999')}`);
    expect(cards[0].displayName).toBe('only@x.io');
    expect(cards[1].displayName).toBe('Contact 2');
  });

  it('unescapes common sequences in mapped field values', () => {
    const [c] = parseVCards(card('FN:Doe\\, Jane\nORG:A\\;B'));
    expect(c.fields.name).toBe('Doe, Jane');
    expect(c.fields.org).toBe('A;B');
  });
});

describe('mergeVCardIntoContent', () => {
  it('overwrites only fields the card provides and joins extras', () => {
    const content = { name: 'Old', org: 'OldOrg', phone: '', email: '', url: '', address: '' };
    const parsed = {
      displayName: 'New',
      fields: { name: 'New', org: '', phone: '123', email: '', url: '', address: '' },
      extra: ['TITLE:CTO', 'BDAY:1990'],
    };
    const out = mergeVCardIntoContent(content, parsed);
    expect(out.name).toBe('New');
    expect(out.org).toBe('OldOrg');
    expect(out.phone).toBe('123');
    expect(out.extra).toBe('TITLE:CTO\nBDAY:1990');
  });

  it('clears extras when the card has none', () => {
    const out = mergeVCardIntoContent({ extra: 'OLD:x' }, {
      displayName: 'X', fields: { name: 'X' }, extra: [],
    });
    expect(out.extra).toBe('');
  });
});

describe('extraSummary', () => {
  it('counts lines and lists property names', () => {
    expect(extraSummary('TITLE:CTO\nBDAY:1990\nTEL;TYPE=HOME:222'))
      .toEqual({ count: 3, names: ['TITLE', 'BDAY', 'TEL'] });
  });

  it('handles empty/undefined', () => {
    expect(extraSummary('')).toEqual({ count: 0, names: [] });
    expect(extraSummary(undefined)).toEqual({ count: 0, names: [] });
  });
});
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `ddev npm run test:js -- vcard`
Expected: FAIL — `Failed to resolve import "./vcard"` / functions not defined.

- [ ] **Step 3: Write the parser**

Create `resources/js/lib/vcard.ts`:

```ts
export interface ParsedVCard {
  displayName: string;
  fields: Record<string, string>;
  extra: string[];
}

// CRLF/CR/LF followed by a single space or tab is a folded continuation line.
const FOLD = /\r\n[ \t]|\n[ \t]|\r[ \t]/g;

function unescape(value: string): string {
  return value
    .replace(/\\n/gi, '\n')
    .replace(/\\,/g, ',')
    .replace(/\\;/g, ';')
    .replace(/\\\\/g, '\\');
}

// Property name = text before the first ':' , minus any ';' parameters, uppercased.
function propName(line: string): string {
  const colon = line.indexOf(':');
  const head = colon === -1 ? line : line.slice(0, colon);
  const semi = head.indexOf(';');
  return (semi === -1 ? head : head.slice(0, semi)).toUpperCase().trim();
}

function propValue(line: string): string {
  const colon = line.indexOf(':');
  return colon === -1 ? '' : line.slice(colon + 1);
}

// ADR is ';'-separated structured parts; join the non-empty ones readably.
function joinAddress(value: string): string {
  return value.split(';').map(s => s.trim()).filter(Boolean).join(', ');
}

// N is family;given;additional;prefix;suffix → "Given Family".
function nameFromN(value: string): string {
  const parts = value.split(';');
  const family = (parts[0] ?? '').trim();
  const given = (parts[1] ?? '').trim();
  return [given, family].filter(Boolean).join(' ');
}

export function parseVCards(text: string): ParsedVCard[] {
  const lines = text.replace(FOLD, '').split(/\r\n|\r|\n/);

  const cards: ParsedVCard[] = [];
  let cur: ParsedVCard | null = null;
  let nValue = '';

  for (const raw of lines) {
    const line = raw.trimEnd();
    if (!line) continue;
    const name = propName(line);

    if (name === 'BEGIN' && propValue(line).toUpperCase() === 'VCARD') {
      cur = { displayName: '', fields: { name: '', org: '', phone: '', email: '', url: '', address: '' }, extra: [] };
      nValue = '';
      continue;
    }
    if (name === 'END' && propValue(line).toUpperCase() === 'VCARD') {
      if (cur) {
        if (!cur.fields.name && nValue) cur.fields.name = nameFromN(nValue);
        cur.displayName = cur.fields.name || cur.fields.email || `Contact ${cards.length + 1}`;
        cards.push(cur);
      }
      cur = null;
      continue;
    }
    if (!cur || name === 'VERSION') continue;

    const value = propValue(line);
    switch (name) {
      case 'FN':    if (!cur.fields.name)    { cur.fields.name = unescape(value); continue; } break;
      case 'ORG':   if (!cur.fields.org)     { cur.fields.org = unescape(value); continue; } break;
      case 'TEL':   if (!cur.fields.phone)   { cur.fields.phone = unescape(value); continue; } break;
      case 'EMAIL': if (!cur.fields.email)   { cur.fields.email = unescape(value); continue; } break;
      case 'URL':   if (!cur.fields.url)     { cur.fields.url = unescape(value); continue; } break;
      case 'ADR':   if (!cur.fields.address) { cur.fields.address = joinAddress(value); continue; } break;
      case 'N':     if (!nValue) nValue = value; break; // captured for fallback, also preserved below
    }
    cur.extra.push(line);
  }

  return cards;
}

export function mergeVCardIntoContent(
  content: Record<string, string>,
  parsed: ParsedVCard,
): Record<string, string> {
  const next: Record<string, string> = { ...content };
  for (const [key, value] of Object.entries(parsed.fields)) {
    if (value) next[key] = value;
  }
  next.extra = parsed.extra.join('\n');
  return next;
}

export function extraSummary(extra: string | undefined): { count: number; names: string[] } {
  const lines = (extra ?? '').split('\n').map(l => l.trim()).filter(Boolean);
  return { count: lines.length, names: lines.map(propName) };
}
```

- [ ] **Step 4: Run the tests to verify they pass**

Run: `ddev npm run test:js -- vcard`
Expected: PASS — all `vcard.test.ts` cases green.

- [ ] **Step 5: Commit**

```bash
git add resources/js/lib/vcard.ts resources/js/lib/vcard.test.ts
git commit -m "feat(qr): hand-rolled vCard parser and merge/summary helpers"
```

---

## Task 2: `buildVCard()` emits preserved extras

**Files:**
- Modify: `resources/js/data/qrTypes.ts:41-52` (`buildVCard`)
- Test: `resources/js/data/qrTypes.test.ts`

**Interfaces:**
- Consumes: `buildQrContent(type, isDynamic, content, dynamicUrl?)` from `qrTypes.ts` (unchanged signature).
- Produces: `buildVCard` now appends `content.extra` lines (split on `\n`) immediately before `END:VCARD`.

- [ ] **Step 1: Write the failing test**

Create `resources/js/data/qrTypes.test.ts`:

```ts
import { describe, expect, it } from 'vitest';
import { buildQrContent } from './qrTypes';

describe('buildQrContent vcard', () => {
  it('emits mapped fields then extras then END', () => {
    const out = buildQrContent('vcard', false, {
      name: 'Jane', org: '', phone: '+49 30 1', email: '', url: '', address: '',
      extra: 'TITLE:CTO\nBDAY:1990-01-01',
    });
    expect(out).toBe(
      'BEGIN:VCARD\nVERSION:3.0\nFN:Jane\nTEL:+49 30 1\nTITLE:CTO\nBDAY:1990-01-01\nEND:VCARD',
    );
  });

  it('omits extras when absent', () => {
    const out = buildQrContent('vcard', false, { name: 'Jane' });
    expect(out).toBe('BEGIN:VCARD\nVERSION:3.0\nFN:Jane\nEND:VCARD');
  });
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `ddev npm run test:js -- qrTypes`
Expected: FAIL — output still lacks `TITLE:CTO` / `BDAY` lines.

- [ ] **Step 3: Update `buildVCard`**

In `resources/js/data/qrTypes.ts`, replace the `buildVCard` function (lines 41-52) with:

```ts
function buildVCard(c: Record<string, string>): string {
  const extra = (c.extra || '').split('\n').map(s => s.trim()).filter(Boolean);
  return [
    'BEGIN:VCARD', 'VERSION:3.0',
    c.name       ? `FN:${c.name}`          : '',
    c.org        ? `ORG:${c.org}`          : '',
    c.phone      ? `TEL:${c.phone}`        : '',
    c.email      ? `EMAIL:${c.email}`      : '',
    c.url        ? `URL:${c.url}`          : '',
    c.address    ? `ADR:;;${c.address};;;` : '',
    ...extra,
    'END:VCARD',
  ].filter(Boolean).join('\n');
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `ddev npm run test:js -- qrTypes`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add resources/js/data/qrTypes.ts resources/js/data/qrTypes.test.ts
git commit -m "feat(qr): include preserved vCard extras in buildVCard output"
```

---

## Task 3: Backend `vCardString()` extras + request validation

**Files:**
- Modify: `app/Models/QrCode.php:71-85` (`vCardString`)
- Modify: `app/Http/Requests/QrCodeRequest.php:23-38` (rules array)
- Test: `tests/Feature/QrCodeVCardTest.php`

**Interfaces:**
- Consumes: `QrCode` model (`content` array cast), `route('app.project.qrcodes.store', ...)`.
- Produces: `QrCode::vCardString()` appends `content.extra` lines (split on `\n`) before `END:VCARD`; `QrCodeRequest` accepts `content.extra` as a nullable string.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/QrCodeVCardTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\QrCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QrCodeVCardTest extends TestCase
{
    use RefreshDatabase;

    private array $style = [
        'foreground' => '#000000', 'background' => '#ffffff',
        'dot_style' => 'square', 'corner_square_style' => 'square',
        'corner_dot_style' => 'square', 'logo_type' => 'none',
        'logo_name' => '', 'logo_data' => '', 'logo_size' => 30,
    ];

    public function test_vcard_string_appends_extras_before_end(): void
    {
        $qr = new QrCode(['content' => [
            'name' => 'Jane Doe', 'phone' => '+49 30 1', 'email' => '',
            'org' => '', 'url' => '', 'address' => '',
            'extra' => "TITLE:CTO\nBDAY:1990-01-01\nTEL;TYPE=HOME:+49 30 2",
        ]]);

        $out = $qr->vCardString();

        $this->assertStringContainsString("FN:Jane Doe", $out);
        $this->assertStringContainsString("TITLE:CTO", $out);
        $this->assertStringContainsString("TEL;TYPE=HOME:+49 30 2", $out);
        $this->assertStringEndsWith("END:VCARD", $out);
        // extras come after the mapped fields
        $this->assertGreaterThan(strpos($out, 'FN:'), strpos($out, 'TITLE:CTO'));
    }

    public function test_vcard_string_without_extras_is_unchanged(): void
    {
        $qr = new QrCode(['content' => [
            'name' => 'Jane', 'phone' => '', 'email' => '', 'org' => '', 'url' => '', 'address' => '',
        ]]);

        $this->assertSame("BEGIN:VCARD\r\nVERSION:3.0\r\nFN:Jane\r\nEND:VCARD", $qr->vCardString());
    }

    public function test_store_accepts_a_vcard_extra_string(): void
    {
        $user = User::factory()->create();
        $project = Project::create(['name' => 'Acme']);
        $project->users()->attach($user->id, ['role' => 'member']);

        $this->actingAs($user)->postJson(
            route('app.project.qrcodes.store', ['project' => $project->id]),
            [
                'name' => 'Card', 'type' => 'vcard', 'is_dynamic' => false,
                'content' => ['name' => 'Jane', 'extra' => "TITLE:CTO"],
                'style' => $this->style,
            ],
            ['X-Inertia' => 'true'],
        )->assertSessionHasNoErrors();

        $qr = QrCode::firstOrFail();
        $this->assertSame('TITLE:CTO', $qr->content['extra']);
    }
}
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `ddev php artisan test --filter=QrCodeVCardTest`
Expected: FAIL — `vCardString()` omits extras; the `assertGreaterThan`/`assertStringContainsString` extras assertions fail. (The store test may pass already since unknown content keys aren't rejected, but the model tests fail.)

- [ ] **Step 3: Update `vCardString()`**

In `app/Models/QrCode.php`, replace the `vCardString` method body (lines 71-85) with:

```php
    public function vCardString(): string
    {
        $c = $this->content;

        $extra = Collection::make(explode("\n", (string) ($c['extra'] ?? '')))
            ->map(fn (string $line) => trim($line))
            ->filter();

        return Collection::make([
            'BEGIN:VCARD', 'VERSION:3.0',
            ! empty($c['name']) ? 'FN:'.$c['name'] : null,
            ! empty($c['org']) ? 'ORG:'.$c['org'] : null,
            ! empty($c['phone']) ? 'TEL:'.$c['phone'] : null,
            ! empty($c['email']) ? 'EMAIL:'.$c['email'] : null,
            ! empty($c['url']) ? 'URL:'.$c['url'] : null,
            ! empty($c['address']) ? 'ADR:;;'.$c['address'].';;;' : null,
        ])
            ->filter()
            ->concat($extra)
            ->push('END:VCARD')
            ->implode("\r\n");
    }
```

- [ ] **Step 4: Add the validation rule**

In `app/Http/Requests/QrCodeRequest.php`, inside the `$rules` array (after the `'content' => ['required', 'array'],` line at line 27), add:

```php
            'content.extra' => ['nullable', 'string'],
```

- [ ] **Step 5: Run the tests to verify they pass**

Run: `ddev php artisan test --filter=QrCodeVCardTest`
Expected: PASS — all three tests green.

- [ ] **Step 6: Run the existing QR suite to confirm no regressions**

Run: `ddev php artisan test --filter=QrCode`
Expected: PASS — `QrCodeVersionTest`, `QrCodeBackingLinkTest`, `QrCodeVCardTest` all green.

- [ ] **Step 7: Commit**

```bash
git add app/Models/QrCode.php app/Http/Requests/QrCodeRequest.php tests/Feature/QrCodeVCardTest.php
git commit -m "feat(qr): emit and accept preserved vCard extras on the backend"
```

---

## Task 4: i18n strings for the vCard importer

**Files:**
- Modify: `lang/en/qr.php`, `lang/de/qr.php`, `lang/nl/qr.php`, `lang/fr/qr.php`

**Interfaces:**
- Consumes: nothing.
- Produces: `qr.vcard.{drop,error,pick,extras,clear}` keys in all four locales, consumed by Task 5 via `t('qr.vcard.x', { count, fields })`.

- [ ] **Step 1: Add the English keys**

In `lang/en/qr.php`, add this entry to the returned array (after the `'versions' => [ ... ],` block, before the closing `];`):

```php
    'vcard' => [
        'drop'   => 'Drop a .vcf file here, or click to upload',
        'error'  => "Couldn't read a contact from that file",
        'pick'   => 'This file has :count contacts — pick one',
        'extras' => 'Extra fields kept from file (:count): :fields',
        'clear'  => 'Clear extras',
    ],
```

- [ ] **Step 2: Add the German keys**

In `lang/de/qr.php`, add to the returned array:

```php
    'vcard' => [
        'drop'   => '.vcf-Datei hierher ziehen oder zum Hochladen klicken',
        'error'  => 'Aus dieser Datei konnte kein Kontakt gelesen werden',
        'pick'   => 'Diese Datei enthält :count Kontakte – bitte einen auswählen',
        'extras' => 'Aus der Datei übernommene Zusatzfelder (:count): :fields',
        'clear'  => 'Zusatzfelder entfernen',
    ],
```

- [ ] **Step 3: Add the Dutch keys**

In `lang/nl/qr.php`, add to the returned array:

```php
    'vcard' => [
        'drop'   => 'Sleep een .vcf-bestand hierheen of klik om te uploaden',
        'error'  => 'Kon geen contact uit dit bestand lezen',
        'pick'   => 'Dit bestand bevat :count contacten — kies er één',
        'extras' => 'Extra velden uit bestand bewaard (:count): :fields',
        'clear'  => 'Extra velden wissen',
    ],
```

- [ ] **Step 4: Add the French keys**

In `lang/fr/qr.php`, add to the returned array:

```php
    'vcard' => [
        'drop'   => 'Déposez un fichier .vcf ici, ou cliquez pour l’importer',
        'error'  => 'Impossible de lire un contact dans ce fichier',
        'pick'   => 'Ce fichier contient :count contacts — choisissez-en un',
        'extras' => 'Champs supplémentaires conservés du fichier (:count) : :fields',
        'clear'  => 'Effacer les extras',
    ],
```

- [ ] **Step 5: Verify the files parse**

Run: `ddev php -r "foreach (['en','de','nl','fr'] as \$l) { \$a = require \"lang/\$l/qr.php\"; assert(isset(\$a['vcard']['drop'])); } echo 'ok';"`
Expected: prints `ok` with no PHP parse errors.

- [ ] **Step 6: Commit**

```bash
git add lang/en/qr.php lang/de/qr.php lang/nl/qr.php lang/fr/qr.php
git commit -m "feat(qr): i18n strings for vCard file import"
```

---

## Task 5: vCard import drop zone in the content form

**Files:**
- Modify: `resources/js/Pages/QrCodes/partials/QrContentForm.tsx`

**Interfaces:**
- Consumes: `parseVCards`, `mergeVCardIntoContent`, `extraSummary`, `ParsedVCard` from `@/lib/vcard` (Task 1); `useTranslation` from `@/lib/i18n`; `qr.vcard.*` keys (Task 4).
- Produces: a `VCardImport` component rendered at the top of the `vcard` case; no exported API changes.

- [ ] **Step 1: Update the imports**

In `resources/js/Pages/QrCodes/partials/QrContentForm.tsx`, replace the top import (line 1) with:

```tsx
import { QrType } from '@/data/qrTypes';
import { useTranslation } from '@/lib/i18n';
import { ParsedVCard, extraSummary, mergeVCardIntoContent, parseVCards } from '@/lib/vcard';
import { Upload, X } from 'lucide-react';
import { useRef, useState } from 'react';
```

- [ ] **Step 2: Add the `VCardImport` component**

In the same file, add this component just above the `export default function QrContentForm` declaration (after the `Field` helper):

```tsx
function VCardImport({ content, onChange }: {
  content: Record<string, string>;
  onChange: (content: Record<string, string>) => void;
}) {
  const { t } = useTranslation();
  const fileRef = useRef<HTMLInputElement>(null);
  const [drag, setDrag] = useState(false);
  const [error, setError] = useState('');
  const [choices, setChoices] = useState<ParsedVCard[]>([]);

  function handleFiles(files: FileList | null) {
    const file = files?.[0];
    if (!file) return;
    setError('');
    const reader = new FileReader();
    reader.onload = () => {
      const cards = parseVCards(String(reader.result));
      if (cards.length === 0) { setError(t('qr.vcard.error')); setChoices([]); return; }
      if (cards.length === 1) { onChange(mergeVCardIntoContent(content, cards[0])); setChoices([]); return; }
      setChoices(cards);
    };
    reader.readAsText(file);
  }

  function pick(card: ParsedVCard) {
    onChange(mergeVCardIntoContent(content, card));
    setChoices([]);
  }

  const summary = extraSummary(content.extra);

  return (
    <div className="space-y-2">
      <div
        onDragOver={e => { e.preventDefault(); setDrag(true); }}
        onDragLeave={() => setDrag(false)}
        onDrop={e => { e.preventDefault(); setDrag(false); handleFiles(e.dataTransfer.files); }}
        onClick={() => fileRef.current?.click()}
        className={`flex cursor-pointer flex-col items-center gap-2 rounded-lg border-2 border-dashed py-6 text-slate-400 transition-colors ${
          drag
            ? 'border-indigo-400 text-indigo-500'
            : 'border-slate-300 hover:border-indigo-400 hover:text-indigo-500 dark:border-slate-700'
        }`}
      >
        <Upload className="h-5 w-5" />
        <span className="text-xs">{t('qr.vcard.drop')}</span>
      </div>
      <input ref={fileRef} type="file" accept=".vcf,text/vcard,text/x-vcard" className="hidden"
        onChange={e => handleFiles(e.target.files)} />

      {error && <p className="text-xs text-red-600">{error}</p>}

      {choices.length > 0 && (
        <div className="rounded-lg border border-slate-200 p-2 dark:border-slate-700">
          <p className="mb-2 text-xs text-slate-500 dark:text-slate-400">
            {t('qr.vcard.pick', { count: choices.length })}
          </p>
          <div className="space-y-1">
            {choices.map((c, i) => (
              <button key={i} type="button" onClick={() => pick(c)}
                className="block w-full rounded-md px-3 py-1.5 text-left text-sm text-slate-700 hover:bg-indigo-50 dark:text-slate-300 dark:hover:bg-indigo-900/20">
                {c.displayName}
              </button>
            ))}
          </div>
        </div>
      )}

      {summary.count > 0 && (
        <div className="flex items-center justify-between rounded-md bg-slate-50 px-3 py-2 text-xs dark:bg-slate-800">
          <span className="text-slate-500 dark:text-slate-400">
            {t('qr.vcard.extras', { count: summary.count, fields: summary.names.join(', ') })}
          </span>
          <button type="button" onClick={() => onChange({ ...content, extra: '' })}
            className="flex shrink-0 items-center gap-1 text-red-500 hover:text-red-700">
            <X className="h-3.5 w-3.5" /> {t('qr.vcard.clear')}
          </button>
        </div>
      )}
    </div>
  );
}
```

- [ ] **Step 3: Render `VCardImport` in the vcard case**

In `resources/js/Pages/QrCodes/partials/QrContentForm.tsx`, in the `case 'vcard':` block, add the importer as the first child of the wrapping `<div className="space-y-4">`:

```tsx
    case 'vcard':
      return (
        <div className="space-y-4">
          <VCardImport content={content} onChange={onChange} />
          <Field label="Full name"><input type="text" value={v('name')} onChange={e => set('name', e.target.value)} placeholder="Jane Doe" className={inp} /></Field>
          <Field label="Organisation"><input type="text" value={v('org')} onChange={e => set('org', e.target.value)} placeholder="Acme Corp" className={inp} /></Field>
          <Field label="Phone"><input type="tel" value={v('phone')} onChange={e => set('phone', e.target.value)} placeholder="+49 123 456789" className={inp} /></Field>
          <Field label="Email"><input type="email" value={v('email')} onChange={e => set('email', e.target.value)} placeholder="jane@example.com" className={inp} /></Field>
          <Field label="Website"><input type="url" value={v('url')} onChange={e => set('url', e.target.value)} placeholder="https://example.com" className={inp} /></Field>
          <Field label="Address"><input type="text" value={v('address')} onChange={e => set('address', e.target.value)} placeholder="123 Main St, Berlin" className={inp} /></Field>
        </div>
      );
```

- [ ] **Step 4: Typecheck and build**

Run: `ddev npm run build`
Expected: PASS — tsc reports no type errors and Vite builds.

- [ ] **Step 5: Manual smoke test**

Run `ddev composer run dev` (or ensure Vite is running), then:
1. Go to a project → QR Codes → Create.
2. Choose **Static QR** → **vCard** type, Content tab.
3. Drag a single-contact `.vcf` onto the drop zone → the six fields fill; if it has exotic fields, the "Extra fields kept from file" summary appears.
4. Click **Clear extras** → summary disappears; fields remain.
5. Drop a multi-contact `.vcf` → a picker lists the contacts; selecting one fills the form.
6. Drop a non-vCard file → the error message shows.
7. Save, then visit the QR's short link / download → the `.vcf` includes the preserved extra lines.

Sample multi-contact `.vcf` for the smoke test:

```
BEGIN:VCARD
VERSION:3.0
FN:Alice Smith
ORG:Acme
TEL:+49 30 111
EMAIL:alice@example.com
TITLE:CEO
BDAY:1985-03-02
END:VCARD
BEGIN:VCARD
VERSION:3.0
FN:Bob Jones
TEL:+49 30 222
END:VCARD
```

- [ ] **Step 6: Commit**

```bash
git add resources/js/Pages/QrCodes/partials/QrContentForm.tsx
git commit -m "feat(qr): drag-and-drop vCard import in the QR content form"
```

---

## Self-Review

**1. Spec coverage:**
- Parse-and-fill + preserve extras → Tasks 1, 2, 3 (parser, frontend emit, backend emit).
- Multi-contact picker → Task 5 (`choices` state + picker UI), parser multi-contact → Task 1.
- Read-only extras summary with single Clear-all → Task 5 (`extraSummary` + Clear button), helper → Task 1.
- Hand-rolled parser, no dependency → Task 1.
- Drag-and-drop + click upload → Task 5.
- Generation appends extras (frontend + backend) → Tasks 2, 3.
- Validation accepts `content.extra` → Task 3.
- i18n en/de/nl/fr → Task 4.
- Tests both sides → Tasks 1, 2, 3.
- ADR joining, first TEL/EMAIL kept + rest preserved, N fallback, unfolding → Task 1 tests.
No gaps.

**2. Placeholder scan:** No TBD/TODO/"handle edge cases"; every code step has full code.

**3. Type consistency:** `ParsedVCard` (`displayName`, `fields: Record<string,string>`, `extra: string[]`) is defined in Task 1 and consumed identically in Tasks 2 (via content), 5. `mergeVCardIntoContent` writes `content.extra` as a `\n`-joined string; `buildVCard` (Task 2) and `vCardString` (Task 3) both read it as a `\n`-joined string and split; `extraSummary` reads the same string. `content` stays `Record<string, string>` everywhere. Consistent.
