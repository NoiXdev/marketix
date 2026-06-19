# Admin Storage Settings Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add an admin **Storage** settings page that lets a super admin choose the file-storage backend (Local or S3-compatible) and configure S3 credentials in the UI, applied at runtime.

**Architecture:** Mirror the existing Mailer settings feature exactly. A `StorageSettings` spatie settings class holds the config; a `StorageSettingsServiceProvider` overrides `filesystems.default` (and the `s3` disk params) at runtime so every `Storage::disk()` call follows the admin's choice with no upload-code refactor. A `StorageController` (edit/update/test) + `StorageSettingsRequest` drive a React `Admin/Storage/Edit.tsx` page.

**Tech Stack:** Laravel 13, PHP 8.3, `spatie/laravel-settings`, React 19 + TypeScript + Inertia.js, lucide-react icons, PHPUnit.

## Global Constraints

- All php/composer/npm commands run via **DDEV**: `ddev php`, `ddev composer`, `ddev npm`, `ddev exec`. Never bare `php`/`composer`/`npm`.
- Frontend gate is **`ddev npm run build`** (ESLint is broken in this repo; do not rely on `npm run lint`).
- Backend gate is **`ddev composer test`** (or `ddev php artisan test --filter=<name>` for a single class).
- In tests, hit routes via **`route('name')`**, never bare paths (APP_DOMAIN vs APP_URL host mismatch).
- In `.tsx`, `route()` params always use the Ziggy **object form** (`route('name', { project: id })`); these storage routes take no params so `route('app.admin.storage.edit')` is fine.
- Encrypted setting properties use the migrator's **`addEncrypted()`** and the class's **`encrypted()`** method — same as `postal_key`/`smtp_password`.
- "Going forward only" on disk switch: **no file migration**. The UI must warn that existing files stay on the previous disk.
- Admin routes live in the existing group: `Route::middleware(['auth', 'super_admin'])->prefix('/admin')->group(...)` in `routes/web.php`.

---

## File Structure

**Backend (create):**
- `app/Settings/StorageSettings.php` — settings class, group `storage`
- `database/settings/2026_06_20_000000_create_storage_settings.php` — seeds defaults from config/env
- `app/Providers/StorageSettingsServiceProvider.php` — runtime `apply()` of disk config
- `app/Http/Controllers/Admin/StorageController.php` — edit/update/test
- `app/Http/Requests/Admin/StorageSettingsRequest.php` — validation
- `tests/Feature/Admin/StorageSettingsTest.php` — settings/provider unit-level tests
- `tests/Feature/Admin/StorageControllerTest.php` — HTTP/controller tests

**Backend (modify):**
- `config/settings.php` — register `StorageSettings::class`
- `bootstrap/providers.php` — register `StorageSettingsServiceProvider::class`
- `routes/web.php` — add storage routes + import

**Frontend (create):**
- `resources/js/Pages/Admin/Storage/Edit.tsx` — the settings page

**Frontend (modify):**
- `resources/js/Components/AdminSidebar.tsx` — add Storage nav item

---

## Task 1: StorageSettings class, migration & registration

Creates the data layer: the settings class, its seed migration, and registration. Deliverable: `app(StorageSettings::class)` resolves with config-seeded values and the secret is encrypted at rest.

**Files:**
- Create: `app/Settings/StorageSettings.php`
- Create: `database/settings/2026_06_20_000000_create_storage_settings.php`
- Create: `tests/Feature/Admin/StorageSettingsTest.php`
- Modify: `config/settings.php` (add to `settings` array, lines 18-21)

**Interfaces:**
- Produces: `App\Settings\StorageSettings` with public properties `string $driver`, `string $s3_key`, `string $s3_secret`, `string $s3_region`, `string $s3_bucket`, `string $s3_endpoint`, `bool $s3_use_path_style`; `group()` returns `'storage'`; `encrypted()` returns `['s3_secret']`. Consumed by Tasks 2 and 3.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Admin/StorageSettingsTest.php`:

```php
<?php

namespace Tests\Feature\Admin;

use App\Settings\StorageSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StorageSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_settings_are_seeded_from_config(): void
    {
        $settings = app(StorageSettings::class);

        $this->assertContains($settings->driver, ['local', 's3']);
        $this->assertIsBool($settings->s3_use_path_style);
        $this->assertIsString($settings->s3_bucket);
    }

    public function test_secret_is_encrypted_at_rest(): void
    {
        $settings = app(StorageSettings::class);
        $settings->s3_secret = 'super-secret-value';
        $settings->save();

        $raw = DB::table('settings')
            ->where('group', 'storage')
            ->where('name', 's3_secret')
            ->value('payload');

        $this->assertStringNotContainsString('super-secret-value', (string) $raw);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `ddev php artisan test --filter=StorageSettingsTest`
Expected: FAIL — `Class "App\Settings\StorageSettings" not found`.

- [ ] **Step 3: Create the settings class**

Create `app/Settings/StorageSettings.php`:

```php
<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class StorageSettings extends Settings
{
    public string $driver;

    public string $s3_key;

    public string $s3_secret;

    public string $s3_region;

    public string $s3_bucket;

    public string $s3_endpoint;

    public bool $s3_use_path_style;

    public static function group(): string
    {
        return 'storage';
    }

    /**
     * @return array<int, string>
     */
    public static function encrypted(): array
    {
        return ['s3_secret'];
    }
}
```

- [ ] **Step 4: Create the settings migration**

Create `database/settings/2026_06_20_000000_create_storage_settings.php`:

```php
<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('storage.driver', 'local');
        $this->migrator->add('storage.s3_key', (string) config('filesystems.disks.s3.key', ''));
        $this->migrator->addEncrypted('storage.s3_secret', (string) config('filesystems.disks.s3.secret', ''));
        $this->migrator->add('storage.s3_region', (string) config('filesystems.disks.s3.region', ''));
        $this->migrator->add('storage.s3_bucket', (string) config('filesystems.disks.s3.bucket', ''));
        $this->migrator->add('storage.s3_endpoint', (string) config('filesystems.disks.s3.endpoint', ''));
        $this->migrator->add('storage.s3_use_path_style', (bool) config('filesystems.disks.s3.use_path_style_endpoint', false));
    }
};
```

- [ ] **Step 5: Register the settings class**

In `config/settings.php`, add `StorageSettings::class` to the `settings` array (after `BrandingSettings::class`):

```php
    'settings' => [
        MailSettings::class,
        BrandingSettings::class,
        StorageSettings::class,
    ],
```

Add the import at the top of the file (with the other `use App\Settings\...` lines):

```php
use App\Settings\StorageSettings;
```

- [ ] **Step 6: Run the test to verify it passes**

Run: `ddev php artisan test --filter=StorageSettingsTest`
Expected: PASS (2 tests). The settings migration runs automatically under `RefreshDatabase`.

- [ ] **Step 7: Commit**

```bash
git add app/Settings/StorageSettings.php database/settings/2026_06_20_000000_create_storage_settings.php config/settings.php tests/Feature/Admin/StorageSettingsTest.php
git commit -m "feat: add StorageSettings with encrypted s3 secret"
```

---

## Task 2: Runtime application via StorageSettingsServiceProvider

Wires the saved settings into `config('filesystems.*')` at runtime, so `Storage::disk()` follows the admin's choice. Deliverable: saving `driver=s3` makes `config('filesystems.default') === 's3'` with the saved connection params; `driver=local` leaves the default untouched.

**Files:**
- Create: `app/Providers/StorageSettingsServiceProvider.php`
- Modify: `bootstrap/providers.php`
- Modify: `tests/Feature/Admin/StorageSettingsTest.php` (add two tests)

**Interfaces:**
- Consumes: `App\Settings\StorageSettings` (Task 1).
- Produces: `App\Providers\StorageSettingsServiceProvider` with a public `apply(): void` method (callable from tests as `(new StorageSettingsServiceProvider($this->app))->apply()`).

- [ ] **Step 1: Write the failing tests**

Add these two methods to `tests/Feature/Admin/StorageSettingsTest.php` and add the import `use App\Providers\StorageSettingsServiceProvider;` at the top:

```php
    public function test_s3_driver_overrides_filesystem_config(): void
    {
        $settings = app(StorageSettings::class);
        $settings->driver = 's3';
        $settings->s3_key = 'KEY';
        $settings->s3_secret = 'SECRET';
        $settings->s3_region = 'eu-central-1';
        $settings->s3_bucket = 'my-bucket';
        $settings->s3_endpoint = 'https://r2.example.com';
        $settings->s3_use_path_style = true;
        $settings->save();

        (new StorageSettingsServiceProvider($this->app))->apply();

        $this->assertSame('s3', config('filesystems.default'));
        $this->assertSame('my-bucket', config('filesystems.disks.s3.bucket'));
        $this->assertSame('https://r2.example.com', config('filesystems.disks.s3.endpoint'));
        $this->assertTrue(config('filesystems.disks.s3.use_path_style_endpoint'));
    }

    public function test_local_driver_leaves_default_unchanged(): void
    {
        config(['filesystems.default' => 'local']);

        $settings = app(StorageSettings::class);
        $settings->driver = 'local';
        $settings->save();

        (new StorageSettingsServiceProvider($this->app))->apply();

        $this->assertSame('local', config('filesystems.default'));
    }
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `ddev php artisan test --filter=StorageSettingsTest`
Expected: FAIL — `Class "App\Providers\StorageSettingsServiceProvider" not found`.

- [ ] **Step 3: Create the provider**

Create `app/Providers/StorageSettingsServiceProvider.php` (mirrors `MailSettingsServiceProvider`, but `apply()` is public so tests can call it):

```php
<?php

namespace App\Providers;

use App\Settings\StorageSettings;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Laravel\Octane\Events\RequestReceived;
use Throwable;

class StorageSettingsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->apply();

        // Re-apply before every queued job: the long-lived `database` queue
        // worker would otherwise keep stale disk config after an admin saves.
        Event::listen(JobProcessing::class, fn () => $this->apply());

        // Re-apply per Octane/FrankenPHP web request, where the worker persists
        // across requests. Under plain php-fpm this listener simply never fires.
        Event::listen(RequestReceived::class, fn () => $this->apply());
    }

    public function apply(): void
    {
        try {
            // Force a fresh load: under Octane/queue the container persists and
            // would return a stale cached singleton.
            $settings = $this->app->make(StorageSettings::class)->refresh();

            if ($settings->driver === 's3') {
                config([
                    'filesystems.disks.s3.key' => $settings->s3_key,
                    'filesystems.disks.s3.secret' => $settings->s3_secret,
                    'filesystems.disks.s3.region' => $settings->s3_region,
                    'filesystems.disks.s3.bucket' => $settings->s3_bucket,
                    'filesystems.disks.s3.endpoint' => $settings->s3_endpoint ?: null,
                    'filesystems.disks.s3.use_path_style_endpoint' => $settings->s3_use_path_style,
                    'filesystems.default' => 's3',
                ]);
            }
            // driver === 'local' → leave filesystems.default as configured in env.
        } catch (Throwable) {
            // Settings unavailable (table not migrated, no DB, etc.) — fall back
            // to .env/config defaults.
        }
    }
}
```

- [ ] **Step 4: Register the provider**

In `bootstrap/providers.php`, add the import and the entry (after `MailSettingsServiceProvider`):

```php
<?php

use App\Providers\AppServiceProvider;
use App\Providers\BrandingServiceProvider;
use App\Providers\HorizonServiceProvider;
use App\Providers\MailSettingsServiceProvider;
use App\Providers\StorageSettingsServiceProvider;

return [
    AppServiceProvider::class,
    HorizonServiceProvider::class,
    MailSettingsServiceProvider::class,
    StorageSettingsServiceProvider::class,
    BrandingServiceProvider::class,
];
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `ddev php artisan test --filter=StorageSettingsTest`
Expected: PASS (4 tests total).

- [ ] **Step 6: Commit**

```bash
git add app/Providers/StorageSettingsServiceProvider.php bootstrap/providers.php tests/Feature/Admin/StorageSettingsTest.php
git commit -m "feat: apply storage settings to filesystem config at runtime"
```

---

## Task 3: StorageController, request & routes

Adds the HTTP layer: edit (render), update (persist with secret masking), and test (verify connection). Deliverable: a super admin can GET/PUT the settings and POST a connection test; non-admins get 403.

**Files:**
- Create: `app/Http/Controllers/Admin/StorageController.php`
- Create: `app/Http/Requests/Admin/StorageSettingsRequest.php`
- Create: `tests/Feature/Admin/StorageControllerTest.php`
- Modify: `routes/web.php` (import + 3 routes in the admin group)

**Interfaces:**
- Consumes: `StorageSettings` (Task 1).
- Produces: routes `app.admin.storage.edit` (GET), `app.admin.storage.update` (PUT), `app.admin.storage.test` (POST). The `edit` Inertia payload: `settings` (object with all fields **except** `s3_secret`) and `has_s3_secret` (bool). Consumed by Task 4.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/Admin/StorageControllerTest.php`:

```php
<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Settings\StorageSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class StorageControllerTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        $u = User::factory()->create();
        $u->super_admin = true;
        $u->save();

        return $u;
    }

    public function test_non_super_admin_is_forbidden(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('app.admin.storage.edit'))
            ->assertForbidden();
    }

    public function test_edit_renders_without_exposing_secret(): void
    {
        $settings = app(StorageSettings::class);
        $settings->s3_secret = 'secret-value';
        $settings->save();

        $this->actingAs($this->superAdmin())
            ->get(route('app.admin.storage.edit'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Storage/Edit')
                ->where('has_s3_secret', true)
                ->missing('settings.s3_secret'));
    }

    public function test_update_persists_and_preserves_blank_secret(): void
    {
        $settings = app(StorageSettings::class);
        $settings->s3_secret = 'original-secret';
        $settings->save();

        $this->actingAs($this->superAdmin())
            ->put(route('app.admin.storage.update'), [
                'driver' => 's3',
                's3_key' => 'AKIA',
                's3_secret' => '', // blank → keep existing
                's3_region' => 'eu-central-1',
                's3_bucket' => 'bucket',
                's3_endpoint' => '',
                's3_use_path_style' => true,
            ])
            ->assertRedirect(route('app.admin.storage.edit'));

        $fresh = app(StorageSettings::class);
        $this->assertSame('s3', $fresh->driver);
        $this->assertSame('bucket', $fresh->s3_bucket);
        $this->assertSame('original-secret', $fresh->s3_secret);
    }

    public function test_update_replaces_secret_when_provided(): void
    {
        $settings = app(StorageSettings::class);
        $settings->s3_secret = 'original-secret';
        $settings->save();

        $this->actingAs($this->superAdmin())
            ->put(route('app.admin.storage.update'), [
                'driver' => 's3',
                's3_key' => 'AKIA',
                's3_secret' => 'rotated-secret',
                's3_region' => 'eu-central-1',
                's3_bucket' => 'bucket',
            ]);

        $this->assertSame('rotated-secret', app(StorageSettings::class)->s3_secret);
    }

    public function test_s3_driver_requires_bucket_region_and_key(): void
    {
        $this->actingAs($this->superAdmin())
            ->put(route('app.admin.storage.update'), [
                'driver' => 's3',
            ])
            ->assertSessionHasErrors(['s3_key', 's3_region', 's3_bucket']);
    }

    public function test_local_driver_needs_no_s3_fields(): void
    {
        $this->actingAs($this->superAdmin())
            ->put(route('app.admin.storage.update'), [
                'driver' => 'local',
            ])
            ->assertRedirect(route('app.admin.storage.edit'));

        $this->assertSame('local', app(StorageSettings::class)->driver);
    }

    public function test_test_connection_succeeds(): void
    {
        Storage::fake();

        $this->actingAs($this->superAdmin())
            ->post(route('app.admin.storage.test'), ['driver' => 'local'])
            ->assertRedirect()
            ->assertSessionHas('success');
    }

    public function test_test_connection_reports_failure(): void
    {
        Storage::shouldReceive('disk')->andThrow(new \RuntimeException('boom'));

        $this->actingAs($this->superAdmin())
            ->post(route('app.admin.storage.test'), ['driver' => 'local'])
            ->assertRedirect()
            ->assertSessionHas('error');
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `ddev php artisan test --filter=StorageControllerTest`
Expected: FAIL — route `app.admin.storage.edit` not defined.

- [ ] **Step 3: Create the FormRequest**

Create `app/Http/Requests/Admin/StorageSettingsRequest.php`:

```php
<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorageSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Access enforced by the super_admin route middleware.
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'driver' => ['required', Rule::in(['local', 's3'])],
            's3_key' => ['required_if:driver,s3', 'nullable', 'string', 'max:255'],
            's3_secret' => ['nullable', 'string', 'max:255'],
            's3_region' => ['required_if:driver,s3', 'nullable', 'string', 'max:255'],
            's3_bucket' => ['required_if:driver,s3', 'nullable', 'string', 'max:255'],
            's3_endpoint' => ['nullable', 'url', 'max:255'],
            's3_use_path_style' => ['boolean'],
        ];
    }
}
```

- [ ] **Step 4: Create the controller**

Create `app/Http/Controllers/Admin/StorageController.php`:

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorageSettingsRequest;
use App\Settings\StorageSettings;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class StorageController extends Controller
{
    public function edit(StorageSettings $settings)
    {
        return inertia('Admin/Storage/Edit', [
            'settings' => [
                'driver' => $settings->driver,
                's3_key' => $settings->s3_key,
                's3_region' => $settings->s3_region,
                's3_bucket' => $settings->s3_bucket,
                's3_endpoint' => $settings->s3_endpoint,
                's3_use_path_style' => $settings->s3_use_path_style,
            ],
            'has_s3_secret' => ! empty($settings->s3_secret),
        ]);
    }

    public function update(StorageSettingsRequest $request, StorageSettings $settings)
    {
        $data = $request->validated();

        $settings->driver = $data['driver'];
        $settings->s3_key = $data['s3_key'] ?? '';
        $settings->s3_region = $data['s3_region'] ?? '';
        $settings->s3_bucket = $data['s3_bucket'] ?? '';
        $settings->s3_endpoint = $data['s3_endpoint'] ?? '';
        $settings->s3_use_path_style = (bool) ($data['s3_use_path_style'] ?? false);

        // Only overwrite the secret when a new value is supplied (mask behaviour).
        if (! empty($data['s3_secret'])) {
            $settings->s3_secret = $data['s3_secret'];
        }

        $settings->save();

        return redirect()->route('app.admin.storage.edit')->with('success', 'Storage settings saved.');
    }

    public function test(StorageSettingsRequest $request)
    {
        $data = $request->validated();

        try {
            $disk = ($data['driver'] ?? 'local') === 's3'
                ? Storage::build([
                    'driver' => 's3',
                    'key' => $data['s3_key'] ?? '',
                    // Fall back to the stored secret when the form field is blank.
                    'secret' => $data['s3_secret'] ?: app(StorageSettings::class)->s3_secret,
                    'region' => $data['s3_region'] ?? '',
                    'bucket' => $data['s3_bucket'] ?? '',
                    'endpoint' => ($data['s3_endpoint'] ?? '') ?: null,
                    'use_path_style_endpoint' => (bool) ($data['s3_use_path_style'] ?? false),
                    'throw' => true,
                ])
                : Storage::disk();

            $path = 'storage-test-'.Str::uuid().'.txt';
            $disk->put($path, 'ok');
            $disk->get($path);
            $disk->delete($path);
        } catch (Throwable $e) {
            return back()->with('error', 'Storage test failed: '.$e->getMessage());
        }

        return back()->with('success', 'Storage connection OK.');
    }
}
```

- [ ] **Step 5: Add the routes**

In `routes/web.php`, add the import next to the other `Admin\` controllers (after `BrandingController`):

```php
use App\Http\Controllers\Admin\StorageController;
```

Inside the `Route::middleware(['auth', 'super_admin'])->prefix('/admin')->group(...)` block, after the branding routes (line ~175):

```php
        Route::get('/storage', [StorageController::class, 'edit'])->name('app.admin.storage.edit');
        Route::put('/storage', [StorageController::class, 'update'])->name('app.admin.storage.update');
        Route::post('/storage/test', [StorageController::class, 'test'])->name('app.admin.storage.test');
```

- [ ] **Step 6: Run tests to verify they pass**

Run: `ddev php artisan test --filter=StorageControllerTest`
Expected: PASS (8 tests).

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/Admin/StorageController.php app/Http/Requests/Admin/StorageSettingsRequest.php routes/web.php tests/Feature/Admin/StorageControllerTest.php
git commit -m "feat: add storage settings controller, request and routes"
```

---

## Task 4: React page & sidebar entry

Adds the UI. Deliverable: a Storage page in the admin area with driver select, conditional S3 fieldset (masked secret), a "going forward only" warning, Save, and a Test connection button; reachable from the sidebar. Verified by the TypeScript build.

**Files:**
- Create: `resources/js/Pages/Admin/Storage/Edit.tsx`
- Modify: `resources/js/Components/AdminSidebar.tsx`

**Interfaces:**
- Consumes: the `edit` payload from Task 3 (`settings` object + `has_s3_secret`), and routes `app.admin.storage.update` / `app.admin.storage.test`.

- [ ] **Step 1: Create the React page**

Create `resources/js/Pages/Admin/Storage/Edit.tsx`:

```tsx
import AdminLayout from '@/Layouts/AdminLayout';
import { PageProps } from '@/types';
import { useForm, usePage } from '@inertiajs/react';

interface StorageSettings {
  driver: string;
  s3_key: string;
  s3_region: string;
  s3_bucket: string;
  s3_endpoint: string;
  s3_use_path_style: boolean;
}

interface Props {
  settings: StorageSettings;
  has_s3_secret: boolean;
}

const inputClass =
  'w-full rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white';
const labelClass = 'mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300';

export default function AdminStorageEdit({ settings, has_s3_secret }: Props) {
  const { flash } = usePage<PageProps>().props;
  const { data, setData, put, processing, errors } = useForm({
    driver: settings.driver,
    s3_key: settings.s3_key,
    s3_secret: '',
    s3_region: settings.s3_region,
    s3_bucket: settings.s3_bucket,
    s3_endpoint: settings.s3_endpoint,
    s3_use_path_style: settings.s3_use_path_style,
  });

  const driverChanged = data.driver !== settings.driver;

  function submit(e: React.FormEvent) {
    e.preventDefault();
    put(route('app.admin.storage.update'));
  }

  function testConnection() {
    // Reuse the current form values; post them to the test endpoint.
    router.post(route('app.admin.storage.test'), { ...data }, { preserveScroll: true });
  }

  return (
    <AdminLayout title="Storage">
      <div className="px-8 py-8">
        <h1 className="mb-6 text-2xl font-bold text-slate-900 dark:text-white">Storage settings</h1>

        {flash?.success && (
          <div className="mb-4 max-w-md rounded-md bg-green-50 px-4 py-3 text-sm text-green-700 dark:bg-green-900/20 dark:text-green-400">{flash.success}</div>
        )}
        {flash?.error && (
          <div className="mb-4 max-w-md rounded-md bg-red-50 px-4 py-3 text-sm text-red-700 dark:bg-red-900/20 dark:text-red-400">{flash.error}</div>
        )}

        <form onSubmit={submit} className="max-w-md space-y-4">
          <div>
            <label className={labelClass}>Storage backend</label>
            <select
              value={data.driver}
              onChange={(e) => setData('driver', e.target.value)}
              className={inputClass}
            >
              <option value="local">Local disk</option>
              <option value="s3">S3-compatible</option>
            </select>
            {errors.driver && <p className="mt-1 text-xs text-red-600">{errors.driver}</p>}
          </div>

          {driverChanged && (
            <div className="max-w-md rounded-md bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:bg-amber-900/20 dark:text-amber-300">
              Existing files (logos, favicons) stay on the previous disk and may need re-uploading. New uploads will use the selected disk.
            </div>
          )}

          {data.driver === 's3' && (
            <fieldset className="space-y-4 rounded-md border border-slate-200 p-4 dark:border-slate-700">
              <legend className="px-1 text-sm font-semibold text-slate-700 dark:text-slate-300">S3-compatible</legend>
              <div>
                <label className={labelClass}>Access key ID</label>
                <input value={data.s3_key} onChange={(e) => setData('s3_key', e.target.value)} className={inputClass} />
                {errors.s3_key && <p className="mt-1 text-xs text-red-600">{errors.s3_key}</p>}
              </div>
              <div>
                <label className={labelClass}>Secret access key {has_s3_secret && '(leave blank to keep current)'}</label>
                <input
                  type="password"
                  placeholder={has_s3_secret ? '•••••••• set' : ''}
                  value={data.s3_secret}
                  onChange={(e) => setData('s3_secret', e.target.value)}
                  className={inputClass}
                />
                {errors.s3_secret && <p className="mt-1 text-xs text-red-600">{errors.s3_secret}</p>}
              </div>
              <div>
                <label className={labelClass}>Region</label>
                <input value={data.s3_region} onChange={(e) => setData('s3_region', e.target.value)} className={inputClass} />
                {errors.s3_region && <p className="mt-1 text-xs text-red-600">{errors.s3_region}</p>}
              </div>
              <div>
                <label className={labelClass}>Bucket</label>
                <input value={data.s3_bucket} onChange={(e) => setData('s3_bucket', e.target.value)} className={inputClass} />
                {errors.s3_bucket && <p className="mt-1 text-xs text-red-600">{errors.s3_bucket}</p>}
              </div>
              <div>
                <label className={labelClass}>Endpoint</label>
                <input
                  value={data.s3_endpoint}
                  onChange={(e) => setData('s3_endpoint', e.target.value)}
                  className={inputClass}
                  placeholder="https://..."
                />
                <p className="mt-1 text-xs text-slate-500 dark:text-slate-400">
                  Leave blank for AWS; set for Cloudflare R2, MinIO, DigitalOcean Spaces, Hetzner.
                </p>
                {errors.s3_endpoint && <p className="mt-1 text-xs text-red-600">{errors.s3_endpoint}</p>}
              </div>
              <div className="flex items-center gap-2">
                <input
                  id="s3_use_path_style"
                  type="checkbox"
                  checked={data.s3_use_path_style}
                  onChange={(e) => setData('s3_use_path_style', e.target.checked)}
                  className="h-4 w-4 rounded border-slate-300"
                />
                <label htmlFor="s3_use_path_style" className="text-sm text-slate-700 dark:text-slate-300">
                  Use path-style endpoint
                </label>
              </div>
            </fieldset>
          )}

          <div className="flex items-center gap-3">
            <button
              disabled={processing}
              className="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500 disabled:opacity-50"
            >
              Save
            </button>
            <button
              type="button"
              onClick={testConnection}
              className="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800"
            >
              Test connection
            </button>
          </div>
        </form>
      </div>
    </AdminLayout>
  );
}
```

- [ ] **Step 2: Add the `router` import**

The test button uses `router.post`. At the top of the file, update the Inertia import to include `router`:

```tsx
import { router, useForm, usePage } from '@inertiajs/react';
```

(Final import line — replaces the `useForm, usePage` import shown in Step 1.)

- [ ] **Step 3: Add the sidebar entry**

In `resources/js/Components/AdminSidebar.tsx`, add `HardDrive` to the lucide-react import and a nav item between Branding and Activity.

Update the import line (line 2):

```tsx
import { Activity, ArrowLeft, FolderKanban, HardDrive, Mail, Palette, ScrollText, Users } from 'lucide-react';
```

Update `navItems` (lines 7-13):

```tsx
const navItems = [
  { label: 'Users', icon: Users, routeName: 'app.admin.users.index' },
  { label: 'Projects', icon: FolderKanban, routeName: 'app.admin.projects.index' },
  { label: 'Mailer', icon: Mail, routeName: 'app.admin.mailer.edit' },
  { label: 'Branding', icon: Palette, routeName: 'app.admin.branding.edit' },
  { label: 'Storage', icon: HardDrive, routeName: 'app.admin.storage.edit' },
  { label: 'Activity', icon: ScrollText, routeName: 'app.admin.activity.index' },
];
```

- [ ] **Step 4: Build to verify TypeScript compiles**

Run: `ddev npm run build`
Expected: build succeeds (TypeScript check passes, Vite bundles). No type errors referencing `Admin/Storage/Edit` or `AdminSidebar`.

- [ ] **Step 5: Commit**

```bash
git add resources/js/Pages/Admin/Storage/Edit.tsx resources/js/Components/AdminSidebar.tsx
git commit -m "feat: add admin storage settings page and nav"
```

---

## Final verification

- [ ] Run the full backend suite: `ddev composer test` — all green.
- [ ] Run the frontend gate: `ddev npm run build` — succeeds.
- [ ] Manual smoke (optional, per `superpowers:verify` / `run` skill): log in as a super admin, open **Storage** in the admin sidebar, switch to S3-compatible, confirm the warning banner appears, fill in credentials, click **Test connection**, and **Save**.

## Notes / risks

- Overriding global `filesystems.default` is safe given the current audit: only branding uses `Storage::disk()` with no argument and it wants public-style URLs. If a future feature relies on a private default disk, revisit the provider.
- S3 live connectivity is exercised by the **Test connection** button at runtime; the automated `test` covers the success/error branches deterministically (faked disk + facade throw), not a real network call.
- "Going forward only": switching disks can orphan existing assets. Accepted for v1; the UI warning makes it explicit. A migration action can be a later feature.
