# Clicks-by-Country Choropleth Map Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a world choropleth map of clicks-by-country to both the project-level statistics dashboard and the per-link stats page.

**Architecture:** Persist the ISO `country_code` that `GeoIpService` already computes (new nullable column + one-line job change), backfill historical rows from country names via ICU, expose a `breakdownByCountryCode()` aggregation as a new `clicksByCountry` Inertia prop on both controllers, and render it with a custom `WorldMap.tsx` choropleth built on `d3-geo` + `topojson-client` + `world-atlas`, bridging geometry numeric ids to alpha-2 via `i18n-iso-countries`.

**Tech Stack:** Laravel 13 / PHP 8.3, React 19 + TypeScript, Inertia.js, recharts (existing), MariaDB/SQLite. New frontend deps: `d3-geo`, `topojson-client`, `world-atlas`, `i18n-iso-countries`.

## Global Constraints

- All `php`/`composer`/`npm` commands MUST run through DDEV: `ddev php`, `ddev composer`, `ddev npm`, `ddev exec`.
- Frontend verification gate is `ddev npm run build` (TypeScript check + Vite). `npm run lint` is broken in this repo — do NOT use it.
- Tests that hit routes must use `route(...)`, never bare path strings (APP_DOMAIN/APP_URL host mismatch gotcha).
- Migrations in this repo are written forward-only in spirit; follow the style of `database/migrations/2026_06_25_000000_replace_ip_with_visitor_hash_on_statistics.php`.
- `country_code` is ISO 3166-1 alpha-2, uppercase, stored as `CHAR(2)` nullable.
- The existing `topCountries` name-based breakdown and its table/bar UI stay untouched on both pages.
- Statistics use ULID primary keys and `SoftDeletes` — do not change that.

---

## File Structure

- `database/migrations/2026_06_25_100000_add_country_code_to_statistics.php` — **create**: add `country_code` column + index.
- `app/Models/Statistic.php` — **modify**: add `country_code` to `$fillable`.
- `database/factories/StatisticFactory.php` — **modify**: default `country_code`, add `countryCode()` state.
- `app/Jobs/RecordClickStatisticJob.php` — **modify**: persist `country_code` from `$geo`.
- `app/Support/CountryCodes.php` — **create**: name→alpha-2 resolver built from ICU.
- `app/Console/Commands/BackfillStatisticCountryCodes.php` — **create**: backfill historical rows.
- `app/Services/StatisticsAggregator.php` — **modify**: add `breakdownByCountryCode()`.
- `app/Http/Controllers/StatisticsController.php` — **modify**: pass `clicksByCountry`.
- `app/Http/Controllers/UrlController.php` — **modify**: pass `clicksByCountry`.
- `resources/js/Components/WorldMap.tsx` — **create**: choropleth component.
- `resources/js/Pages/Statistics/Index.tsx` — **modify**: render `<WorldMap>`.
- `resources/js/Pages/Links/Show.tsx` — **modify**: render `<WorldMap>`.
- Tests: `tests/Feature/RecordClickStatisticJobTest.php` (modify), `tests/Unit/CountryCodesTest.php` (create), `tests/Feature/BackfillStatisticCountryCodesTest.php` (create), `tests/Feature/CountryCodeBreakdownTest.php` (create), `tests/Feature/ClicksByCountryPropTest.php` (create).

---

## Task 1: Add `country_code` column, model fillable, factory support

**Files:**
- Create: `database/migrations/2026_06_25_100000_add_country_code_to_statistics.php`
- Modify: `app/Models/Statistic.php:19-30` (the `$fillable` array)
- Modify: `database/factories/StatisticFactory.php` (definition + new state)
- Test: `tests/Feature/RecordClickStatisticJobTest.php` (extended in Task 2)

**Interfaces:**
- Produces: `statistics.country_code` nullable `CHAR(2)` column; `Statistic` mass-assignable `country_code`; `StatisticFactory::countryCode(string $code)` state and a default `country_code` in `definition()`.

- [ ] **Step 1: Write the failing test**

Add this test to `tests/Feature/RecordClickStatisticJobTest.php`:

```php
public function test_country_code_column_is_persistable(): void
{
    $url = $this->makeUrl();

    \App\Models\Statistic::create([
        'project_id' => $url->project_id,
        'url_id' => $url->id,
        'visitor_hash' => 'hash-cc',
        'country' => 'Germany',
        'country_code' => 'DE',
    ]);

    $this->assertDatabaseHas('statistics', [
        'url_id' => $url->id,
        'country_code' => 'DE',
    ]);
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `ddev php artisan test --filter=test_country_code_column_is_persistable`
Expected: FAIL — unknown column `country_code` (and/or mass-assignment ignored).

- [ ] **Step 3: Create the migration**

Create `database/migrations/2026_06_25_100000_add_country_code_to_statistics.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('statistics', function (Blueprint $table) {
            // ISO 3166-1 alpha-2, resolved by GeoIpService on every click.
            // Nullable: historical rows and rows whose name has no known code stay null.
            $table->char('country_code', 2)->nullable()->after('country');
            $table->index(['project_id', 'url_id', 'country_code'], 'statistics_project_url_country_code_index');
        });
    }

    public function down(): void
    {
        Schema::table('statistics', function (Blueprint $table) {
            $table->dropIndex('statistics_project_url_country_code_index');
            $table->dropColumn('country_code');
        });
    }
};
```

- [ ] **Step 4: Add `country_code` to the model `$fillable`**

In `app/Models/Statistic.php`, change the `$fillable` array to insert `'country_code'` after `'country'`:

```php
    protected $fillable = [
        'project_id',
        'url_id',
        'visitor_hash',
        'country',
        'country_code',
        'city',
        'language',
        'domain',
        'referer',
        'browser',
        'os',
    ];
```

- [ ] **Step 5: Update the factory**

In `database/factories/StatisticFactory.php`, add a `country_code` to `definition()` (right after the `'country'` line):

```php
            'country' => $this->faker->country(),
            'country_code' => $this->faker->countryCode(),
```

And add a new state method next to the existing `country()` method:

```php
    /**
     * Pin the visitor country code (ISO 3166-1 alpha-2) for map breakdowns.
     */
    public function countryCode(string $code): static
    {
        return $this->state(fn (array $attributes) => [
            'country_code' => $code,
        ]);
    }
```

- [ ] **Step 6: Run test to verify it passes**

Run: `ddev php artisan test --filter=test_country_code_column_is_persistable`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add database/migrations/2026_06_25_100000_add_country_code_to_statistics.php app/Models/Statistic.php database/factories/StatisticFactory.php tests/Feature/RecordClickStatisticJobTest.php
git commit -m "feat: add country_code column to statistics"
```

---

## Task 2: Persist `country_code` in RecordClickStatisticJob

**Files:**
- Modify: `app/Jobs/RecordClickStatisticJob.php:44-55` (the `Statistic::create([...])` array)
- Test: `tests/Feature/RecordClickStatisticJobTest.php`

**Interfaces:**
- Consumes: the `$this->geo` array already passed to the job, which contains `country_code` (from `GeoIpService::lookup()`).
- Produces: persisted `country_code` on every recorded click; `null` when absent from the geo array.

- [ ] **Step 1: Write the failing tests**

Add to `tests/Feature/RecordClickStatisticJobTest.php`. First update the existing `dispatch()` helper to include a country code, then add two tests:

```php
public function test_persists_country_code_from_geo(): void
{
    $url = $this->makeUrl();

    (new RecordClickStatisticJob(
        $url->id,
        $url->project_id,
        'hash-geo',
        'UA/1.0',
        null,
        'en',
        ['country' => 'Germany', 'city' => 'Berlin', 'country_code' => 'DE'],
    ))->handle();

    $this->assertDatabaseHas('statistics', [
        'url_id' => $url->id,
        'country_code' => 'DE',
    ]);
}

public function test_country_code_null_when_absent_from_geo(): void
{
    $url = $this->makeUrl();

    (new RecordClickStatisticJob(
        $url->id,
        $url->project_id,
        'hash-nogeo',
        'UA/1.0',
        null,
        'en',
        ['country' => null, 'city' => null],
    ))->handle();

    $this->assertDatabaseHas('statistics', [
        'url_id' => $url->id,
        'country_code' => null,
    ]);
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `ddev php artisan test --filter=RecordClickStatisticJobTest`
Expected: the two new tests FAIL (`country_code` not stored — column stays null even when `DE` is supplied).

- [ ] **Step 3: Persist `country_code` in the job**

In `app/Jobs/RecordClickStatisticJob.php`, add the `country_code` line inside the `Statistic::create([...])` array, immediately after the `'country'` line:

```php
        Statistic::create([
            'project_id' => $this->projectId,
            'url_id' => $this->urlId,
            'visitor_hash' => $this->visitorHash,
            'country' => $this->geo['country'] ?? null,
            'country_code' => $this->geo['country_code'] ?? null,
            'city' => $this->geo['city'] ?? null,
            'language' => $this->language,
            'domain' => $this->referer ? parse_url($this->referer, PHP_URL_HOST) : null,
            'referer' => $this->referer,
            'browser' => UserAgent::browser($this->userAgent),
            'os' => UserAgent::os($this->userAgent),
        ]);
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `ddev php artisan test --filter=RecordClickStatisticJobTest`
Expected: PASS (all tests in the file, including the originals).

- [ ] **Step 5: Commit**

```bash
git add app/Jobs/RecordClickStatisticJob.php tests/Feature/RecordClickStatisticJobTest.php
git commit -m "feat: persist country_code when recording clicks"
```

---

## Task 3: `CountryCodes` name→alpha-2 resolver

**Files:**
- Create: `app/Support/CountryCodes.php`
- Test: `tests/Unit/CountryCodesTest.php`

**Interfaces:**
- Produces: `App\Support\CountryCodes::toAlpha2(?string $name): ?string` — returns uppercase alpha-2 for a known English country name, else `null`. Built from ICU (`ext-intl` is available in this environment), inverted once and cached statically. Case-insensitive on the input name.

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/CountryCodesTest.php`:

```php
<?php

namespace Tests\Unit;

use App\Support\CountryCodes;
use PHPUnit\Framework\TestCase;

class CountryCodesTest extends TestCase
{
    public function test_maps_known_country_names_to_alpha2(): void
    {
        $this->assertSame('DE', CountryCodes::toAlpha2('Germany'));
        $this->assertSame('US', CountryCodes::toAlpha2('United States'));
        $this->assertSame('FR', CountryCodes::toAlpha2('France'));
    }

    public function test_is_case_insensitive(): void
    {
        $this->assertSame('DE', CountryCodes::toAlpha2('germany'));
    }

    public function test_returns_null_for_unknown_or_empty(): void
    {
        $this->assertNull(CountryCodes::toAlpha2('Atlantis'));
        $this->assertNull(CountryCodes::toAlpha2(null));
        $this->assertNull(CountryCodes::toAlpha2(''));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `ddev php artisan test --filter=CountryCodesTest`
Expected: FAIL — class `App\Support\CountryCodes` not found.

- [ ] **Step 3: Implement `CountryCodes`**

Create `app/Support/CountryCodes.php`:

```php
<?php

namespace App\Support;

use Locale;

/**
 * Resolves English country names to ISO 3166-1 alpha-2 codes.
 *
 * The name→code map is built by inverting ICU's English region display names
 * (ext-intl), so it needs no hand-maintained table and tracks the same English
 * names GeoLite2 emits. Used only for best-effort backfill of historical rows;
 * new clicks get their code directly from GeoIpService.
 */
class CountryCodes
{
    /** @var array<string, string>|null name(lower) => alpha-2 */
    private static ?array $map = null;

    public static function toAlpha2(?string $name): ?string
    {
        if ($name === null || trim($name) === '') {
            return null;
        }

        return self::map()[mb_strtolower(trim($name))] ?? null;
    }

    /**
     * @return array<string, string>
     */
    private static function map(): array
    {
        if (self::$map !== null) {
            return self::$map;
        }

        $map = [];
        for ($a = ord('A'); $a <= ord('Z'); $a++) {
            for ($b = ord('A'); $b <= ord('Z'); $b++) {
                $code = chr($a).chr($b);
                $name = Locale::getDisplayRegion('-'.$code, 'en');

                // ICU returns the input code for unassigned regions and
                // "Unknown Region" for ZZ; skip both.
                if ($name === $code || stripos($name, 'Unknown') !== false) {
                    continue;
                }

                $key = mb_strtolower($name);
                // First assignment wins so canonical codes (e.g. GB) aren't
                // overwritten by aliases (e.g. UK) that share a display name.
                if (! isset($map[$key])) {
                    $map[$key] = $code;
                }
            }
        }

        return self::$map = $map;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `ddev php artisan test --filter=CountryCodesTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Support/CountryCodes.php tests/Unit/CountryCodesTest.php
git commit -m "feat: add CountryCodes name-to-alpha2 resolver"
```

---

## Task 4: Backfill command for historical rows

**Files:**
- Create: `app/Console/Commands/BackfillStatisticCountryCodes.php`
- Test: `tests/Feature/BackfillStatisticCountryCodesTest.php`

**Interfaces:**
- Consumes: `App\Support\CountryCodes::toAlpha2()`.
- Produces: artisan command `statistics:backfill-country-codes` that fills `country_code` for rows where it is null but `country` is set; unknown names stay null. Idempotent (only touches null `country_code`).

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/BackfillStatisticCountryCodesTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Statistic;
use App\Models\Url;
use App\Models\User;
use App\Models\Project;
use App\Models\Domain;
use App\Enums\RedirectType;
use App\Enums\UrlStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class BackfillStatisticCountryCodesTest extends TestCase
{
    use RefreshDatabase;

    private function makeUrl(): Url
    {
        $user = User::factory()->create();
        $project = Project::create(['name' => 'Acme']);
        $domain = Domain::create(['project_id' => $project->id, 'name' => 'links.test']);

        return Url::create([
            'project_id' => $project->id,
            'domain_id' => $domain->id,
            'user_id' => $user->id,
            'slug' => 'promo',
            'url' => 'https://example.com/default',
            'type' => RedirectType::cases()[0],
            'status' => UrlStatus::ACTIVATED,
            'archived' => false,
        ]);
    }

    public function test_backfills_known_names_and_leaves_unknown_null(): void
    {
        $url = $this->makeUrl();

        $known = Statistic::create([
            'project_id' => $url->project_id, 'url_id' => $url->id,
            'visitor_hash' => 'h1', 'country' => 'Germany', 'country_code' => null,
        ]);
        $unknown = Statistic::create([
            'project_id' => $url->project_id, 'url_id' => $url->id,
            'visitor_hash' => 'h2', 'country' => 'Atlantis', 'country_code' => null,
        ]);

        Artisan::call('statistics:backfill-country-codes');

        $this->assertSame('DE', $known->fresh()->country_code);
        $this->assertNull($unknown->fresh()->country_code);
    }

    public function test_does_not_overwrite_existing_codes(): void
    {
        $url = $this->makeUrl();

        $row = Statistic::create([
            'project_id' => $url->project_id, 'url_id' => $url->id,
            'visitor_hash' => 'h3', 'country' => 'Germany', 'country_code' => 'XX',
        ]);

        Artisan::call('statistics:backfill-country-codes');

        $this->assertSame('XX', $row->fresh()->country_code);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `ddev php artisan test --filter=BackfillStatisticCountryCodesTest`
Expected: FAIL — command `statistics:backfill-country-codes` not defined.

- [ ] **Step 3: Implement the command**

Create `app/Console/Commands/BackfillStatisticCountryCodes.php`:

```php
<?php

namespace App\Console\Commands;

use App\Models\Statistic;
use App\Support\CountryCodes;
use Illuminate\Console\Command;

class BackfillStatisticCountryCodes extends Command
{
    protected $signature = 'statistics:backfill-country-codes';

    protected $description = 'Fill country_code for historical statistics rows from their country name';

    public function handle(): int
    {
        $updated = 0;

        // Resolve each distinct name once, then bulk-update matching rows.
        $names = Statistic::query()
            ->whereNull('country_code')
            ->whereNotNull('country')
            ->where('country', '!=', '')
            ->distinct()
            ->pluck('country');

        foreach ($names as $name) {
            $code = CountryCodes::toAlpha2($name);
            if ($code === null) {
                continue;
            }

            $updated += Statistic::query()
                ->whereNull('country_code')
                ->where('country', $name)
                ->update(['country_code' => $code]);
        }

        $this->info("Backfilled {$updated} statistics rows.");

        return self::SUCCESS;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `ddev php artisan test --filter=BackfillStatisticCountryCodesTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Console/Commands/BackfillStatisticCountryCodes.php tests/Feature/BackfillStatisticCountryCodesTest.php
git commit -m "feat: add statistics:backfill-country-codes command"
```

---

## Task 5: `breakdownByCountryCode()` aggregation

**Files:**
- Modify: `app/Services/StatisticsAggregator.php` (add a method after `breakdown()`, ~line 143)
- Test: `tests/Feature/CountryCodeBreakdownTest.php`

**Interfaces:**
- Consumes: the private `base()` query helper already in the class.
- Produces: `StatisticsAggregator::breakdownByCountryCode(string $projectId, ?string $urlId, Carbon|CarbonImmutable|null $since = null, Carbon|CarbonImmutable|null $until = null, int $limit = 250): Collection` returning `\stdClass` rows shaped `{ country_code: string, country: string, count: int }`, ordered by count desc, excluding null/empty `country_code`. `country` is the max() representative name for that code.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/CountryCodeBreakdownTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Statistic;
use App\Services\StatisticsAggregator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CountryCodeBreakdownTest extends TestCase
{
    use RefreshDatabase;

    public function test_groups_by_country_code_with_name_and_count(): void
    {
        $project = Project::create(['name' => 'Acme']);

        Statistic::factory()->count(3)->forProject($project)
            ->state(['country' => 'Germany', 'country_code' => 'DE'])->create();
        Statistic::factory()->count(1)->forProject($project)
            ->state(['country' => 'France', 'country_code' => 'FR'])->create();
        // Null code rows must be excluded.
        Statistic::factory()->count(5)->forProject($project)
            ->state(['country' => 'Nowhere', 'country_code' => null])->create();

        $rows = app(StatisticsAggregator::class)
            ->breakdownByCountryCode($project->id, null);

        $this->assertCount(2, $rows);
        $top = $rows->first();
        $this->assertSame('DE', $top->country_code);
        $this->assertSame('Germany', $top->country);
        $this->assertSame(3, (int) $top->count);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `ddev php artisan test --filter=CountryCodeBreakdownTest`
Expected: FAIL — `Call to undefined method ...::breakdownByCountryCode()`.

- [ ] **Step 3: Implement the method**

In `app/Services/StatisticsAggregator.php`, add this method directly after the `breakdown()` method (before `recentClicks()`):

```php
    /**
     * Click counts grouped by ISO country_code, with a representative country
     * name per code, ordered by count desc. Excludes rows without a code.
     * The high default limit returns every country so the choropleth is complete.
     *
     * @return Collection<int, \stdClass>
     */
    public function breakdownByCountryCode(string $projectId, ?string $urlId, Carbon|CarbonImmutable|null $since = null, Carbon|CarbonImmutable|null $until = null, int $limit = 250): Collection
    {
        return $this->base($projectId, $urlId)
            ->when($since, fn (Builder $q) => $q->where('created_at', '>=', $since))
            ->when($until, fn (Builder $q) => $q->where('created_at', '<=', $until))
            ->whereNotNull('country_code')
            ->where('country_code', '!=', '')
            ->select(
                'country_code',
                DB::raw('MAX(country) as country'),
                DB::raw('COUNT(*) as count'),
            )
            ->groupBy('country_code')
            ->orderByDesc('count')
            ->limit($limit)
            ->get();
    }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `ddev php artisan test --filter=CountryCodeBreakdownTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Services/StatisticsAggregator.php tests/Feature/CountryCodeBreakdownTest.php
git commit -m "feat: add breakdownByCountryCode aggregation"
```

---

## Task 6: Expose `clicksByCountry` on both controllers

**Files:**
- Modify: `app/Http/Controllers/StatisticsController.php:34-44` (the `inertia(...)` array)
- Modify: `app/Http/Controllers/UrlController.php:54-85` (the `inertia(...)` array)
- Test: `tests/Feature/ClicksByCountryPropTest.php`

**Interfaces:**
- Consumes: `StatisticsAggregator::breakdownByCountryCode()`.
- Produces: a `clicksByCountry` Inertia prop on both `Statistics/Index` (all-time, matching the existing project-level breakdowns which pass no window) and `Links/Show` (windowed with the existing `$since`).

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/ClicksByCountryPropTest.php`. Mirror the route/auth setup used by the existing `LinkStatsPageTest.php`/`DashboardClicksChartTest.php` — open one of those files first and copy its login + project-membership helper so the `route()` calls resolve. The assertions to add:

```php
<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Statistic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ClicksByCountryPropTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_statistics_includes_clicks_by_country(): void
    {
        // Arrange: a user who belongs to a project with a DE click.
        $user = User::factory()->create();
        $project = Project::create(['name' => 'Acme']);
        $project->users()->attach($user->id, ['permissions' => [], 'active' => true]);
        Statistic::factory()->forProject($project)
            ->state(['country' => 'Germany', 'country_code' => 'DE'])->create();

        $this->actingAs($user)
            ->get(route('app.project.statistics', ['project' => $project->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Statistics/Index')
                ->has('clicksByCountry', 1, fn (Assert $row) => $row
                    ->where('country_code', 'DE')
                    ->where('country', 'Germany')
                    ->where('count', 1)
                )
            );
    }
}
```

> Note: if the project-membership pivot attach signature differs in this repo, copy the exact helper from `tests/Feature/LinkStatsPageTest.php`. Verify the route name `app.project.statistics` against `routes/` before running.

- [ ] **Step 2: Run test to verify it fails**

Run: `ddev php artisan test --filter=ClicksByCountryPropTest`
Expected: FAIL — `clicksByCountry` prop missing.

- [ ] **Step 3: Add the prop to `StatisticsController`**

In `app/Http/Controllers/StatisticsController.php`, add to the `inertia('Statistics/Index', [...])` array, after the `'topCountries'` line:

```php
            'topCountries' => $stats->breakdown($project->id, null, 'country'),
            'clicksByCountry' => $stats->breakdownByCountryCode($project->id, null),
```

- [ ] **Step 4: Add the prop to `UrlController`**

In `app/Http/Controllers/UrlController.php`, add to the `inertia('Links/Show', [...])` array, after the `'topCountries'` line:

```php
            'topCountries' => $stats->breakdown($project->id, $model->id, 'country', $since),
            'clicksByCountry' => $stats->breakdownByCountryCode($project->id, $model->id, $since),
```

- [ ] **Step 5: Run test to verify it passes**

Run: `ddev php artisan test --filter=ClicksByCountryPropTest`
Expected: PASS.

- [ ] **Step 6: Run the full statistics suite (no regressions)**

Run: `ddev php artisan test --filter=Statistic`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/StatisticsController.php app/Http/Controllers/UrlController.php tests/Feature/ClicksByCountryPropTest.php
git commit -m "feat: expose clicksByCountry prop on statistics pages"
```

---

## Task 7: Install frontend map dependencies

**Files:**
- Modify: `package.json` (via `ddev npm install`)

**Interfaces:**
- Produces: `d3-geo`, `topojson-client`, `world-atlas`, `i18n-iso-countries` plus type packages available for import in `WorldMap.tsx`.

- [ ] **Step 1: Install runtime deps**

Run:
```bash
ddev npm install d3-geo topojson-client world-atlas i18n-iso-countries
```
Expected: packages added to `package.json` dependencies, no peer-dep errors (none of these depend on React).

- [ ] **Step 2: Install type deps**

Run:
```bash
ddev npm install -D @types/d3-geo @types/topojson-client
```
Expected: dev types added. (`world-atlas` ships JSON only; `i18n-iso-countries` bundles its own types.)

- [ ] **Step 3: Verify the build still passes**

Run: `ddev npm run build`
Expected: TypeScript check + Vite build succeed with no errors.

- [ ] **Step 4: Commit**

```bash
git add package.json package-lock.json
git commit -m "build: add d3-geo, topojson-client, world-atlas, i18n-iso-countries"
```

---

## Task 8: `WorldMap` choropleth component

**Files:**
- Create: `resources/js/Components/WorldMap.tsx`

**Interfaces:**
- Consumes: `world-atlas/countries-110m.json` (numeric-id geometries), `i18n-iso-countries` (`numericToAlpha2`), `d3-geo` (`geoNaturalEarth1`, `geoPath`), `topojson-client` (`feature`).
- Produces: `export default function WorldMap({ data }: { data: CountryDatum[] })` where `CountryDatum = { country_code: string; country: string; count: number }`. Renders an SVG choropleth with a quantized fill scale, hover tooltip (country + count), a legend, and a stable greyed-out empty state.

- [ ] **Step 1: Create the component**

Create `resources/js/Components/WorldMap.tsx`:

```tsx
import { useMemo, useState } from 'react';
import { geoNaturalEarth1, geoPath } from 'd3-geo';
import { feature } from 'topojson-client';
import type { Topology } from 'topojson-specification';
import type { FeatureCollection, Geometry } from 'geojson';
import { numericToAlpha2 } from 'i18n-iso-countries';
import worldData from 'world-atlas/countries-110m.json';

export interface CountryDatum {
  country_code: string;
  country: string;
  count: number;
}

// 5-step fill ramp, light → indigo (matches the dashboard accent).
const BUCKETS = ['#e0e7ff', '#a5b4fc', '#6366f1', '#4338ca', '#312e81'];
const NO_DATA = '#eef2f6'; // slate-100-ish
const NO_DATA_DARK = '#1e293b';

const projection = geoNaturalEarth1();
const pathGen = geoPath(projection);

// world-atlas countries-110m: numeric string ids in `id`, name in properties.name.
const topo = worldData as unknown as Topology;
const countries = feature(
  topo,
  topo.objects.countries,
) as unknown as FeatureCollection<Geometry, { name: string }>;

interface Props {
  data: CountryDatum[];
}

export default function WorldMap({ data }: Props) {
  const [hover, setHover] = useState<{ name: string; count: number; x: number; y: number } | null>(null);

  const byAlpha2 = useMemo(() => {
    const m = new Map<string, CountryDatum>();
    for (const d of data) m.set(d.country_code.toUpperCase(), d);
    return m;
  }, [data]);

  const max = useMemo(() => data.reduce((acc, d) => Math.max(acc, d.count), 0), [data]);

  // Quantize a count into one of BUCKETS by share of the max.
  function fillFor(count: number): string {
    if (max === 0 || count === 0) return NO_DATA;
    const ratio = count / max;
    const idx = Math.min(BUCKETS.length - 1, Math.floor(ratio * BUCKETS.length));
    return BUCKETS[idx];
  }

  const hasData = max > 0;

  return (
    <div className="rounded-xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
      <div className="mb-4 flex items-center justify-between">
        <h2 className="text-sm font-semibold text-slate-700 dark:text-slate-300">Clicks by country</h2>
        {!hasData && (
          <span className="text-xs text-slate-400">No location data yet</span>
        )}
      </div>

      <div className="relative">
        <svg viewBox="0 0 960 500" className="h-auto w-full" role="img" aria-label="World map of clicks by country">
          <g>
            {countries.features.map((geo, i) => {
              const numericId = String((geo as unknown as { id: string }).id);
              const alpha2 = numericToAlpha2(numericId);
              const datum = alpha2 ? byAlpha2.get(alpha2.toUpperCase()) : undefined;
              const d = pathGen(geo) ?? undefined;
              return (
                <path
                  key={i}
                  d={d}
                  className="stroke-white dark:stroke-slate-900 [&]:dark:opacity-90"
                  strokeWidth={0.4}
                  fill={datum ? fillFor(datum.count) : NO_DATA}
                  onMouseEnter={(e) =>
                    datum &&
                    setHover({
                      name: datum.country,
                      count: datum.count,
                      x: e.nativeEvent.offsetX,
                      y: e.nativeEvent.offsetY,
                    })
                  }
                  onMouseMove={(e) =>
                    datum &&
                    setHover((h) => (h ? { ...h, x: e.nativeEvent.offsetX, y: e.nativeEvent.offsetY } : h))
                  }
                  onMouseLeave={() => setHover(null)}
                />
              );
            })}
          </g>
        </svg>

        {hover && (
          <div
            className="pointer-events-none absolute z-10 rounded-md bg-slate-900 px-2 py-1 text-xs text-white shadow-lg"
            style={{ left: hover.x + 12, top: hover.y + 12 }}
          >
            {hover.name}: {hover.count.toLocaleString()}
          </div>
        )}
      </div>

      {/* Legend */}
      {hasData && (
        <div className="mt-4 flex items-center gap-2 text-xs text-slate-400">
          <span>Fewer</span>
          {BUCKETS.map((c) => (
            <span key={c} className="inline-block h-3 w-6 rounded-sm" style={{ backgroundColor: c }} />
          ))}
          <span>More</span>
        </div>
      )}
    </div>
  );
}
```

> If `topojson-specification` or `geojson` type modules are not resolvable, replace those `import type` lines with local minimal types: `type Topology = { objects: { countries: unknown } }` and treat `countries.features` as `any[]`. Do not add runtime deps for types. The dark-mode `NO_DATA_DARK` constant is referenced by the design's dark empty-state; if unused after implementation, delete it rather than leaving it dead.

- [ ] **Step 2: Verify the build passes**

Run: `ddev npm run build`
Expected: TypeScript check + Vite build succeed. Fix any type errors per the note above (no `any` left behind unless the type module is genuinely absent).

- [ ] **Step 3: Commit**

```bash
git add resources/js/Components/WorldMap.tsx
git commit -m "feat: add WorldMap choropleth component"
```

---

## Task 9: Render `WorldMap` on both statistics pages

**Files:**
- Modify: `resources/js/Pages/Statistics/Index.tsx` (Props interface ~line 12-18, destructure ~line 84-87, render above the breakdown grid ~line 196)
- Modify: `resources/js/Pages/Links/Show.tsx` (Props interface ~line 58-65, destructure ~line 139-141, render above the breakdown grid ~line 296)

**Interfaces:**
- Consumes: the `clicksByCountry` Inertia prop from Task 6 and the `WorldMap` component from Task 8.

- [ ] **Step 1: Wire `WorldMap` into `Statistics/Index.tsx`**

Add the import at the top with the other imports:

```tsx
import WorldMap, { CountryDatum } from '@/Components/WorldMap';
```

Add `clicksByCountry` to the `Props` interface (after the `topCountries` line):

```tsx
  topCountries: (BreakdownRow & { country: string })[];
  clicksByCountry: CountryDatum[];
```

Add it to the destructured props in the component signature:

```tsx
export default function StatisticsIndex({
  days, totalClicks, uniqueClicks, clicksByDay,
  topLinks, topCountries, topBrowsers, topOs, topReferrers,
  clicksByCountry,
}: Props) {
```

Render the map just above the `{/* Breakdown grids */}` block (before the `<div className="grid grid-cols-1 ...">`):

```tsx
        {/* Clicks by country map */}
        <div className="mb-6">
          <WorldMap data={clicksByCountry} />
        </div>

        {/* Breakdown grids */}
```

- [ ] **Step 2: Wire `WorldMap` into `Links/Show.tsx`**

Add the import at the top:

```tsx
import WorldMap, { CountryDatum } from '@/Components/WorldMap';
```

Add `clicksByCountry` to the `Props` interface (after the `topCountries` line):

```tsx
  topCountries: (BreakdownRow & { country: string })[];
  clicksByCountry: CountryDatum[];
```

Add it to the destructured props in the component signature (after `topCountries, topCities, ...`):

```tsx
export default function LinksShow({
  link, days, /* keep the existing names exactly as they are in this signature */
  topCountries, topCities, topBrowsers, topOs, topReferrers, recentClicks,
  clicksByCountry,
}: Props) {
```

> Read the actual destructuring at `resources/js/Pages/Links/Show.tsx:139-141` and append `clicksByCountry` to it verbatim — do not retype the other names from memory.

Render the map immediately above the line containing `<BreakdownChart title={t('links.show.breakdown.countries')} ...>` — wrap it so it spans the full width above that breakdown grid:

```tsx
        {/* Clicks by country map */}
        <div className="mb-6">
          <WorldMap data={clicksByCountry} />
        </div>
```

- [ ] **Step 3: Verify the build passes**

Run: `ddev npm run build`
Expected: TypeScript check + Vite build succeed, no unused-import or type errors.

- [ ] **Step 4: Manual smoke check (optional but recommended)**

Run the dev stack (`ddev composer run dev`), open a project's statistics page and a link's stats page. Confirm: the map renders, hovering a populated country shows the tooltip, and a project with no geo data shows the greyed map with "No location data yet".

- [ ] **Step 5: Commit**

```bash
git add resources/js/Pages/Statistics/Index.tsx resources/js/Pages/Links/Show.tsx
git commit -m "feat: render clicks-by-country map on statistics pages"
```

---

## Task 10: Deploy note — run the backfill

**Files:** none (operational step)

- [ ] **Step 1: After deploying, run the backfill once**

Run: `ddev php artisan statistics:backfill-country-codes`
Expected: prints `Backfilled N statistics rows.` New clicks already store `country_code` directly, so this is a one-time historical fill. The command is idempotent and safe to re-run.

---

## Self-Review Notes

- **Spec coverage:** migration + index (Task 1), write-path persistence (Task 2), backfill via name→code (Tasks 3–4), `breakdownByCountryCode` aggregation (Task 5), `clicksByCountry` on both controllers (Task 6), custom `d3-geo`/`topojson` choropleth component with quantized scale + empty state + tooltip + legend (Tasks 7–8), integration above the existing table breakdown on both pages (Task 9), backfill ops step (Task 10). Existing `topCountries` table untouched. ✓
- **Window semantics:** project-level passes no window (matches existing project breakdowns); link-level passes `$since` (matches existing link breakdowns). ✓
- **No hand-typed country tables:** name→code via ICU; numeric→alpha2 via `i18n-iso-countries`. ✓
- **Type consistency:** `CountryDatum` ({ country_code, country, count }) is defined once in `WorldMap.tsx` and imported by both pages; matches the aggregator's returned shape. `breakdownByCountryCode` signature is identical across Tasks 5 and 6.
