# QR Codes: Optional Tracking, Edit-Confirmation & Revisions — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let users generate static (untracked) QR codes for every type, confirm before edits that would invalidate already-printed codes, and view/restore a QR's saved revisions.

**Architecture:** Three slices. (1) Frontend type recategorization unlocks the already-supported static path for every redirect-capable type. (2) A pure `isRiskyEdit` helper gates the Edit form's submit behind a themed confirmation. (3) A new `qr_code_versions` table snapshots every saved state; restore replays a snapshot through the same persist path that `update` uses, so backing-link create/delete stays correct.

**Tech Stack:** Laravel 13 / PHP 8.3, React 19 + TypeScript + Inertia, Spatie Activitylog (kept for audit), SweetAlert2 (confirmations), Vitest (JS unit), PHPUnit (backend). Build via Vite + tsc.

## Global Constraints

- **Run all PHP/Node tooling through DDEV:** `ddev php`, `ddev composer`, `ddev npm`, `ddev exec`. Never bare `php`/`npm`.
- **Ziggy `route()` always takes object params:** `route('…', { project: id })`, never a bare value. Applies to JS calls.
- **Frontend gate is `ddev npm run build`** (tsc + vite). `npm run lint` is broken (ESLint 9 `--ignore-path`) — do not rely on it.
- **Tests address routes via `route()`**, not bare paths (host mismatch between `APP_DOMAIN` and `APP_URL`).
- **New i18n strings go through `t()`**; add catalogs under `lang/{en,de,nl,fr}/qr.php`. English is the fallback (deep-merged), so `en` is mandatory; the other three are translated copies.
- **Static types stay static-only:** `text`, `wifi`, `event` never become dynamic.
- **Commit after every task.** Branch: `new-features` (stay on it).

---

## File Structure

**Backend**
- `database/migrations/2026_06_20_000000_create_qr_code_versions_table.php` (new) — versions schema.
- `app/Models/QrCodeVersion.php` (new) — snapshot model.
- `app/Models/QrCode.php` (modify) — add `versions()` relation.
- `app/Http/Controllers/QrCodeController.php` (modify) — `persist()`/`recordVersion()` helpers; record on store/update; new `restore()`; `edit()` returns `versions`.
- `routes/web.php` (modify) — restore route.

**Frontend**
- `resources/js/data/qrTypes.ts` (modify) — recategorize types.
- `resources/js/lib/confirm.ts` (modify) — add `confirmAction()`.
- `resources/js/lib/qrRisk.ts` (new) + `resources/js/lib/qrRisk.test.ts` (new) — risk detection.
- `resources/js/Components/QrVersionsPanel.tsx` (new) — versions UI + restore.
- `resources/js/Pages/QrCodes/Edit.tsx` (modify) — risky-edit confirm; render versions panel.
- `lang/{en,de,nl,fr}/qr.php` (new) — confirmation + versions strings.

**Tests**
- `tests/Feature/QrCodeVersionTest.php` (new) — versioning + restore.
- `tests/Feature/QrCodeBackingLinkTest.php` (modify) — static-for-any-type case.
- `resources/js/lib/qrRisk.test.ts` (new) — risk matrix.

---

## Task 1: Static QR for every type (recategorization)

Backend already creates **no** backing `Url` when `is_dynamic=false` (proven by `test_creating_static_qr_creates_no_backing_url`) and imposes no type allow-list. The only gap is the frontend type grid hiding redirect-capable types in Static mode. Lock the backend contract with a test, then flip the categories.

**Files:**
- Modify: `tests/Feature/QrCodeBackingLinkTest.php`
- Modify: `resources/js/data/qrTypes.ts:21-27`

**Interfaces:**
- Produces: `QR_TYPES` where `link, email, phone, application, file, whatsapp, crypto` have `category: 'both'`; `STATIC_TYPES`/`DYNAMIC_TYPES`/`qrTypeTrackable` unchanged in signature.

- [ ] **Step 1: Write the failing test**

Add to `tests/Feature/QrCodeBackingLinkTest.php` (before the final `}`):

```php
    public function test_static_qr_for_a_redirect_type_creates_no_backing_url(): void
    {
        [$user, $project] = $this->tenant();

        foreach ([
            ['type' => 'email', 'content' => ['email' => 'hi@example.com']],
            ['type' => 'whatsapp', 'content' => ['phone' => '+4912345', 'message' => 'hi']],
        ] as $case) {
            $this->actingAs($user)->postJson(
                route('app.project.qrcodes.store', ['project' => $project->id]),
                $this->payload(['name' => 'Static '.$case['type'], 'is_dynamic' => false] + $case),
                ['X-Inertia' => 'true'],
            )->assertSessionHasNoErrors();
        }

        $this->assertDatabaseCount('urls', 0);
        $this->assertDatabaseHas('qr_codes', ['name' => 'Static email', 'is_dynamic' => false, 'url_id' => null]);
        $this->assertDatabaseHas('qr_codes', ['name' => 'Static whatsapp', 'is_dynamic' => false, 'url_id' => null]);
    }
```

- [ ] **Step 2: Run it (expected: PASS already — backend supports this)**

Run: `ddev php artisan test --filter=test_static_qr_for_a_redirect_type_creates_no_backing_url`
Expected: PASS. (This test guards the backend contract that the frontend change relies on. If it fails, stop — the backend assumption is wrong.)

- [ ] **Step 3: Recategorize the redirect-capable types**

In `resources/js/data/qrTypes.ts`, change the `category` of the seven dynamic-only entries (lines 21–27) from `'dynamic'` to `'both'`:

```ts
  // ── Dynamic (also usable as static / no-tracking) ────────────────────────
  { value: 'link',        label: 'Link',           category: 'both', icon: '🔗', defaultContent: { url: '' } },
  { value: 'email',       label: 'Email',          category: 'both', icon: '📧', defaultContent: { email: '', subject: '', body: '' } },
  { value: 'phone',       label: 'Phone',          category: 'both', icon: '📞', defaultContent: { phone: '' } },
  { value: 'application', label: 'Application',    category: 'both', icon: '📱', defaultContent: { url_ios: '', url_android: '', url_fallback: '' } },
  { value: 'file',        label: 'File',           category: 'both', icon: '📄', defaultContent: { file_url: '' } },
  { value: 'whatsapp',    label: 'WhatsApp',       category: 'both', icon: '🟢', defaultContent: { phone: '', message: '' } },
  { value: 'crypto',      label: 'Cryptocurrency', category: 'both', icon: '₿',  defaultContent: { currency: 'BTC', address: '', amount: '', label: '' } },
```

Leave `text`, `wifi`, `event` as `'static'` and `sms`, `vcard` as `'both'`. Do not touch the `QrType` union, `STATIC_TYPES`/`DYNAMIC_TYPES` derivations, `qrTypeTrackable`, or `buildQrContent` — they already handle this.

- [ ] **Step 4: Build to typecheck**

Run: `ddev npm run build`
Expected: completes with no TypeScript errors.

- [ ] **Step 5: Commit**

```bash
git add resources/js/data/qrTypes.ts tests/Feature/QrCodeBackingLinkTest.php
git commit -m "feat(qr): allow static (untracked) QR for every redirect type"
```

---

## Task 2: Versions table + model + relation

**Files:**
- Create: `database/migrations/2026_06_20_000000_create_qr_code_versions_table.php`
- Create: `app/Models/QrCodeVersion.php`
- Modify: `app/Models/QrCode.php`
- Create: `tests/Feature/QrCodeVersionTest.php`

**Interfaces:**
- Produces:
  - `QrCodeVersion` model with fillable `version, name, type, is_dynamic, content, style, domain_id, slug, created_by`; casts `is_dynamic`→bool, `content`/`style`→array; relations `qrCode()`, `creator()` (`belongsTo(User::class, 'created_by')`).
  - `QrCode::versions(): HasMany` → `QrCodeVersion`.
  - Table `qr_code_versions`: ulid `id`, ulid `qr_code_id` (cascade), unsigned int `version`, `name`, `type(30)`, bool `is_dynamic`, json `content`, json `style`, nullable ulid `domain_id`, nullable `slug`, nullable ulid `created_by`, timestamps; unique `(qr_code_id, version)`.

- [ ] **Step 1: Write the migration**

Create `database/migrations/2026_06_20_000000_create_qr_code_versions_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qr_code_versions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('qr_code_id')->constrained('qr_codes')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('name');
            $table->string('type', 30);
            $table->boolean('is_dynamic')->default(false);
            $table->json('content');
            $table->json('style');
            $table->foreignUlid('domain_id')->nullable();
            $table->string('slug')->nullable();
            $table->foreignUlid('created_by')->nullable();
            $table->timestamps();

            $table->unique(['qr_code_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qr_code_versions');
    }
};
```

- [ ] **Step 2: Write the model**

Create `app/Models/QrCodeVersion.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QrCodeVersion extends Model
{
    use HasUlids;

    protected $fillable = [
        'version',
        'name',
        'type',
        'is_dynamic',
        'content',
        'style',
        'domain_id',
        'slug',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_dynamic' => 'boolean',
            'content' => 'array',
            'style' => 'array',
        ];
    }

    public function qrCode(): BelongsTo
    {
        return $this->belongsTo(QrCode::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
```

- [ ] **Step 3: Add the relation to `QrCode`**

In `app/Models/QrCode.php`, add the import after the existing `BelongsTo` import (line 8):

```php
use Illuminate\Database\Eloquent\Relations\HasMany;
```

And add this method after `url()` (after line 59):

```php
    public function versions(): HasMany
    {
        return $this->hasMany(QrCodeVersion::class);
    }
```

- [ ] **Step 4: Write the failing test**

Create `tests/Feature/QrCodeVersionTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\QrCode;
use App\Models\QrCodeVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QrCodeVersionTest extends TestCase
{
    use RefreshDatabase;

    public function test_qr_code_has_many_versions(): void
    {
        $user = User::factory()->create();
        $project = Project::create(['name' => 'Acme']);
        $project->users()->attach($user->id, ['role' => 'member']);

        $qr = $project->qrCodes()->create([
            'name' => 'My QR', 'type' => 'text', 'is_dynamic' => false,
            'content' => ['text' => 'hi'], 'style' => ['foreground' => '#000'],
        ]);

        $version = $qr->versions()->create([
            'version' => 1, 'name' => 'My QR', 'type' => 'text', 'is_dynamic' => false,
            'content' => ['text' => 'hi'], 'style' => ['foreground' => '#000'],
            'created_by' => $user->id,
        ]);

        $this->assertTrue($qr->versions->contains($version));
        $this->assertSame($user->id, $version->creator->id);
        $this->assertIsArray($version->content);
    }
}
```

- [ ] **Step 5: Run the test**

Run: `ddev php artisan test --filter=QrCodeVersionTest`
Expected: PASS (migration runs under `RefreshDatabase`).

- [ ] **Step 6: Commit**

```bash
git add database/migrations/2026_06_20_000000_create_qr_code_versions_table.php app/Models/QrCodeVersion.php app/Models/QrCode.php tests/Feature/QrCodeVersionTest.php
git commit -m "feat(qr): add qr_code_versions table, model and relation"
```

---

## Task 3: Snapshot on create (v1) and on update

Refactor the `update` transaction body into a reusable `persist()`, add `recordVersion()`, and call both from `store` (v1) and `update` (vN+1).

**Files:**
- Modify: `app/Http/Controllers/QrCodeController.php`
- Modify: `tests/Feature/QrCodeVersionTest.php`

**Interfaces:**
- Consumes: `QrCode::versions()`, `QrCodeVersion` (Task 2).
- Produces:
  - `private function persist(Project $project, QrCode $model, array $data): void` — applies a full QR state (`name, type, is_dynamic, content, style`, plus `domain_id, slug` when dynamic) and syncs the backing `Url`.
  - `private function recordVersion(QrCode $model): void` — appends a snapshot row (next per-QR `version`, current model state, backing `domain_id`/`slug`, `created_by` = `Auth::id()`).

- [ ] **Step 1: Add imports**

In `app/Http/Controllers/QrCodeController.php`, add after the existing `use App\Models\Project;` (line 8):

```php
use App\Models\QrCode;
use Illuminate\Support\Facades\Auth;
```

- [ ] **Step 2: Snapshot v1 on store**

In `store()`, change the QR creation (lines 103–110) to capture the model and record v1, all inside the existing transaction:

```php
            $qr = $project->qrCodes()->create([
                'url_id' => $urlId,
                'name' => $data['name'],
                'type' => $data['type'],
                'is_dynamic' => $data['is_dynamic'],
                'content' => $data['content'],
                'style' => $data['style'],
            ]);

            $this->recordVersion($qr);
```

- [ ] **Step 3: Refactor `update` to use `persist()` + `recordVersion()`**

Replace the whole `update()` method body's transaction (lines 149–178) with:

```php
        DB::transaction(function () use ($project, $model, $data) {
            $this->persist($project, $model, $data);
            $this->recordVersion($model);
        });
```

- [ ] **Step 4: Add the `persist()` and `recordVersion()` helpers**

Add both methods in the `// ── Helpers ──` section (after `update()`, before `destroy()` or alongside `backingTarget`):

```php
    /**
     * Apply a full QR state to the model and sync its backing short link.
     * $data keys: name, type, is_dynamic, content, style, and (when dynamic) domain_id, slug.
     *
     * @param  array<string, mixed>  $data
     */
    private function persist(Project $project, QrCode $model, array $data): void
    {
        if ($data['is_dynamic']) {
            $attrs = [
                'domain_id' => $data['domain_id'],
                'slug' => $data['slug'],
                'url' => $this->backingTarget($project, $data),
                'type' => RedirectType::REDIRECT,
                'status' => UrlStatus::ACTIVATED,
            ];

            if ($model->url_id) {
                $model->url->update($attrs);
            } else {
                $model->url_id = $project->urls()->create($attrs)->id;
            }
        } elseif ($model->url_id) {
            // Switched dynamic → static: the backing link is no longer needed.
            $model->url->delete();
            $model->url_id = null;
        }

        $model->update([
            'url_id' => $model->url_id,
            'name' => $data['name'],
            'type' => $data['type'],
            'is_dynamic' => $data['is_dynamic'],
            'content' => $data['content'],
            'style' => $data['style'],
        ]);
    }

    /**
     * Append an immutable snapshot of the QR's current persisted state.
     */
    private function recordVersion(QrCode $model): void
    {
        $model->loadMissing('url');
        $next = (int) $model->versions()->max('version') + 1;

        $model->versions()->create([
            'version' => $next,
            'name' => $model->name,
            'type' => $model->type,
            'is_dynamic' => $model->is_dynamic,
            'content' => $model->content,
            'style' => $model->style,
            'domain_id' => $model->url?->domain_id,
            'slug' => $model->url?->slug,
            'created_by' => Auth::id(),
        ]);
    }
```

- [ ] **Step 5: Write the failing test**

Add to `tests/Feature/QrCodeVersionTest.php` (and add `use Illuminate\Support\Facades\Route;`? no — use `route()` helper; ensure `use App\Models\Domain;` at top):

Add `use App\Models\Domain;` to the imports, then add:

```php
    public function test_creating_a_qr_records_version_one(): void
    {
        $user = User::factory()->create();
        $project = Project::create(['name' => 'Acme']);
        $project->users()->attach($user->id, ['role' => 'member']);

        $this->actingAs($user)->postJson(
            route('app.project.qrcodes.store', ['project' => $project->id]),
            [
                'name' => 'My QR', 'type' => 'text', 'is_dynamic' => false,
                'content' => ['text' => 'hi'],
                'style' => [
                    'foreground' => '#000000', 'background' => '#ffffff',
                    'dot_style' => 'square', 'corner_square_style' => 'square',
                    'corner_dot_style' => 'square', 'logo_type' => 'none',
                    'logo_name' => '', 'logo_data' => '', 'logo_size' => 30,
                ],
            ],
            ['X-Inertia' => 'true'],
        )->assertSessionHasNoErrors();

        $qr = QrCode::firstOrFail();
        $this->assertDatabaseHas('qr_code_versions', [
            'qr_code_id' => $qr->id, 'version' => 1, 'name' => 'My QR', 'created_by' => $user->id,
        ]);
        $this->assertSame(1, $qr->versions()->count());
    }

    public function test_updating_a_qr_appends_a_version(): void
    {
        $user = User::factory()->create();
        $project = Project::create(['name' => 'Acme']);
        $project->users()->attach($user->id, ['role' => 'member']);

        $style = [
            'foreground' => '#000000', 'background' => '#ffffff',
            'dot_style' => 'square', 'corner_square_style' => 'square',
            'corner_dot_style' => 'square', 'logo_type' => 'none',
            'logo_name' => '', 'logo_data' => '', 'logo_size' => 30,
        ];

        $this->actingAs($user)->postJson(
            route('app.project.qrcodes.store', ['project' => $project->id]),
            ['name' => 'My QR', 'type' => 'text', 'is_dynamic' => false, 'content' => ['text' => 'hi'], 'style' => $style],
            ['X-Inertia' => 'true'],
        );
        $qr = QrCode::firstOrFail();

        $this->actingAs($user)->putJson(
            route('app.project.qrcodes.update', ['project' => $project->id, 'qrCode' => $qr->id]),
            ['name' => 'Renamed', 'type' => 'text', 'is_dynamic' => false, 'content' => ['text' => 'bye'], 'style' => $style],
            ['X-Inertia' => 'true'],
        )->assertSessionHasNoErrors();

        $this->assertSame(2, $qr->versions()->count());
        $this->assertDatabaseHas('qr_code_versions', ['qr_code_id' => $qr->id, 'version' => 2, 'name' => 'Renamed']);
    }
```

- [ ] **Step 6: Run versioning + regression tests**

Run: `ddev php artisan test --filter=QrCodeVersionTest && ddev php artisan test --filter=QrCodeBackingLinkTest`
Expected: all PASS (the `persist()` refactor must not change existing backing-link behavior).

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/QrCodeController.php tests/Feature/QrCodeVersionTest.php
git commit -m "feat(qr): snapshot a version on create and update"
```

---

## Task 4: Restore endpoint

**Files:**
- Modify: `routes/web.php:123` (add after the `update` route)
- Modify: `app/Http/Controllers/QrCodeController.php`
- Modify: `tests/Feature/QrCodeVersionTest.php`

**Interfaces:**
- Consumes: `persist()`, `recordVersion()` (Task 3); `QrCode::versions()`.
- Produces: route `app.project.qrcodes.versions.restore` (`POST /qr-codes/{qrCode}/versions/{version}/restore`) → `QrCodeController@restore`.

- [ ] **Step 1: Add the route**

In `routes/web.php`, add immediately after the `update` route (line 122):

```php
            Route::post('/qr-codes/{qrCode}/versions/{version}/restore', [QrCodeController::class, 'restore'])->name('app.project.qrcodes.versions.restore');
```

- [ ] **Step 2: Write the failing tests**

Add to `tests/Feature/QrCodeVersionTest.php`:

```php
    public function test_restoring_a_version_applies_its_snapshot_and_appends_a_version(): void
    {
        $user = User::factory()->create();
        $project = Project::create(['name' => 'Acme']);
        $project->users()->attach($user->id, ['role' => 'member']);

        $style = [
            'foreground' => '#000000', 'background' => '#ffffff',
            'dot_style' => 'square', 'corner_square_style' => 'square',
            'corner_dot_style' => 'square', 'logo_type' => 'none',
            'logo_name' => '', 'logo_data' => '', 'logo_size' => 30,
        ];

        $this->actingAs($user)->postJson(
            route('app.project.qrcodes.store', ['project' => $project->id]),
            ['name' => 'Original', 'type' => 'text', 'is_dynamic' => false, 'content' => ['text' => 'first'], 'style' => $style],
            ['X-Inertia' => 'true'],
        );
        $qr = QrCode::firstOrFail();

        $this->actingAs($user)->putJson(
            route('app.project.qrcodes.update', ['project' => $project->id, 'qrCode' => $qr->id]),
            ['name' => 'Changed', 'type' => 'text', 'is_dynamic' => false, 'content' => ['text' => 'second'], 'style' => $style],
            ['X-Inertia' => 'true'],
        );

        // Restore v1.
        $this->actingAs($user)->post(
            route('app.project.qrcodes.versions.restore', ['project' => $project->id, 'qrCode' => $qr->id, 'version' => 1]),
        )->assertRedirect(route('app.project.qrcodes.index'));

        $fresh = $qr->fresh();
        $this->assertSame('Original', $fresh->name);
        $this->assertSame(['text' => 'first'], $fresh->content);
        // Restore is non-destructive: v1, v2 (update), v3 (restore).
        $this->assertSame(3, $fresh->versions()->count());
    }

    public function test_restoring_a_dynamic_version_recreates_the_backing_link(): void
    {
        $user = User::factory()->create();
        $project = Project::create(['name' => 'Acme']);
        $project->users()->attach($user->id, ['role' => 'member']);
        $domain = Domain::create(['project_id' => $project->id, 'name' => 'links.test']);

        $style = [
            'foreground' => '#000000', 'background' => '#ffffff',
            'dot_style' => 'square', 'corner_square_style' => 'square',
            'corner_dot_style' => 'square', 'logo_type' => 'none',
            'logo_name' => '', 'logo_data' => '', 'logo_size' => 30,
        ];

        // v1: dynamic link.
        $this->actingAs($user)->postJson(
            route('app.project.qrcodes.store', ['project' => $project->id]),
            ['name' => 'Dyn', 'type' => 'link', 'is_dynamic' => true, 'domain_id' => $domain->id, 'slug' => 'promo', 'content' => ['url' => 'https://example.com/a'], 'style' => $style],
            ['X-Inertia' => 'true'],
        )->assertSessionHasNoErrors();
        $qr = QrCode::firstOrFail();

        // v2: switch to static.
        $this->actingAs($user)->putJson(
            route('app.project.qrcodes.update', ['project' => $project->id, 'qrCode' => $qr->id]),
            ['name' => 'Dyn', 'type' => 'text', 'is_dynamic' => false, 'content' => ['text' => 'x'], 'style' => $style],
            ['X-Inertia' => 'true'],
        )->assertSessionHasNoErrors();
        $this->assertNull($qr->fresh()->url_id);

        // Restore v1 (dynamic) — backing link must come back.
        $this->actingAs($user)->post(
            route('app.project.qrcodes.versions.restore', ['project' => $project->id, 'qrCode' => $qr->id, 'version' => 1]),
        )->assertSessionHasNoErrors();

        $this->assertNotNull($qr->fresh()->url_id);
        $this->assertDatabaseHas('urls', ['slug' => 'promo', 'url' => 'https://example.com/a']);
    }

    public function test_cannot_restore_another_projects_qr_version(): void
    {
        $user = User::factory()->create();
        $project = Project::create(['name' => 'Acme']);
        $project->users()->attach($user->id, ['role' => 'member']);

        $other = Project::create(['name' => 'Other']);
        $otherUser = User::factory()->create();
        $other->users()->attach($otherUser->id, ['role' => 'member']);
        $otherQr = $other->qrCodes()->create([
            'name' => 'Theirs', 'type' => 'text', 'is_dynamic' => false,
            'content' => ['text' => 'hi'], 'style' => ['foreground' => '#000'],
        ]);
        $otherQr->versions()->create([
            'version' => 1, 'name' => 'Theirs', 'type' => 'text', 'is_dynamic' => false,
            'content' => ['text' => 'hi'], 'style' => ['foreground' => '#000'],
        ]);

        // $user (Acme) trying to restore Other's QR → 404 (scoped via project).
        $this->actingAs($user)->post(
            route('app.project.qrcodes.versions.restore', ['project' => $project->id, 'qrCode' => $otherQr->id, 'version' => 1]),
        )->assertNotFound();
    }
```

- [ ] **Step 3: Run to verify they fail**

Run: `ddev php artisan test --filter=test_restoring_a_version_applies_its_snapshot_and_appends_a_version`
Expected: FAIL ("Method … restore does not exist" / 404 / route not defined).

- [ ] **Step 4: Implement `restore()`**

Add to `app/Http/Controllers/QrCodeController.php` (after `update()`):

```php
    public function restore(Request $request, string $qrCode, string $version)
    {
        $project = $request->get('project');
        $model = $project->qrCodes()->findOrFail($qrCode);
        $snapshot = $model->versions()->where('version', $version)->firstOrFail();

        $data = [
            'name' => $snapshot->name,
            'type' => $snapshot->type,
            'is_dynamic' => $snapshot->is_dynamic,
            'content' => $snapshot->content,
            'style' => $snapshot->style,
            'domain_id' => $snapshot->domain_id,
            'slug' => $snapshot->slug,
        ];

        // Restoring to dynamic must not collide with another link's slug on that domain.
        if ($snapshot->is_dynamic) {
            $taken = $project->urls()
                ->where('domain_id', $snapshot->domain_id)
                ->where('slug', $snapshot->slug)
                ->when($model->url_id, fn ($q) => $q->where('id', '!=', $model->url_id))
                ->exists();

            if ($taken) {
                return redirect()->back()
                    ->with('error', "That version's short link is already in use. Free up the slug and try again.");
            }
        }

        DB::transaction(function () use ($project, $model, $data) {
            $this->persist($project, $model, $data);
            $this->recordVersion($model);
        });

        return redirect()->route('app.project.qrcodes.index')
            ->with('success', 'QR code restored.');
    }
```

- [ ] **Step 5: Run the restore tests**

Run: `ddev php artisan test --filter=QrCodeVersionTest`
Expected: all PASS.

- [ ] **Step 6: Commit**

```bash
git add routes/web.php app/Http/Controllers/QrCodeController.php tests/Feature/QrCodeVersionTest.php
git commit -m "feat(qr): restore a previous QR version (non-destructive)"
```

---

## Task 5: Risk detection (`isRiskyEdit`) + `confirmAction`

**Files:**
- Modify: `resources/js/lib/confirm.ts`
- Create: `resources/js/lib/qrRisk.ts`
- Create: `resources/js/lib/qrRisk.test.ts`

**Interfaces:**
- Produces:
  - `confirmAction(opts?: { title?: string; text?: string; confirmText?: string }): Promise<boolean>` — themed, non-destructive (indigo) confirm.
  - `QrEditState` interface + `isRiskyEdit(original: QrEditState, next: QrEditState): boolean`.

- [ ] **Step 1: Add `confirmAction` to `confirm.ts`**

Append to `resources/js/lib/confirm.ts`:

```ts
type ConfirmActionOptions = {
    /** Dialog heading. Defaults to 'Are you sure?'. */
    title?: string;
    /** Body text. */
    text?: string;
    /** Label for the confirm button. Defaults to 'Confirm'. */
    confirmText?: string;
};

/**
 * Themed non-destructive confirmation (indigo confirm button). Resolves to
 * `true` when confirmed. Use for risky-but-not-deleting actions.
 */
export async function confirmAction(opts: ConfirmActionOptions = {}): Promise<boolean> {
    const isDark = resolveIsDark(getStoredTheme());

    const result = await Swal.fire({
        title: opts.title ?? 'Are you sure?',
        text: opts.text,
        icon: 'warning',
        iconColor: '#f59e0b',
        showCancelButton: true,
        confirmButtonText: opts.confirmText ?? 'Confirm',
        cancelButtonText: 'Cancel',
        focusCancel: true,
        reverseButtons: true,
        buttonsStyling: false,
        background: isDark ? '#1e293b' : '#ffffff',
        color: isDark ? '#e2e8f0' : '#0f172a',
        customClass: {
            popup: 'rounded-xl',
            confirmButton:
                'inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2',
            cancelButton:
                'mr-3 inline-flex items-center rounded-md border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-slate-400 focus:ring-offset-2 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800',
        },
    });

    return result.isConfirmed;
}
```

- [ ] **Step 2: Write the failing risk test**

Create `resources/js/lib/qrRisk.test.ts`:

```ts
import { describe, expect, it } from 'vitest';
import { isRiskyEdit, QrEditState } from './qrRisk';

const base: QrEditState = {
  type: 'link',
  is_dynamic: true,
  domain_id: 'dom1',
  slug: 'promo',
  content: { url: 'https://example.com/a' },
  style: { foreground: '#000', background: '#fff' } as QrEditState['style'],
};

describe('isRiskyEdit', () => {
  it('is safe when nothing relevant changed', () => {
    expect(isRiskyEdit(base, { ...base })).toBe(false);
  });

  it('is safe when only a dynamic redirect target changes', () => {
    expect(isRiskyEdit(base, { ...base, content: { url: 'https://example.com/b' } })).toBe(false);
  });

  it('is risky when a dynamic slug changes (encoded short link changes)', () => {
    expect(isRiskyEdit(base, { ...base, slug: 'promo-2' })).toBe(true);
  });

  it('is risky when a dynamic domain changes', () => {
    expect(isRiskyEdit(base, { ...base, domain_id: 'dom2' })).toBe(true);
  });

  it('is risky when the type changes', () => {
    expect(isRiskyEdit(base, { ...base, type: 'email' })).toBe(true);
  });

  it('is risky when the mode switches', () => {
    expect(isRiskyEdit(base, { ...base, is_dynamic: false })).toBe(true);
  });

  it('is risky when the style changes', () => {
    expect(isRiskyEdit(base, { ...base, style: { ...base.style, foreground: '#fff' } })).toBe(true);
  });

  it('is risky when a static content field changes', () => {
    const staticState: QrEditState = { ...base, is_dynamic: false, type: 'text', content: { text: 'hi' } };
    expect(isRiskyEdit(staticState, { ...staticState, content: { text: 'bye' } })).toBe(true);
  });

  it('is safe when a static content is unchanged', () => {
    const staticState: QrEditState = { ...base, is_dynamic: false, type: 'text', content: { text: 'hi' } };
    expect(isRiskyEdit(staticState, { ...staticState })).toBe(false);
  });
});
```

- [ ] **Step 3: Run it to verify it fails**

Run: `ddev npm run test:js -- qrRisk`
Expected: FAIL ("Failed to resolve import './qrRisk'").

- [ ] **Step 4: Implement `qrRisk.ts`**

Create `resources/js/lib/qrRisk.ts`:

```ts
import { QrStyle, QrType } from '@/data/qrTypes';

export interface QrEditState {
  type: QrType;
  is_dynamic: boolean;
  domain_id: string | '';
  slug: string;
  content: Record<string, string>;
  style: QrStyle;
}

/**
 * True when saving `next` would change the scannable image of `original`,
 * invalidating any already-printed codes. The only safe change to a dynamic
 * QR is its redirect target (content) — the encoded short link is unchanged.
 */
export function isRiskyEdit(original: QrEditState, next: QrEditState): boolean {
  if (original.is_dynamic !== next.is_dynamic) return true; // mode switch
  if (original.type !== next.type) return true;              // payload kind changes
  if (JSON.stringify(original.style) !== JSON.stringify(next.style)) return true; // re-render

  if (next.is_dynamic) {
    // Image encodes the short link; changing it (domain/slug) breaks printed codes.
    return original.domain_id !== next.domain_id || original.slug !== next.slug;
  }

  // Static: the image encodes the content directly.
  return JSON.stringify(original.content) !== JSON.stringify(next.content);
}
```

- [ ] **Step 5: Run tests + build**

Run: `ddev npm run test:js -- qrRisk && ddev npm run build`
Expected: tests PASS; build clean.

- [ ] **Step 6: Commit**

```bash
git add resources/js/lib/confirm.ts resources/js/lib/qrRisk.ts resources/js/lib/qrRisk.test.ts
git commit -m "feat(qr): add confirmAction and isRiskyEdit helpers"
```

---

## Task 6: i18n catalogs for QR confirmation + versions

**Files:**
- Create: `lang/en/qr.php`, `lang/de/qr.php`, `lang/nl/qr.php`, `lang/fr/qr.php`

**Interfaces:**
- Produces translation keys consumed in Tasks 7–8: `qr.edit.confirm.{title,text,button}`, `qr.versions.{title,empty,current,restore,by,dynamic,static}`, `qr.versions.restore_confirm.{title,text,button}`.

- [ ] **Step 1: English catalog (fallback — required)**

Create `lang/en/qr.php`:

```php
<?php

return [
    'edit' => [
        'confirm' => [
            'title'  => 'Save this change?',
            'text'   => 'This edit changes the QR code image. Codes you have already printed or shared will stop matching and must be regenerated.',
            'button' => 'Save anyway',
        ],
    ],

    'versions' => [
        'title'   => 'Version history',
        'empty'   => 'No versions yet.',
        'current' => 'Current',
        'restore' => 'Restore',
        'by'      => 'by :name',
        'dynamic' => 'Dynamic',
        'static'  => 'Static',
        'restore_confirm' => [
            'title'  => 'Restore this version?',
            'text'   => 'This replaces the current QR with the selected version and changes the QR image. Already-printed codes must be regenerated. Your current version is kept in history.',
            'button' => 'Restore',
        ],
    ],
];
```

- [ ] **Step 2: German catalog**

Create `lang/de/qr.php`:

```php
<?php

return [
    'edit' => [
        'confirm' => [
            'title'  => 'Änderung speichern?',
            'text'   => 'Diese Änderung verändert das QR-Code-Bild. Bereits gedruckte oder geteilte Codes passen dann nicht mehr und müssen neu erstellt werden.',
            'button' => 'Trotzdem speichern',
        ],
    ],

    'versions' => [
        'title'   => 'Versionsverlauf',
        'empty'   => 'Noch keine Versionen.',
        'current' => 'Aktuell',
        'restore' => 'Wiederherstellen',
        'by'      => 'von :name',
        'dynamic' => 'Dynamisch',
        'static'  => 'Statisch',
        'restore_confirm' => [
            'title'  => 'Diese Version wiederherstellen?',
            'text'   => 'Dadurch wird der aktuelle QR-Code durch die gewählte Version ersetzt und das QR-Bild ändert sich. Bereits gedruckte Codes müssen neu erstellt werden. Deine aktuelle Version bleibt im Verlauf erhalten.',
            'button' => 'Wiederherstellen',
        ],
    ],
];
```

- [ ] **Step 3: Dutch catalog**

Create `lang/nl/qr.php`:

```php
<?php

return [
    'edit' => [
        'confirm' => [
            'title'  => 'Wijziging opslaan?',
            'text'   => 'Deze wijziging verandert de QR-code-afbeelding. Codes die je al hebt afgedrukt of gedeeld komen niet meer overeen en moeten opnieuw worden gemaakt.',
            'button' => 'Toch opslaan',
        ],
    ],

    'versions' => [
        'title'   => 'Versiegeschiedenis',
        'empty'   => 'Nog geen versies.',
        'current' => 'Huidig',
        'restore' => 'Herstellen',
        'by'      => 'door :name',
        'dynamic' => 'Dynamisch',
        'static'  => 'Statisch',
        'restore_confirm' => [
            'title'  => 'Deze versie herstellen?',
            'text'   => 'Hiermee vervang je de huidige QR door de geselecteerde versie en verandert de QR-afbeelding. Reeds afgedrukte codes moeten opnieuw worden gemaakt. Je huidige versie blijft in de geschiedenis bewaard.',
            'button' => 'Herstellen',
        ],
    ],
];
```

- [ ] **Step 4: French catalog**

Create `lang/fr/qr.php`:

```php
<?php

return [
    'edit' => [
        'confirm' => [
            'title'  => 'Enregistrer cette modification ?',
            'text'   => "Cette modification change l'image du QR code. Les codes déjà imprimés ou partagés ne correspondront plus et devront être régénérés.",
            'button' => 'Enregistrer quand même',
        ],
    ],

    'versions' => [
        'title'   => 'Historique des versions',
        'empty'   => 'Aucune version pour le moment.',
        'current' => 'Actuelle',
        'restore' => 'Restaurer',
        'by'      => 'par :name',
        'dynamic' => 'Dynamique',
        'static'  => 'Statique',
        'restore_confirm' => [
            'title'  => 'Restaurer cette version ?',
            'text'   => "Cela remplace le QR actuel par la version sélectionnée et change l'image du QR. Les codes déjà imprimés doivent être régénérés. Votre version actuelle est conservée dans l'historique.",
            'button' => 'Restaurer',
        ],
    ],
];
```

- [ ] **Step 5: Commit**

```bash
git add lang/en/qr.php lang/de/qr.php lang/nl/qr.php lang/fr/qr.php
git commit -m "feat(qr): i18n strings for edit confirmation and version history"
```

---

## Task 7: Versions panel component

**Files:**
- Create: `resources/js/Components/QrVersionsPanel.tsx`

**Interfaces:**
- Consumes: `confirmAction` (Task 5), `useTranslation` (`@/lib/i18n`), route `app.project.qrcodes.versions.restore` (Task 4), keys from Task 6.
- Produces: `export interface QrVersionEntry { version: number; name: string; type: string; is_dynamic: boolean; created_at: string; created_by_name: string | null }` and `default function QrVersionsPanel({ qrId, versions }: { qrId: string; versions?: QrVersionEntry[] })`.

- [ ] **Step 1: Create the component**

Create `resources/js/Components/QrVersionsPanel.tsx`:

```tsx
import { confirmAction } from '@/lib/confirm';
import { useTranslation } from '@/lib/i18n';
import { PageProps } from '@/types';
import { router, usePage } from '@inertiajs/react';
import { ChevronDown, ChevronRight, RotateCcw } from 'lucide-react';
import { useState } from 'react';

export interface QrVersionEntry {
  version: number;
  name: string;
  type: string;
  is_dynamic: boolean;
  created_at: string;
  created_by_name: string | null;
}

export default function QrVersionsPanel({ qrId, versions }: { qrId: string; versions?: QrVersionEntry[] }) {
  const { t } = useTranslation();
  const { project } = usePage<PageProps>().props;
  const [open, setOpen] = useState(false);

  function toggle() {
    const next = !open;
    setOpen(next);
    if (next && !versions) router.reload({ only: ['versions'] });
  }

  async function restore(version: number) {
    const ok = await confirmAction({
      title: t('qr.versions.restore_confirm.title'),
      text: t('qr.versions.restore_confirm.text'),
      confirmText: t('qr.versions.restore_confirm.button'),
    });
    if (!ok) return;
    router.post(route('app.project.qrcodes.versions.restore', { project: project!.id, qrCode: qrId, version }));
  }

  return (
    <div className="rounded-xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
      <button type="button" onClick={toggle}
        className="flex w-full items-center gap-2 px-5 py-3 text-left text-sm font-semibold text-slate-900 dark:text-white">
        {open ? <ChevronDown className="h-4 w-4" /> : <ChevronRight className="h-4 w-4" />}
        {t('qr.versions.title')}
      </button>
      {open && (
        <div className="border-t border-slate-100 px-5 py-3 dark:border-slate-800">
          {!versions ? (
            <p className="text-sm text-slate-400">Loading…</p>
          ) : versions.length === 0 ? (
            <p className="text-sm text-slate-400">{t('qr.versions.empty')}</p>
          ) : (
            <ul className="divide-y divide-slate-100 dark:divide-slate-800">
              {versions.map((v, i) => (
                <li key={v.version} className="flex items-center justify-between py-2">
                  <div>
                    <p className="text-sm text-slate-700 dark:text-slate-200">
                      <span className="font-medium">v{v.version}</span> ·{' '}
                      {v.is_dynamic ? t('qr.versions.dynamic') : t('qr.versions.static')}
                      {i === 0 && (
                        <span className="ml-2 rounded-full bg-indigo-100 px-1.5 py-0.5 text-[10px] font-medium text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300">
                          {t('qr.versions.current')}
                        </span>
                      )}
                    </p>
                    <p className="text-xs text-slate-400">
                      {t('qr.versions.by', { name: v.created_by_name ?? 'System' })} ·{' '}
                      {new Date(v.created_at).toLocaleString()}
                    </p>
                  </div>
                  {i !== 0 && (
                    <button type="button" onClick={() => restore(v.version)}
                      className="inline-flex items-center gap-1 rounded-md border border-slate-200 px-2 py-1 text-xs text-slate-600 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">
                      <RotateCcw className="h-3.5 w-3.5" /> {t('qr.versions.restore')}
                    </button>
                  )}
                </li>
              ))}
            </ul>
          )}
        </div>
      )}
    </div>
  );
}
```

- [ ] **Step 2: Build to typecheck**

Run: `ddev npm run build`
Expected: clean (the component is not yet imported anywhere — this only checks it compiles).

- [ ] **Step 3: Commit**

```bash
git add resources/js/Components/QrVersionsPanel.tsx
git commit -m "feat(qr): version history panel with restore"
```

---

## Task 8: Wire it into the Edit page (confirm-on-edit + versions, replacing activity feed)

**Files:**
- Modify: `app/Http/Controllers/QrCodeController.php` (`edit()` — return `versions` instead of `history`)
- Modify: `resources/js/Pages/QrCodes/Edit.tsx`
- Modify: `tests/Feature/QrCodeVersionTest.php`

**Interfaces:**
- Consumes: `QrVersionsPanel` + `QrVersionEntry` (Task 7), `isRiskyEdit`/`QrEditState` (Task 5), `confirmAction` (Task 5), `useTranslation`.
- Produces: `edit()` Inertia `versions` prop: `Array<{ version, name, type, is_dynamic, created_at, created_by_name }>`.

- [ ] **Step 1: Change `edit()` to return versions**

In `app/Http/Controllers/QrCodeController.php`, replace the `'history' => Inertia::optional(...)` block in `edit()` (lines 137–139) with:

```php
            'versions' => Inertia::optional(
                fn () => $model->versions()->with('creator')->orderByDesc('version')->limit(50)->get()->map(fn ($v) => [
                    'version' => $v->version,
                    'name' => $v->name,
                    'type' => $v->type,
                    'is_dynamic' => $v->is_dynamic,
                    'created_at' => $v->created_at->toISOString(),
                    'created_by_name' => $v->creator?->name,
                ])
            ),
```

(The `LogsActivity` trait stays on the model — audit logging continues; it is just no longer surfaced in the UI. The unused `use Inertia\Inertia;` import is still needed for `Inertia::optional`.)

- [ ] **Step 2: Write the failing backend test for the versions prop**

Add to `tests/Feature/QrCodeVersionTest.php`:

```php
    public function test_edit_page_exposes_versions(): void
    {
        $user = User::factory()->create();
        $project = Project::create(['name' => 'Acme']);
        $project->users()->attach($user->id, ['role' => 'member']);

        $style = [
            'foreground' => '#000000', 'background' => '#ffffff',
            'dot_style' => 'square', 'corner_square_style' => 'square',
            'corner_dot_style' => 'square', 'logo_type' => 'none',
            'logo_name' => '', 'logo_data' => '', 'logo_size' => 30,
        ];
        $this->actingAs($user)->postJson(
            route('app.project.qrcodes.store', ['project' => $project->id]),
            ['name' => 'My QR', 'type' => 'text', 'is_dynamic' => false, 'content' => ['text' => 'hi'], 'style' => $style],
            ['X-Inertia' => 'true'],
        );
        $qr = QrCode::firstOrFail();

        $this->actingAs($user)->get(
            route('app.project.qrcodes.edit', ['project' => $project->id, 'qrCode' => $qr->id]),
            ['X-Inertia' => 'true', 'X-Inertia-Partial-Data' => 'versions', 'X-Inertia-Partial-Component' => 'QrCodes/Edit'],
        )->assertOk()->assertJsonPath('props.versions.0.version', 1);
    }
```

- [ ] **Step 3: Run it to verify it fails**

Run: `ddev php artisan test --filter=test_edit_page_exposes_versions`
Expected: FAIL (prop is `history`, not `versions`) — then PASS once Step 1 is in. (If Step 1 is already applied, this should PASS now; run it to confirm.)

- [ ] **Step 4: Rewrite `Edit.tsx`**

Replace the entire contents of `resources/js/Pages/QrCodes/Edit.tsx` with:

```tsx
import QrVersionsPanel, { QrVersionEntry } from '@/Components/QrVersionsPanel';
import AppLayout from '@/Layouts/AppLayout';
import { QrStyle, QrType } from '@/data/qrTypes';
import { confirmAction } from '@/lib/confirm';
import { useTranslation } from '@/lib/i18n';
import { isRiskyEdit, QrEditState } from '@/lib/qrRisk';
import { PageProps } from '@/types';
import { Link, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { FormEvent } from 'react';
import QrEditor, { QrFormData } from './partials/QrEditor';

interface Domain { id: string; name: string }

interface QrData {
  id: string;
  name: string;
  type: QrType;
  is_dynamic: boolean;
  content: Record<string, string>;
  style: QrStyle;
  domain_id: string | null;
  slug: string | null;
  dynamic_url: string | null;
}

export default function QrCodesEdit({ qrCode, domains, versions }: { qrCode: QrData; domains: Domain[]; versions?: QrVersionEntry[] }) {
  const { project } = usePage<PageProps>().props;
  const { t } = useTranslation();

  const { data, setData, put, processing, errors } = useForm<QrFormData>({
    name:       qrCode.name,
    type:       qrCode.type,
    is_dynamic: qrCode.is_dynamic,
    domain_id:  qrCode.domain_id ?? (domains[0]?.id ?? ''),
    slug:       qrCode.slug ?? '',
    content:    qrCode.content,
    style:      qrCode.style,
  });

  const original: QrEditState = {
    type: qrCode.type,
    is_dynamic: qrCode.is_dynamic,
    domain_id: qrCode.domain_id ?? '',
    slug: qrCode.slug ?? '',
    content: qrCode.content,
    style: qrCode.style,
  };

  async function submit(e: FormEvent) {
    e.preventDefault();
    const next: QrEditState = {
      type: data.type,
      is_dynamic: data.is_dynamic,
      domain_id: data.domain_id,
      slug: data.slug,
      content: data.content,
      style: data.style,
    };

    if (isRiskyEdit(original, next)) {
      const ok = await confirmAction({
        title: t('qr.edit.confirm.title'),
        text: t('qr.edit.confirm.text'),
        confirmText: t('qr.edit.confirm.button'),
      });
      if (!ok) return;
    }

    put(route('app.project.qrcodes.update', { project: project!.id, qrCode: qrCode.id }));
  }

  return (
    <AppLayout title="Edit QR code">
      <div className="px-8 py-8">
        <div className="mb-6">
          <Link href={route('app.project.qrcodes.index', { project: project!.id })}
            className="inline-flex items-center gap-1.5 text-sm text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white">
            <ArrowLeft className="h-4 w-4" /> Back to QR codes
          </Link>
          <h1 className="mt-3 text-2xl font-bold text-slate-900 dark:text-white">
            Edit <span className="text-indigo-600 dark:text-indigo-400">{qrCode.name}</span>
          </h1>
        </div>
        <div className="space-y-4">
          <QrEditor
            data={data}
            setData={setData}
            errors={errors as Record<string, string>}
            processing={processing}
            submitLabel="Save changes"
            cancelHref={route('app.project.qrcodes.index', { project: project!.id })}
            domains={domains}
            dynamicUrl={qrCode.dynamic_url ?? undefined}
            onSubmit={submit}
          />
          <QrVersionsPanel qrId={qrCode.id} versions={versions} />
        </div>
      </div>
    </AppLayout>
  );
}
```

- [ ] **Step 5: Build + full test suites**

Run: `ddev npm run build && ddev php artisan test --filter=QrCode && ddev npm run test:js`
Expected: build clean; all QR feature tests PASS; vitest PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/QrCodeController.php resources/js/Pages/QrCodes/Edit.tsx tests/Feature/QrCodeVersionTest.php
git commit -m "feat(qr): confirm risky edits and show version history on the edit page"
```

---

## Final verification

- [ ] Run the whole backend suite: `ddev php artisan test`
- [ ] Run JS unit tests: `ddev npm run test:js`
- [ ] Production build: `ddev npm run build`
- [ ] Manual smoke (via `composer run dev`): create a static QR for an `email`/`whatsapp` type (no backing link, "Not tracked" badge); edit a dynamic QR's destination only (saves with no prompt); change its slug (prompts); open Version history, restore an older version (prompts, then appears as the newest version).

---

## Notes / decisions baked in

- **Version snapshot stores `domain_id` + `slug`** (beyond the spec's name/type/is_dynamic/content/style) so a restore can faithfully rebuild the backing short link. Captured from the QR's `url` at snapshot time.
- **Restore is non-destructive**: it replays through `persist()` (same path as `update`) and appends a new version. The newest version row always equals the current state, so the panel marks index 0 as "Current" with no restore button.
- **Restore slug-collision guard**: restoring to a dynamic state whose slug is taken by a different link returns a flash error rather than throwing a DB unique violation.
- **Activity logging is retained** on the model for audit but no longer surfaced in the Edit UI (the versions panel replaces it).
- **Backend already type-agnostic** for static QRs, so Feature 1 needs no controller/validation change — only the frontend categorization plus a guard test.
```
