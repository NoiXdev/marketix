# Hardening Package Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Close the four MUST-level production-hardening gaps: a redirect hot-path index, auth rate-limiting, bounded `statistics` growth, and redirect-correctness tests.

**Architecture:** Four independent workstreams, each its own commit/PR: (1) a DB index migration on `domains.name`; (2) in-controller rate limiting on login + throttle middleware on password-reset routes; (3) a configurable retention prune command + daily schedule; (4) feature tests for existing redirect guard logic. No cross-task dependencies.

**Tech Stack:** Laravel 13 / PHP 8.3, MariaDB (DDEV) / SQLite (tests), PHPUnit.

## Global Constraints

- Run ALL php/composer/npm commands through DDEV: `ddev php artisan ...`, `ddev composer ...`. Never bare.
- Backend gate: `ddev php artisan test`. No frontend changes in this plan.
- Tests hitting app routes use `route(...)` or explicit `http://<host>/...` (redirect tests run on the tenant host `links.test`, per the existing `GeoStatisticsTest` pattern); use `RefreshDatabase` and factories.
- Migrations are forward-only in spirit; match the style of existing migrations under `database/migrations/`.
- Login throttle: **5 attempts/minute keyed on `email + IP`**, reset on successful login, implemented **in `AuthController::login`** via the `RateLimiter` facade (Fortify-style) so it surfaces as an Inertia validation error on the `email` field.
- Forgot-password + reset-submit: **5 requests/minute per IP** via `throttle:5,1` middleware.
- `domains.name` gets a **plain index** (not unique): the column has no existing uniqueness and enforcing it would be a semantics/integrity change outside this package's scope.
- Statistics retention: `env('STATISTICS_RETENTION_MONTHS', 12)`; prune **hard-deletes** (bypasses SoftDeletes) rows older than the cutoff; `urls.clicks`/`urls.unique_clicks` counters must remain untouched.

## File Structure

- `database/migrations/2026_07_30_000000_add_index_to_domains_name.php` — **create** (Task 1)
- `app/Http/Controllers/AuthController.php` — **modify** login() + add throttle key helper (Task 2)
- `routes/web.php` — **modify** forgot/reset routes add throttle middleware (Task 2)
- `config/statistics.php` — **create** (Task 3)
- `app/Console/Commands/PruneStatistics.php` — **create** (Task 3)
- `routes/console.php` — **modify** add daily schedule (Task 3)
- Tests: `tests/Feature/DomainsNameIndexTest.php` (T1), `tests/Feature/AuthThrottleTest.php` (T2), `tests/Feature/PruneStatisticsTest.php` (T3), `tests/Feature/RedirectGuardsTest.php` (T4)

---

## Task 1: Index on `domains.name`

**Files:**
- Create: `database/migrations/2026_07_30_000000_add_index_to_domains_name.php`
- Test: `tests/Feature/DomainsNameIndexTest.php`

**Interfaces:**
- Produces: an index named `domains_name_index` covering column `name` on the `domains` table.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/DomainsNameIndexTest.php`:

```php
<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DomainsNameIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_domains_name_column_is_indexed(): void
    {
        $indexes = collect(Schema::getIndexes('domains'));

        $this->assertTrue(
            $indexes->contains(fn (array $i) => in_array('name', $i['columns'], true)),
            'Expected an index covering domains.name',
        );
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `ddev php artisan test --filter=DomainsNameIndexTest`
Expected: FAIL — no index covers `domains.name`.

- [ ] **Step 3: Create the migration**

Create `database/migrations/2026_07_30_000000_add_index_to_domains_name.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('domains', function (Blueprint $table) {
            // Redirect hot-path resolves the host via Domain::where('name', $host);
            // this backs that lookup. Plain (non-unique) index — the column has no
            // enforced uniqueness and adding it would change tenant semantics.
            $table->index('name', 'domains_name_index');
        });
    }

    public function down(): void
    {
        Schema::table('domains', function (Blueprint $table) {
            $table->dropIndex('domains_name_index');
        });
    }
};
```

- [ ] **Step 4: Run test to verify it passes**

Run: `ddev php artisan test --filter=DomainsNameIndexTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add database/migrations/2026_07_30_000000_add_index_to_domains_name.php tests/Feature/DomainsNameIndexTest.php
git commit -m "perf: index domains.name for redirect host lookup"
```

---

## Task 2: Auth rate limiting

**Files:**
- Modify: `app/Http/Controllers/AuthController.php` (login method + new private `throttleKey`)
- Modify: `routes/web.php:68` and `routes/web.php:70` (forgot + reset POST routes)
- Test: `tests/Feature/AuthThrottleTest.php`

**Interfaces:**
- Consumes: existing routes `app.auth.login`, `app.auth.forgot`, `app.auth.reset`.
- Produces: login locks after 5 failed attempts/min (validation error on `email`), counter clears on success; forgot/reset return HTTP 429 after 5/min.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/AuthThrottleTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AuthThrottleTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_is_throttled_after_five_failed_attempts(): void
    {
        User::factory()->create(['email' => 'user@test.dev', 'password' => Hash::make('correct-horse')]);

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('app.auth.login'), ['email' => 'user@test.dev', 'password' => 'wrong']);
        }

        $response = $this->post(route('app.auth.login'), ['email' => 'user@test.dev', 'password' => 'wrong']);

        $response->assertSessionHasErrors('email');
        $this->assertStringContainsString('Too many', session('errors')->first('email'));
    }

    public function test_successful_login_clears_the_rate_limiter(): void
    {
        User::factory()->create(['email' => 'user@test.dev', 'password' => Hash::make('correct-horse')]);

        $this->post(route('app.auth.login'), ['email' => 'user@test.dev', 'password' => 'wrong']);
        $this->post(route('app.auth.login'), ['email' => 'user@test.dev', 'password' => 'wrong']);

        $this->post(route('app.auth.login'), ['email' => 'user@test.dev', 'password' => 'correct-horse'])
            ->assertRedirect('/');

        // Key must match AuthController::throttleKey (lower(email) . '|' . ip).
        $key = 'user@test.dev|127.0.0.1';
        $this->assertSame(0, RateLimiter::attempts($key));
    }

    public function test_forgot_password_is_throttled_per_minute(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post(route('app.auth.forgot'), ['email' => 'user@test.dev']);
        }

        $this->post(route('app.auth.forgot'), ['email' => 'user@test.dev'])
            ->assertStatus(429);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `ddev php artisan test --filter=AuthThrottleTest`
Expected: FAIL — no throttling yet (login never returns a "Too many" error; forgot never 429s).

- [ ] **Step 3: Add rate limiting to `AuthController::login`**

In `app/Http/Controllers/AuthController.php`, add imports at the top (after the existing `use` lines):

```php
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
```

Replace the `login` method body so it checks/consumes the limiter, and add a private `throttleKey` helper. The method becomes:

```php
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $key = $this->throttleKey($request);

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);

            throw ValidationException::withMessages([
                'email' => "Too many login attempts. Please try again in {$seconds} seconds.",
            ]);
        }

        if (! Auth::validate($credentials)) {
            RateLimiter::hit($key); // 1 decay minute (default)

            return back()->withErrors([
                'email' => 'These credentials do not match our records.',
            ])->onlyInput('email');
        }

        RateLimiter::clear($key);

        $user = Auth::getProvider()->retrieveByCredentials($credentials);

        if ($user->hasTwoFactorEnabled()) {
            $request->session()->put('auth.2fa.pending_id', $user->getKey());
            $request->session()->put('auth.2fa.remember', $request->boolean('remember'));

            return redirect()->route('app.auth.two-factor.show');
        }

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        ActivityRecorder::security('login', $user);

        return redirect('/');
    }

    private function throttleKey(Request $request): string
    {
        return Str::lower((string) $request->input('email')).'|'.$request->ip();
    }
```

- [ ] **Step 4: Add throttle middleware to the password-reset routes**

In `routes/web.php`, add `->middleware('throttle:5,1')` to the forgot and reset POST routes:

```php
        Route::post('/auth/forgot-password', [PasswordResetController::class, 'sendLink'])
            ->middleware('throttle:5,1')
            ->name('app.auth.forgot');
```

and

```php
        Route::post('/auth/reset-password', [PasswordResetController::class, 'reset'])
            ->middleware('throttle:5,1')
            ->name('app.auth.reset');
```

(Leave the GET routes and the 2FA routes unchanged.)

- [ ] **Step 5: Run tests to verify they pass**

Run: `ddev php artisan test --filter=AuthThrottleTest`
Expected: PASS (all three).

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/AuthController.php routes/web.php tests/Feature/AuthThrottleTest.php
git commit -m "security: rate-limit login and password-reset routes"
```

---

## Task 3: Statistics pruning

**Files:**
- Create: `config/statistics.php`
- Create: `app/Console/Commands/PruneStatistics.php`
- Modify: `routes/console.php` (add daily schedule)
- Test: `tests/Feature/PruneStatisticsTest.php`

**Interfaces:**
- Consumes: `App\Models\Statistic` (HasUlids + SoftDeletes), `StatisticFactory` (`forUrl()` state).
- Produces: artisan command `statistics:prune`; config key `statistics.retention_months`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/PruneStatisticsTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Enums\RedirectType;
use App\Enums\UrlStatus;
use App\Models\Domain;
use App\Models\Project;
use App\Models\Statistic;
use App\Models\Url;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class PruneStatisticsTest extends TestCase
{
    use RefreshDatabase;

    private function makeUrl(int $clicks = 0): Url
    {
        $user = User::factory()->create();
        $project = Project::create(['name' => 'Acme']);
        $domain = Domain::create(['project_id' => $project->id, 'name' => 'links.test']);

        return Url::create([
            'project_id' => $project->id,
            'domain_id' => $domain->id,
            'user_id' => $user->id,
            'slug' => 'promo',
            'url' => 'https://example.com',
            'type' => RedirectType::cases()[0],
            'status' => UrlStatus::ACTIVATED,
            'archived' => false,
            'clicks' => $clicks,
        ]);
    }

    public function test_prunes_rows_older_than_retention_and_keeps_recent(): void
    {
        $url = $this->makeUrl();

        $old = Statistic::factory()->forUrl($url)->create(['created_at' => now()->subMonths(13)]);
        $recent = Statistic::factory()->forUrl($url)->create(['created_at' => now()->subDays(5)]);

        Artisan::call('statistics:prune');

        $this->assertDatabaseMissing('statistics', ['id' => $old->id]);
        $this->assertDatabaseHas('statistics', ['id' => $recent->id]);
    }

    public function test_respects_configured_retention_and_is_idempotent(): void
    {
        config(['statistics.retention_months' => 1]);
        $url = $this->makeUrl();

        $twoMonths = Statistic::factory()->forUrl($url)->create(['created_at' => now()->subMonths(2)]);

        Artisan::call('statistics:prune');
        Artisan::call('statistics:prune'); // idempotent — no error on re-run

        $this->assertDatabaseMissing('statistics', ['id' => $twoMonths->id]);
    }

    public function test_does_not_touch_url_click_counters(): void
    {
        $url = $this->makeUrl(clicks: 5);
        Statistic::factory()->forUrl($url)->create(['created_at' => now()->subMonths(13)]);

        Artisan::call('statistics:prune');

        $this->assertSame(5, $url->fresh()->clicks);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `ddev php artisan test --filter=PruneStatisticsTest`
Expected: FAIL — command `statistics:prune` not defined.

- [ ] **Step 3: Create the config file**

Create `config/statistics.php`:

```php
<?php

return [
    /*
     | How many months of raw statistics rows to retain. Rows older than this
     | are hard-deleted by `php artisan statistics:prune` (scheduled daily).
     | Denormalized urls.clicks / urls.unique_clicks counters are unaffected.
     */
    'retention_months' => (int) env('STATISTICS_RETENTION_MONTHS', 12),
];
```

- [ ] **Step 4: Create the command**

Create `app/Console/Commands/PruneStatistics.php`:

```php
<?php

namespace App\Console\Commands;

use App\Models\Statistic;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

class PruneStatistics extends Command
{
    protected $signature = 'statistics:prune';

    protected $description = 'Hard-delete statistics rows older than the configured retention window';

    public function handle(): int
    {
        $months = (int) config('statistics.retention_months', 12);
        $cutoff = now()->subMonths($months)->startOfDay();

        $total = 0;

        // chunkById + forceDelete is DB-portable (SQLite has no DELETE ... LIMIT).
        // Deleting the fetched rows is safe with chunkById because it pages by id.
        Statistic::where('created_at', '<', $cutoff)
            ->select('id')
            ->chunkById(1000, function (Collection $rows) use (&$total) {
                $total += Statistic::whereKey($rows->modelKeys())->forceDelete();
            });

        $this->info("Pruned {$total} statistics rows older than {$cutoff->toDateString()}.");

        return self::SUCCESS;
    }
}
```

- [ ] **Step 5: Schedule it daily**

In `routes/console.php`, add this line after the existing `Schedule::command('activitylog:clean')->daily();` line:

```php
Schedule::command('statistics:prune')->daily();
```

- [ ] **Step 6: Run test to verify it passes**

Run: `ddev php artisan test --filter=PruneStatisticsTest`
Expected: PASS (all three).

- [ ] **Step 7: Commit**

```bash
git add config/statistics.php app/Console/Commands/PruneStatistics.php routes/console.php tests/Feature/PruneStatisticsTest.php
git commit -m "feat: prune statistics older than configurable retention (default 12mo)"
```

---

## Task 4: Redirect correctness tests

**Files:**
- Test: `tests/Feature/RedirectGuardsTest.php`

**Interfaces:**
- Consumes: the redirect fallback route (`RedirectController@handle`) on host `links.test`, and the `redirect.password.check` POST route.
- Produces: coverage only — no production code changes.

- [ ] **Step 1: Write the tests**

Create `tests/Feature/RedirectGuardsTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Enums\RedirectType;
use App\Enums\UrlStatus;
use App\Models\Domain;
use App\Models\Project;
use App\Models\Url;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RedirectGuardsTest extends TestCase
{
    use RefreshDatabase;

    /** @param array<string, mixed> $attributes */
    private function makeUrl(array $attributes = []): Url
    {
        $user = User::factory()->create();
        $project = Project::create(['name' => 'Acme']);
        $domain = Domain::create(['project_id' => $project->id, 'name' => 'links.test']);

        return Url::create(array_merge([
            'project_id' => $project->id,
            'domain_id' => $domain->id,
            'user_id' => $user->id,
            'slug' => 'promo',
            'url' => 'https://example.com/default',
            'type' => RedirectType::cases()[0],
            'status' => UrlStatus::ACTIVATED,
            'archived' => false,
        ], $attributes));
    }

    public function test_expired_link_returns_410(): void
    {
        $this->makeUrl(['expired_at' => now()->subDay()]);

        $this->get('http://links.test/promo')->assertStatus(410);
    }

    public function test_deactivated_link_returns_404(): void
    {
        $this->makeUrl(['status' => UrlStatus::DEACTIVATED]);

        $this->get('http://links.test/promo')->assertStatus(404);
    }

    public function test_archived_link_returns_404(): void
    {
        $this->makeUrl(['archived' => true]);

        $this->get('http://links.test/promo')->assertStatus(404);
    }

    public function test_password_protected_link_gates_and_hides_target(): void
    {
        $this->makeUrl(['password' => Hash::make('secret')]);

        $response = $this->get('http://links.test/promo');

        $response->assertOk(); // shows the password gate view, not a redirect
        $response->assertDontSee('https://example.com/default', false);
    }

    public function test_correct_password_allows_through(): void
    {
        $this->makeUrl(['password' => Hash::make('secret')]);

        $this->post('http://links.test/promo', ['password' => 'secret'])
            ->assertRedirect('https://example.com/default');
    }

    public function test_wrong_password_is_rejected(): void
    {
        $this->makeUrl(['password' => Hash::make('secret')]);

        $this->post('http://links.test/promo', ['password' => 'nope'])
            ->assertSessionHasErrors('password');
    }
}
```

- [ ] **Step 2: Run the tests**

Run: `ddev php artisan test --filter=RedirectGuardsTest`
Expected: PASS — these assert the already-implemented guard behavior in `RedirectController`. If any FAILS, that is a real discrepancy between the guard logic and its intended behavior — STOP and report it (do not weaken the assertion).

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/RedirectGuardsTest.php
git commit -m "test: cover redirect guards (expiry, deactivated, archived, password)"
```

---

## Self-Review Notes

- **Spec coverage:** A → Task 2 (login in-controller limiter + forgot/reset throttle), B → Task 1 (`domains.name` index), C → Task 3 (config + `statistics:prune` + daily schedule + counter-untouched assertion), D → Task 4 (expiry/deactivated/archived/password tests). ✓
- **Ordering:** Tasks are independent; B→A→C→D as recommended, but any order works.
- **DB portability:** Task 3 avoids `DELETE ... LIMIT` (SQLite-incompatible) via `chunkById` + `whereKey(...)->forceDelete()`.
- **Type/name consistency:** throttle key formula `lower(email).'|'.ip` is identical in `AuthController::throttleKey` (Task 2 Step 3) and the test (Task 2 Step 1). Command signature `statistics:prune` matches between command, schedule, and tests. Index name `domains_name_index` matches migration and is covered by the column-based assertion.
- **Login UX:** throttle surfaces as an Inertia validation error on `email` (ValidationException), consistent with the existing `back()->withErrors` flow — not a raw 429.
