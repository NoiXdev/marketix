# Bot Filtering for Click Statistics Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Detect bot/crawler clicks at recording time, tag them with an `is_bot` flag, and exclude them from every click statistic and counter while still redirecting them.

**Architecture:** A `CrawlerDetector` wrapper around `jaybizzle/crawler-detect` classifies each click by User-Agent inside `RecordClickStatisticJob`. Bot clicks are still stored (with `is_bot = true`) but never increment `urls.clicks`/`urls.unique_clicks`; every read path adds `where('is_bot', false)`.

**Tech Stack:** Laravel 13 / PHP 8.3, MariaDB (DDEV) / SQLite (tests), `jaybizzle/crawler-detect`, PHPUnit.

## Global Constraints

- Run ALL php/composer/npm commands through DDEV: `ddev composer ...`, `ddev php artisan test ...`. Never bare.
- Backend gate: `ddev php artisan test`. No frontend changes in this plan.
- Detection: `jaybizzle/crawler-detect` via a `App\Support\CrawlerDetector::isBot(string $ua): bool` wrapper; empty UA → `false`.
- `statistics.is_bot`: boolean, `default(false)`, cast to `boolean` on the model.
- Bot clicks: the row IS stored with `is_bot = true`, but `urls.clicks` / `urls.unique_clicks` are NOT incremented for bots.
- Exclusion (`where('is_bot', false)`) added at: `StatisticsAggregator::base()`, `StatisticsController` `$topLinks`, `DashboardController::clicksByDay`, `ReportDataService::topLinks`.
- Redirect behavior is unchanged — bots are still resolved and redirected.
- Tests: `RefreshDatabase`, factories, `route()` or explicit `http://<host>/...`; copy the login + project-membership helper from `tests/Feature/ClicksByCountryPropTest.php` where an authenticated project page is needed.

## File Structure

- `app/Support/CrawlerDetector.php` — **create** (Task 1)
- `database/migrations/2026_07_31_000000_add_is_bot_to_statistics.php` — **create** (Task 2)
- `app/Models/Statistic.php` — **modify** `$fillable` + add `casts()` (Task 2)
- `database/factories/StatisticFactory.php` — **modify** default + `bot()` state (Task 2)
- `app/Jobs/RecordClickStatisticJob.php` — **modify** `handle()` (Task 3)
- `app/Services/StatisticsAggregator.php` — **modify** `base()` (Task 4)
- `app/Http/Controllers/StatisticsController.php` — **modify** `$topLinks` (Task 4)
- `app/Http/Controllers/DashboardController.php` — **modify** `clicksByDay()` (Task 4)
- `app/Reports/ReportDataService.php` — **modify** `topLinks()` (Task 4)
- Tests: `tests/Unit/CrawlerDetectorTest.php` (T1), `tests/Feature/StatisticIsBotColumnTest.php` (T2), `tests/Feature/RecordClickStatisticJobTest.php` (T3, extend), `tests/Feature/BotStatsExclusionTest.php` (T4)

---

## Task 1: CrawlerDetector wrapper

**Files:**
- Create: `app/Support/CrawlerDetector.php`
- Test: `tests/Unit/CrawlerDetectorTest.php`
- Dependency: `jaybizzle/crawler-detect` (via composer)

**Interfaces:**
- Produces: `App\Support\CrawlerDetector::isBot(string $ua): bool` — `true` for known crawler UAs, `false` for real browsers and for an empty string.

- [ ] **Step 1: Install the dependency**

Run: `ddev composer require jaybizzle/crawler-detect`
Expected: package added to `composer.json` / `composer.lock`, no errors.

- [ ] **Step 2: Write the failing test**

Create `tests/Unit/CrawlerDetectorTest.php`:

```php
<?php

namespace Tests\Unit;

use App\Support\CrawlerDetector;
use PHPUnit\Framework\TestCase;

class CrawlerDetectorTest extends TestCase
{
    public function test_detects_known_crawlers(): void
    {
        $this->assertTrue(CrawlerDetector::isBot(
            'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)'
        ));
        $this->assertTrue(CrawlerDetector::isBot(
            'facebookexternalhit/1.1 (+http://www.facebook.com/externalhit_uatext.php)'
        ));
    }

    public function test_real_browser_is_not_a_bot(): void
    {
        $this->assertFalse(CrawlerDetector::isBot(
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
        ));
    }

    public function test_empty_user_agent_is_not_a_bot(): void
    {
        $this->assertFalse(CrawlerDetector::isBot(''));
    }
}
```

- [ ] **Step 3: Run test to verify it fails**

Run: `ddev php artisan test --filter=CrawlerDetectorTest`
Expected: FAIL — class `App\Support\CrawlerDetector` not found.

- [ ] **Step 4: Implement the wrapper**

Create `app/Support/CrawlerDetector.php`:

```php
<?php

namespace App\Support;

use Jaybizzle\CrawlerDetect\CrawlerDetect;

/**
 * Thin wrapper over jaybizzle/crawler-detect for classifying click traffic.
 * Kept as a static helper to mirror App\Support\UserAgent and stay easy to
 * call from the queued statistic job.
 */
class CrawlerDetector
{
    public static function isBot(string $ua): bool
    {
        if ($ua === '') {
            return false;
        }

        return (new CrawlerDetect)->isCrawler($ua);
    }
}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `ddev php artisan test --filter=CrawlerDetectorTest`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add composer.json composer.lock app/Support/CrawlerDetector.php tests/Unit/CrawlerDetectorTest.php
git commit -m "feat: add CrawlerDetector wrapper over jaybizzle/crawler-detect"
```

---

## Task 2: `is_bot` column, model cast, factory

**Files:**
- Create: `database/migrations/2026_07_31_000000_add_is_bot_to_statistics.php`
- Modify: `app/Models/Statistic.php` (`$fillable` + `casts()`)
- Modify: `database/factories/StatisticFactory.php` (definition default + `bot()` state)
- Test: `tests/Feature/StatisticIsBotColumnTest.php`

**Interfaces:**
- Produces: `statistics.is_bot` boolean column (default false), cast to bool on the `Statistic` model; `StatisticFactory` default `is_bot => false` and a `bot()` state setting it `true`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/StatisticIsBotColumnTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Statistic;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StatisticIsBotColumnTest extends TestCase
{
    use RefreshDatabase;

    public function test_defaults_to_false_and_casts_to_bool(): void
    {
        $stat = Statistic::factory()->create();

        $this->assertFalse($stat->fresh()->is_bot);
    }

    public function test_bot_state_persists_true(): void
    {
        $stat = Statistic::factory()->bot()->create();

        $this->assertTrue($stat->fresh()->is_bot);
        $this->assertDatabaseHas('statistics', ['id' => $stat->id, 'is_bot' => true]);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `ddev php artisan test --filter=StatisticIsBotColumnTest`
Expected: FAIL — unknown column `is_bot` and/or no `bot()` factory state.

- [ ] **Step 3: Create the migration**

Create `database/migrations/2026_07_31_000000_add_is_bot_to_statistics.php`:

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
            // Crawler/bot clicks are recorded but excluded from all counts.
            $table->boolean('is_bot')->default(false)->after('os');
        });
    }

    public function down(): void
    {
        Schema::table('statistics', function (Blueprint $table) {
            $table->dropColumn('is_bot');
        });
    }
};
```

- [ ] **Step 4: Add `is_bot` to fillable and cast it**

In `app/Models/Statistic.php`, add `'is_bot'` to the `$fillable` array (after `'os'`):

```php
        'browser',
        'os',
        'is_bot',
    ];
```

And add a `casts()` method to the class body (the model currently has none):

```php
    protected function casts(): array
    {
        return [
            'is_bot' => 'boolean',
        ];
    }
```

- [ ] **Step 5: Update the factory**

In `database/factories/StatisticFactory.php`, add `is_bot` to the `definition()` return array (after the `'os'` line):

```php
            'os' => $this->faker->randomElement(['Windows', 'macOS', 'Linux', 'Android', 'iOS']),
            'is_bot' => false,
```

And add a `bot()` state method next to the existing `country()` / `countryCode()` states:

```php
    /**
     * Mark the statistic as a bot/crawler click.
     */
    public function bot(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_bot' => true,
        ]);
    }
```

- [ ] **Step 6: Run test to verify it passes**

Run: `ddev php artisan test --filter=StatisticIsBotColumnTest`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add database/migrations/2026_07_31_000000_add_is_bot_to_statistics.php app/Models/Statistic.php database/factories/StatisticFactory.php tests/Feature/StatisticIsBotColumnTest.php
git commit -m "feat: add is_bot column to statistics"
```

---

## Task 3: Record `is_bot` and gate counters in the job

**Files:**
- Modify: `app/Jobs/RecordClickStatisticJob.php` (`handle()`)
- Test: `tests/Feature/RecordClickStatisticJobTest.php` (extend)

**Interfaces:**
- Consumes: `App\Support\CrawlerDetector::isBot()` (Task 1), `statistics.is_bot` (Task 2).
- Produces: bot clicks stored with `is_bot = true` and no counter increment; human clicks stored with `is_bot = false` and `clicks` (+ `unique_clicks` on first daily visit) incremented.

- [ ] **Step 1: Write the failing tests**

Add these two tests to `tests/Feature/RecordClickStatisticJobTest.php` (the file already exists with a `makeUrl()` helper returning a `Url` and imports for `RecordClickStatisticJob`, `Statistic`):

```php
public function test_bot_click_is_flagged_and_does_not_increment_counters(): void
{
    $url = $this->makeUrl();

    (new RecordClickStatisticJob(
        $url->id,
        $url->project_id,
        'hash-bot',
        'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)',
        null,
        'en',
        ['country' => 'Germany', 'city' => 'Berlin', 'country_code' => 'DE'],
    ))->handle();

    $this->assertDatabaseHas('statistics', ['url_id' => $url->id, 'is_bot' => true]);
    $this->assertSame(0, $url->fresh()->clicks);
    $this->assertSame(0, $url->fresh()->unique_clicks);
}

public function test_human_click_is_not_flagged_and_increments_counters(): void
{
    $url = $this->makeUrl();

    (new RecordClickStatisticJob(
        $url->id,
        $url->project_id,
        'hash-human',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        null,
        'en',
        ['country' => 'Germany', 'city' => 'Berlin', 'country_code' => 'DE'],
    ))->handle();

    $this->assertDatabaseHas('statistics', ['url_id' => $url->id, 'is_bot' => false]);
    $this->assertSame(1, $url->fresh()->clicks);
    $this->assertSame(1, $url->fresh()->unique_clicks);
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `ddev php artisan test --filter=RecordClickStatisticJobTest`
Expected: the two new tests FAIL — `is_bot` is never set and the bot click still increments `clicks`.

- [ ] **Step 3: Update `handle()`**

In `app/Jobs/RecordClickStatisticJob.php`, add the import at the top (with the other `use` statements):

```php
use App\Support\CrawlerDetector;
```

Then change `handle()` to detect the bot, store the flag, and gate the increments:

```php
    public function handle(): void
    {
        $isBot = CrawlerDetector::isBot($this->userAgent);

        $isUnique = ! Statistic::where('url_id', $this->urlId)
            ->where('visitor_hash', $this->visitorHash)
            ->where('created_at', '>=', now()->startOfDay())
            ->exists();

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
            'is_bot' => $isBot,
        ]);

        // Bots are recorded but never counted toward click totals.
        if (! $isBot) {
            Url::whereKey($this->urlId)->increment('clicks');
            if ($isUnique) {
                Url::whereKey($this->urlId)->increment('unique_clicks');
            }
        }
    }
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `ddev php artisan test --filter=RecordClickStatisticJobTest`
Expected: PASS (all tests in the file, including the originals).

- [ ] **Step 5: Commit**

```bash
git add app/Jobs/RecordClickStatisticJob.php tests/Feature/RecordClickStatisticJobTest.php
git commit -m "feat: flag bot clicks and exclude them from click counters"
```

---

## Task 4: Exclude bots from all read paths

**Files:**
- Modify: `app/Services/StatisticsAggregator.php` (`base()`)
- Modify: `app/Http/Controllers/StatisticsController.php` (`$topLinks`)
- Modify: `app/Http/Controllers/DashboardController.php` (`clicksByDay`)
- Modify: `app/Reports/ReportDataService.php` (`topLinks`)
- Test: `tests/Feature/BotStatsExclusionTest.php`

**Interfaces:**
- Consumes: `statistics.is_bot` (Task 2); the `Statistic` rows written by Task 3.
- Produces: all click reads exclude `is_bot = true` rows.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/BotStatsExclusionTest.php`. For the authenticated statistics-page assertion, copy the exact login + project-membership setup from `tests/Feature/ClicksByCountryPropTest.php` (open it and reuse its pattern verbatim — do not guess the pivot attach signature):

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
use App\Services\StatisticsAggregator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BotStatsExclusionTest extends TestCase
{
    use RefreshDatabase;

    public function test_aggregations_count_only_human_clicks(): void
    {
        $project = Project::create(['name' => 'Acme']);

        Statistic::factory()->count(3)->forProject($project)
            ->state(['country' => 'Germany', 'country_code' => 'DE'])->create();
        Statistic::factory()->count(2)->forProject($project)->bot()
            ->state(['country' => 'Germany', 'country_code' => 'DE'])->create();

        $agg = app(StatisticsAggregator::class);

        $this->assertSame(3, $agg->totalClicks($project->id, null));
        $this->assertSame(3, $agg->uniqueClicks($project->id, null));
        $this->assertSame(3, (int) $agg->breakdown($project->id, null, 'country')->firstWhere('country', 'Germany')->count);
        $this->assertSame(3, (int) $agg->breakdownByCountryCode($project->id, null)->first()->count);
        $this->assertCount(3, $agg->recentClicks($project->id, null));
    }

    public function test_statistics_page_top_links_excludes_bots(): void
    {
        // Auth + membership: reuse the helper pattern from ClicksByCountryPropTest.
        $user = User::factory()->create();
        $project = Project::create(['name' => 'Acme']);
        $project->users()->attach($user->id, ['permissions' => [], 'active' => true]);

        $domain = Domain::create(['project_id' => $project->id, 'name' => 'links.test']);
        $link = Url::create([
            'project_id' => $project->id,
            'domain_id' => $domain->id,
            'user_id' => $user->id,
            'slug' => 'promo',
            'url' => 'https://example.com',
            'type' => RedirectType::cases()[0],
            'status' => UrlStatus::ACTIVATED,
            'archived' => false,
        ]);

        Statistic::factory()->count(2)->forUrl($link)->create();
        Statistic::factory()->count(1)->forUrl($link)->bot()->create();

        $this->actingAs($user)
            ->get(route('app.project.statistics', ['project' => $project->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Statistics/Index')
                ->has('topLinks', 1, fn (Assert $row) => $row
                    ->where('clicks', 2)
                    ->etc()
                )
            );
    }
}
```

> Note: if the pivot attach signature in `ClicksByCountryPropTest` differs from `['permissions' => [], 'active' => true]`, use whatever that passing test uses. Verify the route name `app.project.statistics` against `routes/web.php` before running.

- [ ] **Step 2: Run tests to verify they fail**

Run: `ddev php artisan test --filter=BotStatsExclusionTest`
Expected: FAIL — aggregations and topLinks currently count the bot rows (4/5 instead of 3/2).

- [ ] **Step 3: Exclude bots in `StatisticsAggregator::base()`**

In `app/Services/StatisticsAggregator.php`, add the `is_bot` filter to `base()`:

```php
    private function base(string $projectId, ?string $urlId): Builder
    {
        return Statistic::query()
            ->where('project_id', $projectId)
            ->where('is_bot', false)
            ->when($urlId !== null, fn (Builder $q) => $q->where('url_id', $urlId));
    }
```

- [ ] **Step 4: Exclude bots in `StatisticsController` topLinks**

In `app/Http/Controllers/StatisticsController.php`, add `->where('statistics.is_bot', false)` to the `$topLinks` query (right after the `->where('statistics.project_id', ...)` line):

```php
        $topLinks = Statistic::where('statistics.project_id', $project->id)
            ->where('statistics.is_bot', false)
            ->join('urls', 'statistics.url_id', '=', 'urls.id')
```

- [ ] **Step 5: Exclude bots in `DashboardController::clicksByDay`**

In `app/Http/Controllers/DashboardController.php`, add `->where('is_bot', false)` to the query in `clicksByDay()` (after the `->where('created_at', '>=', $since)` line):

```php
        $rows = Statistic::where('project_id', $projectId)
            ->where('is_bot', false)
            ->where('created_at', '>=', $since)
```

- [ ] **Step 6: Exclude bots in `ReportDataService::topLinks`**

In `app/Reports/ReportDataService.php`, add `->where('statistics.is_bot', false)` to the `topLinks()` query (after the `->whereBetween('statistics.created_at', ...)` line):

```php
        return Statistic::where('statistics.project_id', $projectId)
            ->where('statistics.is_bot', false)
            ->whereBetween('statistics.created_at', [$range->start(), $range->end()])
```

- [ ] **Step 7: Run tests to verify they pass**

Run: `ddev php artisan test --filter=BotStatsExclusionTest`
Expected: PASS (both tests).

- [ ] **Step 8: Run the full statistics suite (no regressions)**

Run: `ddev php artisan test --filter=Statistic`
Expected: PASS — existing stats tests still green (factory default `is_bot = false`).

- [ ] **Step 9: Commit**

```bash
git add app/Services/StatisticsAggregator.php app/Http/Controllers/StatisticsController.php app/Http/Controllers/DashboardController.php app/Reports/ReportDataService.php tests/Feature/BotStatsExclusionTest.php
git commit -m "feat: exclude bot clicks from all statistics reads"
```

---

## Self-Review Notes

- **Spec coverage:** detection wrapper + dep → Task 1; column/cast/factory → Task 2; job records flag + gates counters → Task 3; exclusion at `base()` + the three raw-query sites, dashboard totals unaffected (bot-free via skipped increment) → Task 4. Redirect unchanged (no task touches `RedirectController`). ✓
- **Ordering:** 1 (detector) and 2 (schema) are independent; 3 depends on both; 4 depends on 2. Sequential 1→2→3→4.
- **DB portability:** boolean default column + `where('is_bot', false)` work on both SQLite (tests) and MariaDB.
- **Type/name consistency:** `CrawlerDetector::isBot(string): bool` is defined in Task 1 and consumed verbatim in Task 3. `is_bot` column/cast (Task 2) is used identically across Tasks 3 and 4. Factory `bot()` state (Task 2) is used in Tasks 3-test data and Task 4 tests.
- **Counter/aggregation consistency:** bots skip `urls.clicks` increment (Task 3), so Dashboard totals reading `urls.sum('clicks')` need no filter; all `statistics`-derived reads get `is_bot = false` (Task 4). Both sides bot-free.
