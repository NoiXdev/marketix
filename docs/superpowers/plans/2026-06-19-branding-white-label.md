# White-Label Branding Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let super admins replace the hardcoded "Marketix" brand (app name, logos, favicon) with their own, instance-wide, via an `/admin/branding` settings page.

**Architecture:** A single global Spatie `BrandingSettings` (group `branding`) holds the app name plus storage paths for four images (light logo, dark logo, email logo, favicon). Images live on the **default filesystem disk** with public visibility; only paths are stored in the DB. A `BrandingController` (mirroring the existing `MailerController`) edits them. Branding reaches React via a `branding` shared Inertia prop consumed by a single `<Brand>` component, and reaches Blade (head title, favicon, emails, PDF) via a `BrandingServiceProvider` that sets `config('app.name')` and shares view variables.

**Tech Stack:** Laravel 13, PHP 8.3, Spatie Laravel Settings, Spatie Laravel PDF, Inertia.js, React 19 + TypeScript, Tailwind, Lucide icons.

## Global Constraints

- Run all PHP/Composer/NPM commands through DDEV: `ddev php`, `ddev composer`, `ddev npm`, `ddev exec`. Never bare `php`/`npm`.
- Frontend gate is `ddev npm run build` (tsc + Vite). `ddev npm run lint` is broken — do not use it.
- Ziggy `route()` calls: always pass params as an object, e.g. `route('app.admin.branding.edit')` (no params here) and `route('x', { project: id })` elsewhere — never a bare scalar.
- Images are stored on the **default disk** (`config('filesystems.default')`, S3 in prod) with **public** visibility via `->storePublicly('branding')`. URLs are derived with `Storage::disk()->url($path)`. Never store binary in the settings payload.
- Brand name fallback is the literal string `Marketix`. Image fallback is the Lucide `Link2` icon + the (custom or fallback) app name; favicon fallback is `/favicon.ico`.
- All admin routes already sit behind `['auth','super_admin']`; the `BrandingSettingsRequest::authorize()` returns `true` (access enforced by `EnsureSuperAdmin`), matching `MailSettingsRequest`.
- Settings/HTTP requests live under namespace `App\Http\Requests\Admin` (matching `MailSettingsRequest`); admin controllers under `App\Http\Controllers\Admin`.

---

### Task 1: BrandingSettings class, migration, accessors, registration

**Files:**
- Create: `app/Settings/BrandingSettings.php`
- Create: `database/settings/2026_06_19_000000_create_branding_settings.php`
- Modify: `config/settings.php` (add `BrandingSettings::class` to the `settings` array)
- Test: `tests/Feature/Admin/BrandingSettingsTest.php`

**Interfaces:**
- Produces: `App\Settings\BrandingSettings` with public nullable props `?string $app_name, $logo_light_path, $logo_dark_path, $logo_email_path, $favicon_path`; group `branding`; and accessors `appName(): string`, `logoLightUrl(): ?string`, `logoDarkUrl(): ?string`, `emailLogoUrl(): ?string`, `faviconUrl(): ?string`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Admin;

use App\Settings\BrandingSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BrandingSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_app_name_defaults_to_marketix_and_is_overridable(): void
    {
        $settings = app(BrandingSettings::class);
        $this->assertSame('Marketix', $settings->appName());

        $settings->app_name = 'Acme Links';
        $settings->save();

        $this->assertSame('Acme Links', app(BrandingSettings::class)->appName());
    }

    public function test_url_accessors_are_null_until_paths_set_then_resolve_via_default_disk(): void
    {
        Storage::fake();

        $settings = app(BrandingSettings::class);
        $this->assertNull($settings->faviconUrl());
        $this->assertNull($settings->emailLogoUrl());

        $settings->favicon_path = 'branding/favicon.png';
        $settings->logo_email_path = 'branding/email.png';
        $settings->save();

        $fresh = app(BrandingSettings::class);
        $this->assertStringContainsString('branding/favicon.png', $fresh->faviconUrl());
        $this->assertStringContainsString('branding/email.png', $fresh->emailLogoUrl());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `ddev php artisan test --filter=BrandingSettingsTest`
Expected: FAIL — `Class "App\Settings\BrandingSettings" not found`.

- [ ] **Step 3: Create the settings migration**

`database/settings/2026_06_19_000000_create_branding_settings.php`:

```php
<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('branding.app_name', null);
        $this->migrator->add('branding.logo_light_path', null);
        $this->migrator->add('branding.logo_dark_path', null);
        $this->migrator->add('branding.logo_email_path', null);
        $this->migrator->add('branding.favicon_path', null);
    }
};
```

- [ ] **Step 4: Create the settings class**

`app/Settings/BrandingSettings.php`:

```php
<?php

namespace App\Settings;

use Illuminate\Support\Facades\Storage;
use Spatie\LaravelSettings\Settings;

class BrandingSettings extends Settings
{
    public ?string $app_name = null;

    public ?string $logo_light_path = null;

    public ?string $logo_dark_path = null;

    public ?string $logo_email_path = null;

    public ?string $favicon_path = null;

    public static function group(): string
    {
        return 'branding';
    }

    public function appName(): string
    {
        return $this->app_name ?: 'Marketix';
    }

    public function logoLightUrl(): ?string
    {
        return $this->urlFor($this->logo_light_path);
    }

    public function logoDarkUrl(): ?string
    {
        return $this->urlFor($this->logo_dark_path);
    }

    public function emailLogoUrl(): ?string
    {
        return $this->urlFor($this->logo_email_path);
    }

    public function faviconUrl(): ?string
    {
        return $this->urlFor($this->favicon_path);
    }

    private function urlFor(?string $path): ?string
    {
        return $path ? Storage::disk()->url($path) : null;
    }
}
```

- [ ] **Step 5: Register the settings class**

In `config/settings.php`, change the `settings` array to:

```php
    'settings' => [
        MailSettings::class,
        \App\Settings\BrandingSettings::class,
    ],
```

- [ ] **Step 6: Run the test to verify it passes**

Run: `ddev php artisan test --filter=BrandingSettingsTest`
Expected: PASS (2 tests).

- [ ] **Step 7: Commit**

```bash
git add app/Settings/BrandingSettings.php database/settings/2026_06_19_000000_create_branding_settings.php config/settings.php tests/Feature/Admin/BrandingSettingsTest.php
git commit -m "feat: add BrandingSettings with image-url accessors"
```

---

### Task 2: BrandingController, request, routes

**Files:**
- Create: `app/Http/Controllers/Admin/BrandingController.php`
- Create: `app/Http/Requests/Admin/BrandingSettingsRequest.php`
- Modify: `routes/web.php` (add two routes inside the existing `['auth','super_admin']` `/admin` group, near the mailer routes ~line 169-171)
- Test: `tests/Feature/Admin/BrandingControllerTest.php`

**Interfaces:**
- Consumes: `App\Settings\BrandingSettings` (Task 1).
- Produces: routes `app.admin.branding.edit` (GET `/admin/branding`) and `app.admin.branding.update` (POST `/admin/branding`); Inertia page `Admin/Branding/Edit` with props `{ app_name: string|null, logo_light_url, logo_dark_url, logo_email_url, favicon_url: string|null }`.

- [ ] **Step 1: Write the failing test**

`tests/Feature/Admin/BrandingControllerTest.php`:

```php
<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Settings\BrandingSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class BrandingControllerTest extends TestCase
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
            ->get(route('app.admin.branding.edit'))
            ->assertForbidden();
    }

    public function test_edit_page_renders_with_resolved_urls(): void
    {
        Storage::fake();
        $settings = app(BrandingSettings::class);
        $settings->app_name = 'Acme Links';
        $settings->favicon_path = 'branding/favicon.png';
        $settings->save();

        $this->actingAs($this->superAdmin())
            ->get(route('app.admin.branding.edit'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Branding/Edit')
                ->where('app_name', 'Acme Links')
                ->where('logo_light_url', null)
                ->whereContains('favicon_url', 'branding/favicon.png'));
    }

    public function test_update_persists_app_name(): void
    {
        $this->actingAs($this->superAdmin())
            ->post(route('app.admin.branding.update'), ['app_name' => 'Acme Links'])
            ->assertRedirect(route('app.admin.branding.edit'));

        $this->assertSame('Acme Links', app(BrandingSettings::class)->appName());
    }

    public function test_uploading_logo_stores_file_and_saves_path(): void
    {
        Storage::fake();

        $this->actingAs($this->superAdmin())
            ->post(route('app.admin.branding.update'), [
                'app_name' => 'Acme',
                'logo_light' => UploadedFile::fake()->image('logo.png', 200, 60),
            ])
            ->assertRedirect();

        $path = app(BrandingSettings::class)->logo_light_path;
        $this->assertNotNull($path);
        Storage::disk()->assertExists($path);
    }

    public function test_remove_flag_clears_path_and_deletes_file(): void
    {
        Storage::fake();
        Storage::disk()->put('branding/old.png', 'x');
        $settings = app(BrandingSettings::class);
        $settings->logo_light_path = 'branding/old.png';
        $settings->save();

        $this->actingAs($this->superAdmin())
            ->post(route('app.admin.branding.update'), [
                'app_name' => 'Acme',
                'remove_logo_light' => '1',
            ])
            ->assertRedirect();

        $this->assertNull(app(BrandingSettings::class)->logo_light_path);
        Storage::disk()->assertMissing('branding/old.png');
    }

    public function test_invalid_logo_upload_is_rejected(): void
    {
        $this->actingAs($this->superAdmin())
            ->post(route('app.admin.branding.update'), [
                'app_name' => 'Acme',
                'logo_light' => UploadedFile::fake()->create('not-an-image.pdf', 10, 'application/pdf'),
            ])
            ->assertSessionHasErrors('logo_light');
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `ddev php artisan test --filter=BrandingControllerTest`
Expected: FAIL — route `app.admin.branding.edit` not defined.

- [ ] **Step 3: Create the form request**

`app/Http/Requests/Admin/BrandingSettingsRequest.php`:

```php
<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class BrandingSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Access enforced by EnsureSuperAdmin.
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'app_name' => ['nullable', 'string', 'max:255'],

            'logo_light' => ['nullable', 'image', 'max:2048'],
            'logo_dark' => ['nullable', 'image', 'max:2048'],
            'logo_email' => ['nullable', 'image', 'max:2048'],
            'favicon' => ['nullable', 'file', 'mimes:ico,png,svg,jpg,jpeg', 'max:2048'],

            'remove_logo_light' => ['nullable', 'boolean'],
            'remove_logo_dark' => ['nullable', 'boolean'],
            'remove_logo_email' => ['nullable', 'boolean'],
            'remove_favicon' => ['nullable', 'boolean'],
        ];
    }
}
```

- [ ] **Step 4: Create the controller**

`app/Http/Controllers/Admin/BrandingController.php`:

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BrandingSettingsRequest;
use App\Settings\BrandingSettings;
use Illuminate\Support\Facades\Storage;

class BrandingController extends Controller
{
    /**
     * Map of upload field name => settings property holding the stored path.
     *
     * @var array<string, string>
     */
    private const IMAGE_FIELDS = [
        'logo_light' => 'logo_light_path',
        'logo_dark' => 'logo_dark_path',
        'logo_email' => 'logo_email_path',
        'favicon' => 'favicon_path',
    ];

    public function edit(BrandingSettings $settings)
    {
        return inertia('Admin/Branding/Edit', [
            'app_name' => $settings->app_name,
            'logo_light_url' => $settings->logoLightUrl(),
            'logo_dark_url' => $settings->logoDarkUrl(),
            'logo_email_url' => $settings->emailLogoUrl(),
            'favicon_url' => $settings->faviconUrl(),
        ]);
    }

    public function update(BrandingSettingsRequest $request, BrandingSettings $settings)
    {
        $settings->app_name = $request->validated('app_name') ?: null;

        foreach (self::IMAGE_FIELDS as $field => $property) {
            if ($request->boolean("remove_{$field}")) {
                $this->deleteIfPresent($settings->{$property});
                $settings->{$property} = null;

                continue;
            }

            if ($request->hasFile($field)) {
                $this->deleteIfPresent($settings->{$property});
                $settings->{$property} = $request->file($field)->storePublicly('branding');
            }
        }

        $settings->save();

        return redirect()->route('app.admin.branding.edit')->with('success', 'Branding saved.');
    }

    private function deleteIfPresent(?string $path): void
    {
        if ($path) {
            Storage::disk()->delete($path);
        }
    }
}
```

- [ ] **Step 5: Add the routes**

In `routes/web.php`, inside the `['auth','super_admin']` `/admin` group, immediately after the mailer routes (after the `app.admin.mailer.test` line), add:

```php
        Route::get('/branding', [\App\Http\Controllers\Admin\BrandingController::class, 'edit'])->name('app.admin.branding.edit');
        Route::post('/branding', [\App\Http\Controllers\Admin\BrandingController::class, 'update'])->name('app.admin.branding.update');
```

(Use a top-of-file `use App\Http\Controllers\Admin\BrandingController;` instead of the FQCN if the file imports the other admin controllers that way — match the existing import style in `routes/web.php`.)

- [ ] **Step 6: Run the test to verify it passes**

Run: `ddev php artisan test --filter=BrandingControllerTest`
Expected: PASS (6 tests).

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/Admin/BrandingController.php app/Http/Requests/Admin/BrandingSettingsRequest.php routes/web.php tests/Feature/Admin/BrandingControllerTest.php
git commit -m "feat: add branding settings controller, request and routes"
```

---

### Task 3: Share branding to all Inertia pages + TS types

**Files:**
- Modify: `app/Http/Middleware/HandleInertiaRequests.php` (add `branding` to `share()`)
- Modify: `resources/js/types/index.d.ts` (add `branding` to `PageProps`)
- Test: `tests/Feature/Admin/BrandingShareTest.php`

**Interfaces:**
- Consumes: `App\Settings\BrandingSettings` (Task 1).
- Produces: Inertia shared prop `branding: { appName: string, logoLight: string|null, logoDark: string|null, favicon: string|null }`, available to every page.

- [ ] **Step 1: Write the failing test**

`tests/Feature/Admin/BrandingShareTest.php`:

```php
<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Settings\BrandingSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class BrandingShareTest extends TestCase
{
    use RefreshDatabase;

    public function test_branding_is_shared_with_every_page(): void
    {
        $settings = app(BrandingSettings::class);
        $settings->app_name = 'Acme Links';
        $settings->save();

        $user = User::factory()->create();
        $user->super_admin = true;
        $user->save();

        $this->actingAs($user)
            ->get(route('app.admin.branding.edit'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('branding.appName', 'Acme Links')
                ->where('branding.logoLight', null)
                ->where('branding.favicon', null));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `ddev php artisan test --filter=BrandingShareTest`
Expected: FAIL — `branding` prop is missing.

- [ ] **Step 3: Add `branding` to the share() method**

In `app/Http/Middleware/HandleInertiaRequests.php`, add the import at the top:

```php
use App\Settings\BrandingSettings;
```

Then add a `branding` key to the array returned by `share()` (after the `flash` key):

```php
            'branding' => $this->branding(),
```

And add this private method to the class:

```php
    /**
     * @return array<string, string|null>
     */
    private function branding(): array
    {
        try {
            $b = app(BrandingSettings::class);

            return [
                'appName' => $b->appName(),
                'logoLight' => $b->logoLightUrl(),
                'logoDark' => $b->logoDarkUrl(),
                'favicon' => $b->faviconUrl(),
            ];
        } catch (\Throwable) {
            // Settings table not migrated yet — fall back to defaults.
            return ['appName' => 'Marketix', 'logoLight' => null, 'logoDark' => null, 'favicon' => null];
        }
    }
```

- [ ] **Step 4: Add the TypeScript type**

In `resources/js/types/index.d.ts`, add to the `PageProps` intersection type (after the `version: string;` line):

```ts
  branding: {
    appName: string;
    logoLight: string | null;
    logoDark: string | null;
    favicon: string | null;
  };
```

- [ ] **Step 5: Run the test to verify it passes**

Run: `ddev php artisan test --filter=BrandingShareTest`
Expected: PASS.

- [ ] **Step 6: Verify the frontend still type-checks**

Run: `ddev npm run build`
Expected: build succeeds (no TS errors).

- [ ] **Step 7: Commit**

```bash
git add app/Http/Middleware/HandleInertiaRequests.php resources/js/types/index.d.ts tests/Feature/Admin/BrandingShareTest.php
git commit -m "feat: share branding props to all Inertia pages"
```

---

### Task 4: BrandingServiceProvider + Blade head (title + favicon) + client title

**Files:**
- Create: `app/Providers/BrandingServiceProvider.php`
- Modify: `bootstrap/providers.php` (register the provider last)
- Modify: `resources/views/app.blade.php` (add app-name meta + favicon link)
- Modify: `resources/js/app.tsx` (read app name from meta tag at runtime)
- Test: `tests/Feature/Admin/BrandingHeadTest.php`

**Interfaces:**
- Consumes: `App\Settings\BrandingSettings` (Task 1).
- Produces: `config('app.name')` reflects the custom app name; Blade view variables `$brandFaviconUrl` and `$brandEmailLogoUrl` (string|null) shared to all views.

- [ ] **Step 1: Write the failing test**

`tests/Feature/Admin/BrandingHeadTest.php`:

```php
<?php

namespace Tests\Feature\Admin;

use App\Settings\BrandingSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BrandingHeadTest extends TestCase
{
    use RefreshDatabase;

    public function test_custom_app_name_drives_config_and_head_title(): void
    {
        $settings = app(BrandingSettings::class);
        $settings->app_name = 'Acme Links';
        $settings->save();

        // Re-apply now that the value is saved (provider runs at boot, before save).
        (new \App\Providers\BrandingServiceProvider($this->app))->bootForTesting();

        $this->assertSame('Acme Links', config('app.name'));

        $this->get('/login')
            ->assertOk()
            ->assertSee('Acme Links', false)            // <title> / meta app-name
            ->assertSee('name="app-name"', false);
    }
}
```

> Note: `/login` is the guest login route (see `routes/web.php` guest group). If its path differs, use the guest login URL from `routes/web.php`.

- [ ] **Step 2: Run test to verify it fails**

Run: `ddev php artisan test --filter=BrandingHeadTest`
Expected: FAIL — `App\Providers\BrandingServiceProvider` not found.

- [ ] **Step 3: Create the provider**

`app/Providers/BrandingServiceProvider.php`:

```php
<?php

namespace App\Providers;

use App\Settings\BrandingSettings;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Laravel\Octane\Events\RequestReceived;
use Throwable;

class BrandingServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->applyBranding();

        // Re-apply per queued job and per Octane request — the worker/container
        // persists, so without this it would serve stale branding after a save.
        Event::listen(JobProcessing::class, fn () => $this->applyBranding());
        Event::listen(RequestReceived::class, fn () => $this->applyBranding());
    }

    /** Test helper: re-run after saving settings mid-test. */
    public function bootForTesting(): void
    {
        $this->applyBranding();
    }

    private function applyBranding(): void
    {
        try {
            $b = $this->app->make(BrandingSettings::class)->refresh();

            config(['app.name' => $b->appName()]);
            View::share('brandFaviconUrl', $b->faviconUrl());
            View::share('brandEmailLogoUrl', $b->emailLogoUrl());
        } catch (Throwable) {
            // Settings unavailable (table not migrated, no DB) — keep config/.env defaults.
        }
    }
}
```

- [ ] **Step 4: Register the provider (last, so it wins for config('app.name'))**

In `bootstrap/providers.php`:

```php
<?php

use App\Providers\AppServiceProvider;
use App\Providers\BrandingServiceProvider;
use App\Providers\HorizonServiceProvider;
use App\Providers\MailSettingsServiceProvider;

return [
    AppServiceProvider::class,
    HorizonServiceProvider::class,
    MailSettingsServiceProvider::class,
    BrandingServiceProvider::class,
];
```

- [ ] **Step 5: Wire the Blade head**

In `resources/views/app.blade.php`, add a favicon link and an app-name meta tag. Place the meta right after the existing viewport meta (line 5), and the favicon link right after it:

```blade
        <meta name="app-name" content="{{ config('app.name', 'Laravel') }}">
        <link rel="icon" href="{{ $brandFaviconUrl ?? '/favicon.ico' }}">
```

(The existing `<title inertia>{{ config('app.name', 'Laravel') }}</title>` now resolves to the custom name automatically — leave it unchanged.)

- [ ] **Step 6: Make the client-side title use the runtime app name**

In `resources/js/app.tsx`, replace the `appName` constant (line 8):

```ts
const appName =
    document.querySelector('meta[name="app-name"]')?.getAttribute('content') ||
    import.meta.env.VITE_APP_NAME ||
    'Laravel';
```

- [ ] **Step 7: Run tests + build to verify they pass**

Run: `ddev php artisan test --filter=BrandingHeadTest`
Expected: PASS.

Run: `ddev npm run build`
Expected: build succeeds.

- [ ] **Step 8: Commit**

```bash
git add app/Providers/BrandingServiceProvider.php bootstrap/providers.php resources/views/app.blade.php resources/js/app.tsx tests/Feature/Admin/BrandingHeadTest.php
git commit -m "feat: apply branding to app name, head title and favicon"
```

---

### Task 5: `<Brand>` component + refactor the five UI call sites

**Files:**
- Create: `resources/js/Components/Brand.tsx`
- Modify: `resources/js/Components/Sidebar.tsx` (lines 30-33)
- Modify: `resources/js/Components/AdminSidebar.tsx` (lines 20-23)
- Modify: `resources/js/Layouts/GuestLayout.tsx` (lines 17-21 desktop, 34-37 mobile)
- Modify: `resources/js/Layouts/ProfileLayout.tsx` (lines 13-16)
- Modify: `resources/js/Pages/ChooseProject.tsx` (lines 41-44)

**Interfaces:**
- Consumes: `branding` shared prop + `PageProps` type (Task 3).
- Produces: `<Brand>` default export taking `{ className?, iconClassName?, textClassName?, logoClassName?, forceLogo?: 'light'|'dark', suffix?: string }`. Renders logo image(s) when set (theme-toggled via Tailwind, or forced), else `Link2` icon + app name. Optional `suffix` (e.g. "Admin") appended in text mode / shown beside the logo.

- [ ] **Step 1: Create the Brand component**

`resources/js/Components/Brand.tsx`:

```tsx
import { PageProps } from '@/types';
import { usePage } from '@inertiajs/react';
import { Link2 } from 'lucide-react';

interface BrandProps {
  className?: string;
  iconClassName?: string;
  textClassName?: string;
  logoClassName?: string;
  forceLogo?: 'light' | 'dark';
  suffix?: string;
}

export default function Brand({
  className = 'flex items-center gap-2',
  iconClassName = 'h-5 w-5 text-indigo-600',
  textClassName = 'text-sm font-semibold text-slate-900 dark:text-white',
  logoClassName = 'h-6 w-auto',
  forceLogo,
  suffix,
}: BrandProps) {
  const { branding } = usePage<PageProps>().props;
  const { appName, logoLight, logoDark } = branding;
  const hasLogo = Boolean(logoLight || logoDark);

  return (
    <span className={className}>
      {hasLogo ? (
        forceLogo === 'dark' ? (
          <img src={(logoDark || logoLight)!} alt={appName} className={logoClassName} />
        ) : forceLogo === 'light' ? (
          <img src={(logoLight || logoDark)!} alt={appName} className={logoClassName} />
        ) : (
          <>
            <img src={(logoLight || logoDark)!} alt={appName} className={`${logoClassName} block dark:hidden`} />
            <img src={(logoDark || logoLight)!} alt={appName} className={`${logoClassName} hidden dark:block`} />
          </>
        )
      ) : (
        <>
          <Link2 className={iconClassName} />
          <span className={textClassName}>{appName}</span>
        </>
      )}
      {suffix && <span className={textClassName}>{suffix}</span>}
    </span>
  );
}
```

- [ ] **Step 2: Refactor `Sidebar.tsx`**

Add `import Brand from './Brand';` to the imports, drop the now-unused `Link2` from the lucide import on line 2 **only if** `Link2` is not used elsewhere in the file (it is not — verify). Replace the logo block (lines 30-33):

```tsx
      <div className="flex h-14 items-center border-b border-slate-200 px-4 dark:border-slate-800">
        <Brand />
      </div>
```

- [ ] **Step 3: Refactor `AdminSidebar.tsx`**

Add `import Brand from './Brand';`. Remove `Link2` from the lucide import on line 2 (it's only used in the logo block). Replace lines 20-23:

```tsx
      <div className="flex h-14 items-center border-b border-slate-200 px-4 dark:border-slate-800">
        <Brand suffix="Admin" />
      </div>
```

- [ ] **Step 4: Refactor `GuestLayout.tsx`**

Add `import Brand from '@/Components/Brand';`. Remove the `Link2` import (line 3). Replace the desktop branding block (lines 18-21):

```tsx
        <Brand
          forceLogo="dark"
          className="flex items-center gap-2 text-lg font-semibold"
          iconClassName="h-5 w-5 text-indigo-400"
          textClassName="text-lg font-semibold text-white"
        />
```

Replace the mobile logo block (lines 34-37):

```tsx
          <Brand
            className="mb-8 flex items-center gap-2 text-lg font-semibold text-slate-900 lg:hidden dark:text-white"
            iconClassName="h-5 w-5 text-indigo-500"
            textClassName="text-lg font-semibold"
          />
```

- [ ] **Step 5: Refactor `ProfileLayout.tsx`**

Add `import Brand from '@/Components/Brand';`. Remove `Link2` from the lucide import (keep `ArrowLeft`). Replace lines 13-16:

```tsx
      <header className="flex h-14 items-center border-b border-slate-200 bg-white px-4 dark:border-slate-800 dark:bg-slate-900">
        <Brand />
      </header>
```

- [ ] **Step 6: Refactor `ChooseProject.tsx`**

Add `import Brand from '@/Components/Brand';`. Remove `Link2` from the lucide import (keep `Search`). Replace lines 41-44:

```tsx
                <Brand
                  className="flex items-center gap-2 text-lg font-semibold text-slate-900 dark:text-white"
                  iconClassName="h-5 w-5 text-indigo-500"
                  textClassName="text-lg font-semibold"
                />
```

- [ ] **Step 7: Build to verify everything type-checks and compiles**

Run: `ddev npm run build`
Expected: build succeeds with no TS/ESLint errors (no unused `Link2` imports left behind).

- [ ] **Step 8: Commit**

```bash
git add resources/js/Components/Brand.tsx resources/js/Components/Sidebar.tsx resources/js/Components/AdminSidebar.tsx resources/js/Layouts/GuestLayout.tsx resources/js/Layouts/ProfileLayout.tsx resources/js/Pages/ChooseProject.tsx
git commit -m "feat: render brand via shared Brand component across UI"
```

---

### Task 6: Emails, PDF report cover, and TestMail subject

**Files:**
- Modify: `resources/views/mail/project-invitation.blade.php` (line 4)
- Modify: `resources/views/mail/test.blade.php` (line 4)
- Modify: `app/Mail/TestMail.php` (line 18, subject)
- Modify: `resources/views/reports/layout.blade.php` (line 29, cover brand)
- Test: `tests/Feature/Admin/BrandingTemplatesTest.php`

**Interfaces:**
- Consumes: `config('app.name')` and the shared `$brandEmailLogoUrl` view variable (Task 4).

- [ ] **Step 1: Write the failing test**

`tests/Feature/Admin/BrandingTemplatesTest.php`:

```php
<?php

namespace Tests\Feature\Admin;

use App\Reports\ReportData;
use App\Settings\BrandingSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BrandingTemplatesTest extends TestCase
{
    use RefreshDatabase;

    private function setBrand(string $name): void
    {
        $settings = app(BrandingSettings::class);
        $settings->app_name = $name;
        $settings->save();
        config(['app.name' => $settings->appName()]);
    }

    public function test_invitation_email_uses_custom_app_name(): void
    {
        $this->setBrand('Acme Links');

        $html = view('mail.project-invitation', [
            'projectName' => 'Demo',
            'acceptUrl' => 'https://example.com/invitations/abc',
        ])->render();

        $this->assertStringContainsString('Acme Links', $html);
        $this->assertStringNotContainsString('on Marketix', $html);
    }

    public function test_report_cover_uses_custom_app_name(): void
    {
        $this->setBrand('Acme Links');

        $data = new ReportData(
            scope: 'project',
            title: 'Statistics report — Acme',
            subtitle: 'Acme',
            rangeLabel: 'Last 30 days',
            generatedAt: '18 Jun 2026, 12:00',
            totalClicks: 1,
            uniqueClicks: 1,
            timeSeries: [['date' => '2026-06-18', 'clicks' => 1, 'unique' => 1]],
            breakdowns: ['country' => [], 'city' => [], 'browser' => [], 'os' => [], 'domain' => []],
            topLinks: [],
            recentClicks: [],
        );

        $html = view('reports.project', $data->toArray())->render();

        $this->assertStringContainsString('Acme Links', $html);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `ddev php artisan test --filter=BrandingTemplatesTest`
Expected: FAIL — templates still say "Marketix".

- [ ] **Step 3: Update the invitation email**

In `resources/views/mail/project-invitation.blade.php`, change line 4 from:

```blade
You've been invited to join **{{ $projectName }}** on Marketix.
```

to:

```blade
You've been invited to join **{{ $projectName }}** on {{ config('app.name') }}.
```

And add, immediately after the opening `<x-mail::message>` tag (line 1), an optional email logo:

```blade
@if(!empty($brandEmailLogoUrl))
<img src="{{ $brandEmailLogoUrl }}" alt="{{ config('app.name') }}" style="max-height:40px;margin-bottom:16px">
@endif
```

- [ ] **Step 4: Update the test email body**

In `resources/views/mail/test.blade.php`, change line 4 from:

```blade
This is a test email sent from the Marketix admin mailer settings. If you received it, your mail configuration is working.
```

to:

```blade
This is a test email sent from the {{ config('app.name') }} admin mailer settings. If you received it, your mail configuration is working.
```

- [ ] **Step 5: Update the TestMail subject**

In `app/Mail/TestMail.php`, change the envelope subject (line 18) from:

```php
            subject: 'Marketix test email',
```

to:

```php
            subject: config('app.name').' test email',
```

- [ ] **Step 6: Update the PDF report cover**

In `resources/views/reports/layout.blade.php`, replace the brand div (line 29):

```blade
        <div class="brand">Marketix</div>
```

with:

```blade
        <div class="brand">
            @if(!empty($brandEmailLogoUrl))
                <img src="{{ $brandEmailLogoUrl }}" alt="{{ config('app.name') }}" style="max-height:48px">
            @else
                {{ config('app.name') }}
            @endif
        </div>
```

> The PDF engine is Spatie Laravel PDF with a Browsershot/Chrome driver (`config/laravel-pdf.php`), which loads remote (S3) images fine. No extra config needed.

- [ ] **Step 7: Run the test to verify it passes**

Run: `ddev php artisan test --filter=BrandingTemplatesTest`
Expected: PASS (2 tests).

- [ ] **Step 8: Run the existing mail/report tests to confirm no regressions**

Run: `ddev php artisan test --filter="ReportTemplateRenderTest|MailerSettingsControllerTest|InvitationAcceptTest"`
Expected: PASS.

- [ ] **Step 9: Commit**

```bash
git add resources/views/mail/project-invitation.blade.php resources/views/mail/test.blade.php app/Mail/TestMail.php resources/views/reports/layout.blade.php tests/Feature/Admin/BrandingTemplatesTest.php
git commit -m "feat: apply branding to emails and PDF report cover"
```

---

### Task 7: Admin branding page UI + sidebar nav

**Files:**
- Create: `resources/js/Pages/Admin/Branding/Edit.tsx`
- Modify: `resources/js/Components/AdminSidebar.tsx` (add nav item, line 6-11)

**Interfaces:**
- Consumes: routes `app.admin.branding.edit` / `app.admin.branding.update` (Task 2); `AdminLayout`; `flash` shared prop.

- [ ] **Step 1: Create the branding admin page**

`resources/js/Pages/Admin/Branding/Edit.tsx`:

```tsx
import AdminLayout from '@/Layouts/AdminLayout';
import { PageProps } from '@/types';
import { useForm, usePage } from '@inertiajs/react';

interface Props {
  app_name: string | null;
  logo_light_url: string | null;
  logo_dark_url: string | null;
  logo_email_url: string | null;
  favicon_url: string | null;
}

const inputClass =
  'w-full rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white';
const labelClass = 'mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300';

type ImageField = 'logo_light' | 'logo_dark' | 'logo_email' | 'favicon';

export default function AdminBrandingEdit(props: Props) {
  const { flash } = usePage<PageProps>().props;

  const currentUrl: Record<ImageField, string | null> = {
    logo_light: props.logo_light_url,
    logo_dark: props.logo_dark_url,
    logo_email: props.logo_email_url,
    favicon: props.favicon_url,
  };

  const { data, setData, post, processing, errors } = useForm<{
    app_name: string;
    logo_light: File | null;
    logo_dark: File | null;
    logo_email: File | null;
    favicon: File | null;
    remove_logo_light: boolean;
    remove_logo_dark: boolean;
    remove_logo_email: boolean;
    remove_favicon: boolean;
  }>({
    app_name: props.app_name ?? '',
    logo_light: null,
    logo_dark: null,
    logo_email: null,
    favicon: null,
    remove_logo_light: false,
    remove_logo_dark: false,
    remove_logo_email: false,
    remove_favicon: false,
  });

  function submit(e: React.FormEvent) {
    e.preventDefault();
    post(route('app.admin.branding.update'), { forceFormData: true });
  }

  const imageFields: { field: ImageField; remove: keyof typeof data; label: string; hint: string }[] = [
    { field: 'logo_light', remove: 'remove_logo_light', label: 'Logo (light mode)', hint: 'Shown on light backgrounds.' },
    { field: 'logo_dark', remove: 'remove_logo_dark', label: 'Logo (dark mode)', hint: 'Shown on dark backgrounds.' },
    { field: 'logo_email', remove: 'remove_logo_email', label: 'Email / PDF logo', hint: 'Used in emails and PDF reports.' },
    { field: 'favicon', remove: 'remove_favicon', label: 'Favicon', hint: '.ico, .png or .svg.' },
  ];

  return (
    <AdminLayout title="Branding">
      <div className="px-8 py-8">
        <h1 className="mb-6 text-2xl font-bold text-slate-900 dark:text-white">Branding</h1>

        {flash?.success && (
          <div className="mb-4 max-w-md rounded-md bg-green-50 px-4 py-3 text-sm text-green-700 dark:bg-green-900/20 dark:text-green-400">{flash.success}</div>
        )}
        {flash?.error && (
          <div className="mb-4 max-w-md rounded-md bg-red-50 px-4 py-3 text-sm text-red-700 dark:bg-red-900/20 dark:text-red-400">{flash.error}</div>
        )}

        <form onSubmit={submit} className="max-w-md space-y-6">
          <div>
            <label className={labelClass}>Application name</label>
            <input
              value={data.app_name}
              onChange={(e) => setData('app_name', e.target.value)}
              placeholder="Marketix"
              className={inputClass}
            />
            <p className="mt-1 text-xs text-slate-500 dark:text-slate-400">Leave blank to use the default ("Marketix").</p>
            {errors.app_name && <p className="mt-1 text-xs text-red-600">{errors.app_name}</p>}
          </div>

          {imageFields.map(({ field, remove, label, hint }) => (
            <div key={field}>
              <label className={labelClass}>{label}</label>
              {currentUrl[field] && (
                <img src={currentUrl[field]!} alt={label} className="mb-2 h-10 w-auto rounded border border-slate-200 bg-slate-50 p-1 dark:border-slate-700 dark:bg-slate-800" />
              )}
              <input
                type="file"
                accept={field === 'favicon' ? '.ico,.png,.svg,image/*' : 'image/*'}
                onChange={(e) => setData(field, e.target.files?.[0] ?? null)}
                className="block w-full text-sm text-slate-600 file:mr-3 file:rounded-md file:border-0 file:bg-indigo-50 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-indigo-700 dark:text-slate-400 dark:file:bg-indigo-900/30 dark:file:text-indigo-300"
              />
              <p className="mt-1 text-xs text-slate-500 dark:text-slate-400">{hint}</p>
              {currentUrl[field] && (
                <label className="mt-1 flex items-center gap-2 text-xs text-slate-600 dark:text-slate-400">
                  <input
                    type="checkbox"
                    checked={Boolean(data[remove])}
                    onChange={(e) => setData(remove, e.target.checked)}
                  />
                  Remove current
                </label>
              )}
              {errors[field] && <p className="mt-1 text-xs text-red-600">{errors[field]}</p>}
            </div>
          ))}

          <button
            disabled={processing}
            className="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500 disabled:opacity-50"
          >
            Save
          </button>
        </form>
      </div>
    </AdminLayout>
  );
}
```

- [ ] **Step 2: Add the sidebar nav item**

In `resources/js/Components/AdminSidebar.tsx`, import a suitable icon (add `Palette` to the existing lucide import on line 2), and add to the `navItems` array (after the Mailer entry):

```tsx
  { label: 'Branding', icon: Palette, routeName: 'app.admin.branding.edit' },
```

- [ ] **Step 3: Build to verify it compiles**

Run: `ddev npm run build`
Expected: build succeeds.

- [ ] **Step 4: Full test suite green**

Run: `ddev php artisan test`
Expected: all tests pass.

- [ ] **Step 5: Commit**

```bash
git add resources/js/Pages/Admin/Branding/Edit.tsx resources/js/Components/AdminSidebar.tsx
git commit -m "feat: add admin branding settings page and nav"
```

---

## Self-Review

**Spec coverage:**
- App name override → Tasks 1 (storage), 4 (config/title), 5 (UI), 6 (emails/PDF). ✓
- Light/dark logos → Tasks 1, 3, 5 (Tailwind toggle in `<Brand>`). ✓
- Dedicated email logo → Tasks 1, 4 (shared var), 6 (emails + PDF). ✓
- Favicon → Tasks 1, 4 (head link). ✓
- Default-disk storage, public visibility, URL via `Storage::disk()->url()` → Task 1 accessors + Task 2 `storePublicly`. ✓
- Fallback to Marketix defaults when unset → Task 1 (`appName()`), Task 5 (`Link2` fallback), Task 4 (favicon `?? '/favicon.ico'`). ✓
- Super-admin-only → Task 2 (routes in existing `super_admin` group; 403 test). ✓
- Global Inertia share → Task 3. ✓
- Admin page + nav → Task 7. ✓
- Tests with `Storage::fake()` → Task 2. ✓

**Placeholder scan:** No TBD/TODO; all steps contain concrete code/commands. ✓

**Type consistency:** Settings props (`logo_light_path` etc.) and accessor names (`logoLightUrl`, `emailLogoUrl`, `faviconUrl`, `appName`) are used identically across Tasks 1/2/3/4. Shared prop shape `{ appName, logoLight, logoDark, favicon }` matches the TS type in Task 3 and `<Brand>` consumption in Task 5. Controller props (`app_name`, `logo_light_url`, …) match the React page `Props` in Task 7. Image field map keys (`logo_light`/`logo_dark`/`logo_email`/`favicon`) and `remove_*` flags match between the request, controller, and page. ✓
