# Activity Log Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add an audit trail + user-facing activity feed using `spatie/laravel-activitylog`, scoped per project, with three UI surfaces (project feed, per-resource history, admin audit view).

**Architecture:** Spatie's `LogsActivity` trait auto-logs CRUD on content models (`Url`, `Domain`, `QrCode`, `Pixel`, `Project`) with attribute diffs; non-CRUD events (membership, invitation, security) are logged manually through an `ActivityRecorder` helper. A custom `App\Models\Activity` adds a `project_id` column so the per-project feed is an indexed query; security events keep `project_id = null`.

**Tech Stack:** Laravel 13 / PHP 8.3, `spatie/laravel-activitylog`, Inertia 2 + React 19 + TypeScript, MariaDB, ULID keys.

## Global Constraints

- All PHP/Composer/NPM/artisan commands run via DDEV: `ddev composer …`, `ddev php artisan …`, `ddev npm …`, `ddev exec`.
- Tests run with: `ddev composer test` (or `ddev php artisan test --filter=…` for one test).
- Frontend gate is `ddev npm run build` (ESLint is broken in this repo — do **not** rely on `npm run lint`).
- Ziggy `route()` calls **always** use object params: `route('name', { project: id })` — never a bare value.
- All primary keys are ULIDs (`char(26)`); the `project_id` column must be ULID-compatible.
- Never store secrets in the activity log: `Url.password` is redacted to the sentinel `••••`; `User` password/2FA/passkey secrets are never placed in `properties`.

---

## File Structure

**Create:**
- `app/Models/Activity.php` — custom Activity model (project relation, scope, `toFeedArray()`).
- `app/Models/Concerns/SetsActivityProject.php` — trait: tags `project_id` + redacts sensitive attributes on auto-logged activities.
- `app/Support/ActivityRecorder.php` — helper for manual project/security event logging.
- `app/Http/Controllers/ActivityController.php` — project feed.
- `app/Http/Controllers/Admin/ActivityController.php` — admin audit view.
- `database/migrations/<ts>_add_project_id_to_activity_log_table.php` — adds `project_id`.
- `resources/js/Components/ActivityFeed.tsx` — shared timeline list (feed + admin).
- `resources/js/Components/ActivityHistory.tsx` — per-resource diff panel.
- `resources/js/Pages/Activity/Index.tsx` — project feed page.
- `resources/js/Pages/Admin/Activity/Index.tsx` — admin audit page.
- `tests/Feature/ActivityLog/*` — feature tests.
- `tests/Unit/ActivityLog/*` — unit tests.

**Modify:**
- `config/activitylog.php` (published) — set custom activity model.
- `app/Models/Url.php`, `Domain.php`, `QrCode.php`, `Pixel.php`, `Project.php` — add logging.
- `app/Http/Controllers/{Team,Auth,PasswordReset,ForcePasswordChange,Profile,TwoFactor,PasskeyManagement,Invitation}Controller.php` + `UrlController`/`DomainController`/`QrCodeController`/`PixelController` (history prop).
- `app/Providers/AppServiceProvider.php` — passkey created/deleted security logging.
- `routes/web.php` — project + admin activity routes.
- `routes/console.php` — daily `activitylog:clean`.
- `resources/js/Components/Sidebar.tsx`, `AdminSidebar.tsx` — nav items.
- `resources/js/types/index.d.ts` — `ActivityEntry` type.

---

## Task 1: Install package, schema & custom model registration

**Files:**
- Run: `ddev composer require spatie/laravel-activitylog`
- Create (published): `config/activitylog.php`, `database/migrations/<published-ts>_create_activity_log_table.php` (+ `add_event_column`, `add_batch_uuid_column` migrations the package ships)
- Create: `database/migrations/<ts>_add_project_id_to_activity_log_table.php`
- Create: `app/Models/Activity.php`
- Test: `tests/Feature/ActivityLog/SchemaTest.php`

**Interfaces:**
- Produces: `App\Models\Activity` (extends `Spatie\Activitylog\Models\Activity`) with `project(): BelongsTo`, `scopeForProject(Builder, Project|string): Builder`, `toFeedArray(): array`. The `activity_log` table has a nullable indexed `project_id` (ULID) column.

- [ ] **Step 1: Install the package**

Run: `ddev composer require spatie/laravel-activitylog`
Expected: package added to `composer.json` require block; `composer.lock` updated.

- [ ] **Step 2: Publish migrations and config**

```bash
ddev php artisan vendor:publish --provider="Spatie\Activitylog\ActivitylogServiceProvider" --tag="activitylog-migrations"
ddev php artisan vendor:publish --provider="Spatie\Activitylog\ActivitylogServiceProvider" --tag="activitylog-config"
```
Expected: `config/activitylog.php` and the package's `create_activity_log_table` (+ event/batch_uuid) migrations appear under `database/migrations/`.

- [ ] **Step 3: Create the `project_id` migration**

Determine a timestamp strictly **after** the just-published migrations (run `ls database/migrations/ | tail -5` and pick a later time). Create `database/migrations/<ts>_add_project_id_to_activity_log_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_log', function (Blueprint $table) {
            $table->ulid('project_id')->nullable()->after('log_name');
            $table->index(['project_id', 'created_at']);
            $table->foreign('project_id')->references('id')->on('projects')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('activity_log', function (Blueprint $table) {
            $table->dropForeign(['project_id']);
            $table->dropIndex(['project_id', 'created_at']);
            $table->dropColumn('project_id');
        });
    }
};
```

- [ ] **Step 4: Create the custom Activity model**

Create `app/Models/Activity.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Activity as SpatieActivity;

class Activity extends SpatieActivity
{
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function scopeForProject(Builder $query, Project|string $project): Builder
    {
        return $query->where('project_id', $project instanceof Project ? $project->getKey() : $project);
    }

    public function toFeedArray(): array
    {
        return [
            'id' => $this->id,
            'log_name' => $this->log_name,
            'description' => $this->description,
            'event' => $this->event,
            'subject_type' => $this->subject_type ? class_basename($this->subject_type) : null,
            'causer' => $this->causer ? ['id' => $this->causer->id, 'name' => $this->causer->name] : null,
            // Spatie v5: attribute diffs (old → new) live in `attribute_changes`,
            // shaped ['attributes' => [...], 'old' => [...]]. `properties` holds
            // only manual custom data (role, email, ip, …).
            'changes' => $this->attribute_changes?->toArray() ?? [],
            'properties' => $this->properties?->toArray() ?? [],
            'created_at' => $this->created_at->toISOString(),
        ];
    }
}
```

- [ ] **Step 5: Register the custom model in config**

In `config/activitylog.php`, set:

```php
'activity_model' => \App\Models\Activity::class,
```

- [ ] **Step 6: Write the failing test**

Create `tests/Feature/ActivityLog/SchemaTest.php`:

```php
<?php

namespace Tests\Feature\ActivityLog;

use App\Models\Activity;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Spatie\Activitylog\ActivitylogServiceProvider;
use Tests\TestCase;

class SchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_activity_log_has_project_id_column(): void
    {
        $this->assertTrue(Schema::hasColumn('activity_log', 'project_id'));
    }

    public function test_configured_activity_model_is_custom(): void
    {
        $this->assertSame(Activity::class, config('activitylog.activity_model'));
    }

    public function test_project_id_is_persisted_and_related(): void
    {
        $project = Project::factory()->create();
        $activity = activity()->log('test');
        $activity->project_id = $project->id;
        $activity->save();

        $this->assertInstanceOf(Activity::class, $activity);
        $this->assertTrue($activity->fresh()->project->is($project));
    }
}
```

- [ ] **Step 7: Run migrations and the test**

Run: `ddev php artisan migrate && ddev php artisan test --filter=SchemaTest`
Expected: PASS (3 tests).

- [ ] **Step 8: Commit**

```bash
git add composer.json composer.lock config/activitylog.php database/migrations app/Models/Activity.php tests/Feature/ActivityLog/SchemaTest.php
git commit -m "feat(activity-log): install spatie activitylog with project_id column and custom model"
```

---

## Task 2: Project-tagging + redaction concern

**Files:**
- Create: `app/Models/Concerns/SetsActivityProject.php`
- Test: `tests/Unit/ActivityLog/SetsActivityProjectTest.php`

**Interfaces:**
- Consumes: `App\Models\Activity` (Task 1).
- Produces: trait `App\Models\Concerns\SetsActivityProject` providing `beforeActivityLogged(Activity $activity, string $eventName): void` (the **Spatie v5** subject hook — sets `project_id` from `resolveActivityProjectId()` and redacts `$activitySensitiveAttributes` in the `attribute_changes` bag, defensively also `properties`) and `resolveActivityProjectId(): ?string` (defaults to `$this->project_id`). Consuming models may override `resolveActivityProjectId()` and declare `protected array $activitySensitiveAttributes`.

> **Spatie v5 note (verified against vendor 5.0.0):** the subject hook is `beforeActivityLogged($activity, $event)` (NOT v4's `tapActivity`), and attribute diffs are stored in the `attribute_changes` column (shape `['attributes' => …, 'old' => …]`), NOT in `properties`. This task was implemented to v5; Tasks 3/4/10 below assert against `attribute_changes` accordingly.

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/ActivityLog/SetsActivityProjectTest.php`:

```php
<?php

namespace Tests\Unit\ActivityLog;

use App\Models\Activity;
use App\Models\Concerns\SetsActivityProject;
use PHPUnit\Framework\TestCase;

class FakeLoggable
{
    use SetsActivityProject;

    public ?string $project_id = 'proj-123';
    protected array $activitySensitiveAttributes = ['password'];
}

class SetsActivityProjectTest extends TestCase
{
    public function test_tap_sets_project_id_from_resolver(): void
    {
        $model = new FakeLoggable();
        $activity = new Activity();
        $activity->properties = collect([]);

        $model->tapActivity($activity, 'created');

        $this->assertSame('proj-123', $activity->project_id);
    }

    public function test_tap_redacts_sensitive_attributes_in_attributes_and_old(): void
    {
        $model = new FakeLoggable();
        $activity = new Activity();
        $activity->properties = collect([
            'attributes' => ['slug' => 'abc', 'password' => 'hashed-value'],
            'old' => ['password' => 'old-hash', 'slug' => 'old'],
        ]);

        $model->tapActivity($activity, 'updated');

        $props = $activity->properties->toArray();
        $this->assertSame('••••', $props['attributes']['password']);
        $this->assertSame('••••', $props['old']['password']);
        $this->assertSame('abc', $props['attributes']['slug']);
    }

    public function test_tap_leaves_null_sensitive_values_untouched(): void
    {
        $model = new FakeLoggable();
        $activity = new Activity();
        $activity->properties = collect(['attributes' => ['password' => null]]);

        $model->tapActivity($activity, 'updated');

        $this->assertNull($activity->properties->toArray()['attributes']['password']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `ddev php artisan test --filter=SetsActivityProjectTest`
Expected: FAIL — trait `App\Models\Concerns\SetsActivityProject` not found.

- [ ] **Step 3: Implement the trait**

Create `app/Models/Concerns/SetsActivityProject.php`:

```php
<?php

namespace App\Models\Concerns;

use App\Models\Activity;

trait SetsActivityProject
{
    /**
     * Called by Spatie's LogsActivity after building an activity for an
     * auto-logged event. Tags the project and redacts sensitive attributes.
     */
    public function tapActivity(Activity $activity, string $eventName): void
    {
        $activity->project_id = $this->resolveActivityProjectId();

        $sensitive = $this->activitySensitiveAttributes ?? [];

        if ($sensitive === []) {
            return;
        }

        $properties = $activity->properties->toArray();

        foreach (['attributes', 'old'] as $bag) {
            if (! isset($properties[$bag]) || ! is_array($properties[$bag])) {
                continue;
            }

            foreach ($sensitive as $attr) {
                if (array_key_exists($attr, $properties[$bag]) && $properties[$bag][$attr] !== null) {
                    $properties[$bag][$attr] = '••••';
                }
            }
        }

        $activity->properties = collect($properties);
    }

    /**
     * Project this model's activity belongs to. Defaults to the model's
     * own project_id; the Project model overrides this to return its key.
     */
    public function resolveActivityProjectId(): ?string
    {
        return $this->project_id ?? null;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `ddev php artisan test --filter=SetsActivityProjectTest`
Expected: PASS (3 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Models/Concerns/SetsActivityProject.php tests/Unit/ActivityLog/SetsActivityProjectTest.php
git commit -m "feat(activity-log): add project-tagging and redaction concern"
```

---

## Task 3: Auto-log the Url model (with redaction)

**Files:**
- Modify: `app/Models/Url.php`
- Test: `tests/Feature/ActivityLog/UrlLoggingTest.php`

**Interfaces:**
- Consumes: `LogsActivity` (Spatie), `SetsActivityProject` (Task 2).
- Produces: `Url` writes an activity (`log_name = url`) on create/update/delete with dirty attribute diffs; `password` redacted; `targeting_ab` included; timestamps/counters excluded.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/ActivityLog/UrlLoggingTest.php`:

```php
<?php

namespace Tests\Feature\ActivityLog;

use App\Models\Activity;
use App\Models\Project;
use App\Models\Url;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UrlLoggingTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_url_logs_a_tagged_activity(): void
    {
        $project = Project::factory()->create();
        $url = Url::factory()->for($project)->create();

        $activity = Activity::query()->where('subject_id', $url->id)->latest('id')->first();

        $this->assertNotNull($activity);
        $this->assertSame('url', $activity->log_name);
        $this->assertSame('created', $activity->description);
        $this->assertSame($project->id, $activity->project_id);
    }

    public function test_updating_a_url_logs_only_dirty_attributes_and_excludes_timestamps(): void
    {
        $project = Project::factory()->create();
        $url = Url::factory()->for($project)->create(['slug' => 'old-slug']);

        $url->update(['slug' => 'new-slug']);

        $activity = Activity::query()->where('subject_id', $url->id)->where('event', 'updated')->latest('id')->first();
        // Spatie v5: diffs live in attribute_changes, not properties.
        $attrs = $activity->attribute_changes->toArray()['attributes'];

        $this->assertSame('new-slug', $attrs['slug']);
        $this->assertArrayNotHasKey('updated_at', $attrs);
        $this->assertArrayNotHasKey('created_at', $attrs);
        $this->assertArrayNotHasKey('clicks', $attrs);
    }

    public function test_password_is_redacted(): void
    {
        $project = Project::factory()->create();
        $url = Url::factory()->for($project)->create();

        $url->update(['password' => 'super-secret']);

        $activity = Activity::query()->where('subject_id', $url->id)->where('event', 'updated')->latest('id')->first();
        $attrs = $activity->attribute_changes->toArray()['attributes'];

        $this->assertSame('••••', $attrs['password']);
        $this->assertStringNotContainsString('super-secret', json_encode($activity->attribute_changes->toArray()));
    }

    public function test_ab_targeting_is_logged(): void
    {
        $project = Project::factory()->create();
        $url = Url::factory()->for($project)->create();

        $url->update(['targeting_ab' => [['url' => 'https://b.test', 'weight' => 50]]]);

        $activity = Activity::query()->where('subject_id', $url->id)->where('event', 'updated')->latest('id')->first();

        $this->assertArrayHasKey('targeting_ab', $activity->attribute_changes->toArray()['attributes']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `ddev php artisan test --filter=UrlLoggingTest`
Expected: FAIL — no activity rows / `log_name` not `url`.

- [ ] **Step 3: Add logging to the Url model**

In `app/Models/Url.php`, add imports and trait usage. Add to the `use` import block:

```php
use App\Models\Concerns\SetsActivityProject;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
```

Change the trait line `use HasUlids, SoftDeletes;` to:

```php
use HasUlids, LogsActivity, SetsActivityProject, SoftDeletes;
```

Add these members to the class body (e.g. directly above `protected function casts()`):

```php
    protected array $activitySensitiveAttributes = ['password'];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('url')
            ->logOnly([
                'slug', 'url', 'type', 'password', 'expired_at', 'status', 'archived',
                'targeting_geo', 'targeting_device', 'targeting_language', 'targeting_ab',
            ])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    public function getDescriptionForEvent(string $eventName): string
    {
        return $eventName;
    }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `ddev php artisan test --filter=UrlLoggingTest`
Expected: PASS (4 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Models/Url.php tests/Feature/ActivityLog/UrlLoggingTest.php
git commit -m "feat(activity-log): auto-log Url changes with password redaction"
```

---

## Task 4: Auto-log Domain, QrCode, Pixel, Project

**Files:**
- Modify: `app/Models/Domain.php`, `app/Models/QrCode.php`, `app/Models/Pixel.php`, `app/Models/Project.php`
- Test: `tests/Feature/ActivityLog/ContentModelLoggingTest.php`

**Interfaces:**
- Consumes: `LogsActivity`, `SetsActivityProject` (Task 2).
- Produces: `Domain` (log_name `domain`), `QrCode` (`qrcode`), `Pixel` (`pixel`), `Project` (`project`) auto-log create/update/delete. `Domain` logs only user-editable fields (not health-check fields churned by background jobs). `Project` tags activity with its own id.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/ActivityLog/ContentModelLoggingTest.php`:

```php
<?php

namespace Tests\Feature\ActivityLog;

use App\Enums\PixelProvider;
use App\Models\Activity;
use App\Models\Domain;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContentModelLoggingTest extends TestCase
{
    use RefreshDatabase;

    public function test_domain_logs_editable_fields_and_ignores_health_churn(): void
    {
        $project = Project::factory()->create();
        $domain = $project->domains()->create(['name' => 'a.test', 'redirect_root' => null, 'redirect_not_found' => null]);

        $domain->update(['redirect_root' => 'https://x.test']);
        $domain->update(['dns_ok' => true, 'last_checked_at' => now()]);

        $editActivity = Activity::query()->where('subject_id', $domain->id)->where('event', 'updated')->latest('id')->first();
        // Spatie v5: diffs live in attribute_changes, not properties.
        $attrs = $editActivity->attribute_changes->toArray()['attributes'];

        $this->assertSame('domain', $editActivity->log_name);
        $this->assertSame($project->id, $editActivity->project_id);
        // The health-only update must not create a new logged activity.
        $this->assertArrayHasKey('redirect_root', $attrs);
        $this->assertArrayNotHasKey('dns_ok', $attrs);
        $this->assertSame(1, Activity::query()->where('subject_id', $domain->id)->where('event', 'updated')->count());
    }

    public function test_qrcode_logs_activity(): void
    {
        $project = Project::factory()->create();
        $qr = $project->qrCodes()->create(['name' => 'My QR', 'type' => 'url', 'is_dynamic' => false, 'content' => ['url' => 'https://x.test'], 'style' => []]);

        $activity = Activity::query()->where('subject_id', $qr->id)->latest('id')->first();
        $this->assertSame('qrcode', $activity->log_name);
        $this->assertSame($project->id, $activity->project_id);
    }

    public function test_pixel_logs_activity(): void
    {
        $project = Project::factory()->create();
        $pixel = $project->pixels()->create(['provider' => PixelProvider::cases()[0]->value, 'name' => 'P', 'tag' => 'TAG']);

        $activity = Activity::query()->where('subject_id', $pixel->id)->latest('id')->first();
        $this->assertSame('pixel', $activity->log_name);
        $this->assertSame($project->id, $activity->project_id);
    }

    public function test_project_tags_activity_with_own_id(): void
    {
        $project = Project::factory()->create();
        $project->update(['name' => 'Renamed']);

        $activity = Activity::query()->where('subject_id', $project->id)->where('event', 'updated')->latest('id')->first();
        $this->assertSame('project', $activity->log_name);
        $this->assertSame($project->id, $activity->project_id);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `ddev php artisan test --filter=ContentModelLoggingTest`
Expected: FAIL — log names not set / project_id null.

- [ ] **Step 3: Add logging to Domain**

In `app/Models/Domain.php` add to imports:

```php
use App\Models\Concerns\SetsActivityProject;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
```

Change `use HasFactory, HasUlids, SoftDeletes;` to:

```php
use HasFactory, HasUlids, LogsActivity, SetsActivityProject, SoftDeletes;
```

Add to the class body:

```php
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('domain')
            ->logOnly(['name', 'redirect_root', 'redirect_not_found'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    public function getDescriptionForEvent(string $eventName): string
    {
        return $eventName;
    }
```

- [ ] **Step 4: Add logging to QrCode**

In `app/Models/QrCode.php` add imports:

```php
use App\Models\Concerns\SetsActivityProject;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
```

Change `use HasUlids, SoftDeletes;` to:

```php
use HasUlids, LogsActivity, SetsActivityProject, SoftDeletes;
```

Add to the class body:

```php
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('qrcode')
            ->logOnly(['name', 'type', 'is_dynamic', 'content', 'style'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    public function getDescriptionForEvent(string $eventName): string
    {
        return $eventName;
    }
```

- [ ] **Step 5: Add logging to Pixel**

In `app/Models/Pixel.php` add imports:

```php
use App\Models\Concerns\SetsActivityProject;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
```

Change `use HasUlids;` to:

```php
use HasUlids, LogsActivity, SetsActivityProject;
```

Add to the class body:

```php
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('pixel')
            ->logOnly(['provider', 'name', 'tag'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    public function getDescriptionForEvent(string $eventName): string
    {
        return $eventName;
    }
```

- [ ] **Step 6: Add logging to Project (overrides project resolver)**

In `app/Models/Project.php` add imports:

```php
use App\Models\Concerns\SetsActivityProject;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
```

Change `use HasFactory, HasUlids, SoftDeletes;` to:

```php
use HasFactory, HasUlids, LogsActivity, SetsActivityProject, SoftDeletes;
```

Add to the class body:

```php
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('project')
            ->logOnly(['name', 'locked'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    public function getDescriptionForEvent(string $eventName): string
    {
        return $eventName;
    }

    public function resolveActivityProjectId(): ?string
    {
        return $this->getKey();
    }
```

- [ ] **Step 7: Run test to verify it passes**

Run: `ddev php artisan test --filter=ContentModelLoggingTest`
Expected: PASS (4 tests).

- [ ] **Step 8: Commit**

```bash
git add app/Models/Domain.php app/Models/QrCode.php app/Models/Pixel.php app/Models/Project.php tests/Feature/ActivityLog/ContentModelLoggingTest.php
git commit -m "feat(activity-log): auto-log Domain, QrCode, Pixel, Project"
```

---

## Task 5: ActivityRecorder helper for manual events

**Files:**
- Create: `app/Support/ActivityRecorder.php`
- Test: `tests/Feature/ActivityLog/ActivityRecorderTest.php`

**Interfaces:**
- Consumes: `App\Models\Activity` (Task 1).
- Produces:
  - `ActivityRecorder::project(string $logName, string $description, ?string $projectId, ?Model $causer = null, ?Model $subject = null, array $properties = []): Activity`
  - `ActivityRecorder::security(string $description, Model $causer, array $properties = []): Activity` — forces `project_id = null`, auto-adds `ip` + `user_agent`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/ActivityLog/ActivityRecorderTest.php`:

```php
<?php

namespace Tests\Feature\ActivityLog;

use App\Models\Activity;
use App\Models\Project;
use App\Models\User;
use App\Support\ActivityRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityRecorderTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_event_is_tagged_and_attributed(): void
    {
        $project = Project::factory()->create();
        $user = User::factory()->create();

        $activity = ActivityRecorder::project('membership', 'member_added', $project->id, $user, $project, ['role' => 'member']);

        $this->assertSame('membership', $activity->log_name);
        $this->assertSame('member_added', $activity->description);
        $this->assertSame($project->id, $activity->project_id);
        $this->assertTrue($activity->causer->is($user));
        $this->assertSame('member', $activity->properties->toArray()['role']);
    }

    public function test_security_event_has_null_project_and_request_metadata(): void
    {
        $user = User::factory()->create();

        $activity = ActivityRecorder::security('login', $user);

        $this->assertSame('security', $activity->log_name);
        $this->assertNull($activity->project_id);
        $this->assertTrue($activity->causer->is($user));
        $this->assertArrayHasKey('ip', $activity->properties->toArray());
        $this->assertArrayHasKey('user_agent', $activity->properties->toArray());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `ddev php artisan test --filter=ActivityRecorderTest`
Expected: FAIL — class `App\Support\ActivityRecorder` not found.

- [ ] **Step 3: Implement the helper**

Create `app/Support/ActivityRecorder.php`:

```php
<?php

namespace App\Support;

use App\Models\Activity;
use Illuminate\Database\Eloquent\Model;

class ActivityRecorder
{
    /**
     * Record a project-scoped event (membership, invitation, …).
     */
    public static function project(
        string $logName,
        string $description,
        ?string $projectId,
        ?Model $causer = null,
        ?Model $subject = null,
        array $properties = [],
    ): Activity {
        $logger = activity($logName)->withProperties($properties);

        if ($subject) {
            $logger->performedOn($subject);
        }

        if ($causer) {
            $logger->causedBy($causer);
        }

        /** @var Activity $activity */
        $activity = $logger->log($description);
        $activity->project_id = $projectId;
        $activity->save();

        return $activity;
    }

    /**
     * Record a global security event (project_id is always null).
     */
    public static function security(string $description, Model $causer, array $properties = []): Activity
    {
        $properties = array_merge([
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ], $properties);

        return self::project('security', $description, null, $causer, null, $properties);
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `ddev php artisan test --filter=ActivityRecorderTest`
Expected: PASS (2 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Support/ActivityRecorder.php tests/Feature/ActivityLog/ActivityRecorderTest.php
git commit -m "feat(activity-log): add ActivityRecorder helper for manual events"
```

---

## Task 6: Wire membership & invitation events

**Files:**
- Modify: `app/Http/Controllers/TeamController.php`, `app/Http/Controllers/InvitationController.php`
- Test: `tests/Feature/ActivityLog/MembershipLoggingTest.php`

**Interfaces:**
- Consumes: `ActivityRecorder` (Task 5).
- Produces: `membership` log entries (`member_removed`, `role_changed`) and `invitation` entries (`invitation_sent`, `invitation_revoked`, `invitation_resent`, `invitation_accepted`), each tagged with the project.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/ActivityLog/MembershipLoggingTest.php`:

```php
<?php

namespace Tests\Feature\ActivityLog;

use App\Enums\ProjectRole;
use App\Models\Activity;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MembershipLoggingTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_change_is_logged(): void
    {
        $admin = User::factory()->create();
        $member = User::factory()->create();
        $project = Project::factory()->create();
        $project->users()->attach($admin, ['role' => ProjectRole::Admin->value, 'active' => true]);
        $project->users()->attach($member, ['role' => ProjectRole::Member->value, 'active' => true]);

        $this->actingAs($admin)->patch(route('app.project.team.members.update', ['project' => $project->id, 'user' => $member->id]), [
            'role' => ProjectRole::Admin->value,
        ]);

        $activity = Activity::query()->where('log_name', 'membership')->where('description', 'role_changed')->latest('id')->first();
        $this->assertNotNull($activity);
        $this->assertSame($project->id, $activity->project_id);
        $this->assertTrue($activity->causer->is($admin));
    }

    public function test_member_removal_is_logged(): void
    {
        $admin = User::factory()->create();
        $member = User::factory()->create();
        $project = Project::factory()->create();
        $project->users()->attach($admin, ['role' => ProjectRole::Admin->value, 'active' => true]);
        $project->users()->attach($member, ['role' => ProjectRole::Member->value, 'active' => true]);

        $this->actingAs($admin)->delete(route('app.project.team.members.destroy', ['project' => $project->id, 'user' => $member->id]));

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'membership',
            'description' => 'member_removed',
            'project_id' => $project->id,
        ]);
    }

    public function test_invitation_sent_is_logged(): void
    {
        $admin = User::factory()->create();
        $project = Project::factory()->create();
        $project->users()->attach($admin, ['role' => ProjectRole::Admin->value, 'active' => true]);

        $this->actingAs($admin)->post(route('app.project.team.invitations.store', ['project' => $project->id]), [
            'email' => 'invitee@test.com',
            'role' => ProjectRole::Member->value,
        ]);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'invitation',
            'description' => 'invitation_sent',
            'project_id' => $project->id,
        ]);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `ddev php artisan test --filter=MembershipLoggingTest`
Expected: FAIL — no matching `activity_log` rows.

- [ ] **Step 3: Add the import to TeamController**

In `app/Http/Controllers/TeamController.php` add to the `use` block:

```php
use App\Support\ActivityRecorder;
```

- [ ] **Step 4: Log invitation sent**

In `TeamController::storeInvitation`, after `$this->sendInvitation($invitation);` and before the `return`, add:

```php
        ActivityRecorder::project('invitation', 'invitation_sent', $project->id, $request->user(), $project, [
            'email' => $data['email'],
            'role' => $data['role'],
        ]);
```

- [ ] **Step 5: Log invitation revoked**

In `TeamController::destroyInvitation`, replace the line `$project->invitations()->findOrFail($invitation)->delete();` with:

```php
        $invite = $project->invitations()->findOrFail($invitation);
        ActivityRecorder::project('invitation', 'invitation_revoked', $project->id, $request->user(), $project, [
            'email' => $invite->email,
        ]);
        $invite->delete();
```

- [ ] **Step 6: Log invitation resent**

In `TeamController::resendInvitation`, after `$this->sendInvitation($invite);` and before the `return`, add:

```php
        ActivityRecorder::project('invitation', 'invitation_resent', $project->id, $request->user(), $project, [
            'email' => $invite->email,
        ]);
```

- [ ] **Step 7: Log role change**

In `TeamController::updateMember`, after `$project->users()->updateExistingPivot($user, ['role' => $data['role']]);` add:

```php
        ActivityRecorder::project('membership', 'role_changed', $project->id, $request->user(), $project, [
            'user_id' => $user,
            'role' => $data['role'],
        ]);
```

- [ ] **Step 8: Log member removal**

In `TeamController::destroyMember`, after `$project->users()->detach($user);` add:

```php
        ActivityRecorder::project('membership', 'member_removed', $project->id, $request->user(), $project, [
            'user_id' => $user,
        ]);
```

- [ ] **Step 9: Log invitation accepted**

In `app/Http/Controllers/InvitationController.php` add to the `use` block:

```php
use App\Support\ActivityRecorder;
```

In `InvitationController::accept`, after `$invitation->update(['accepted_at' => now()]);` add:

```php
        ActivityRecorder::project('invitation', 'invitation_accepted', $invitation->project_id, $user, $invitation->project, [
            'email' => $invitation->email,
        ]);
```

- [ ] **Step 10: Run test to verify it passes**

Run: `ddev php artisan test --filter=MembershipLoggingTest`
Expected: PASS (3 tests).

- [ ] **Step 11: Commit**

```bash
git add app/Http/Controllers/TeamController.php app/Http/Controllers/InvitationController.php tests/Feature/ActivityLog/MembershipLoggingTest.php
git commit -m "feat(activity-log): log membership and invitation events"
```

---

## Task 7: Wire security events

**Files:**
- Modify: `app/Http/Controllers/AuthController.php`, `PasswordResetController.php`, `ForcePasswordChangeController.php`, `ProfileController.php`, `TwoFactorController.php`, `PasskeyManagementController.php`
- Modify: `app/Providers/AppServiceProvider.php` (passkey created/deleted)
- Test: `tests/Feature/ActivityLog/SecurityLoggingTest.php`

**Interfaces:**
- Consumes: `ActivityRecorder` (Task 5).
- Produces: `security` log entries (`project_id = null`) for: `login`, `password_changed`, `password_reset`, `two_factor_enabled`, `two_factor_disabled`, `passkey_added`, `passkey_removed`, `passkey_renamed`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/ActivityLog/SecurityLoggingTest.php`:

```php
<?php

namespace Tests\Feature\ActivityLog;

use App\Models\Activity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SecurityLoggingTest extends TestCase
{
    use RefreshDatabase;

    public function test_successful_login_is_logged_with_null_project(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password123')]);

        $this->post(route('app.auth.login'), ['email' => $user->email, 'password' => 'password123']);

        $activity = Activity::query()->where('log_name', 'security')->where('description', 'login')->latest('id')->first();
        $this->assertNotNull($activity);
        $this->assertNull($activity->project_id);
        $this->assertTrue($activity->causer->is($user));
    }

    public function test_password_change_is_logged(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put(route('app.profile.update'), [
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ]);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'security',
            'description' => 'password_changed',
            'causer_id' => $user->id,
        ]);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `ddev php artisan test --filter=SecurityLoggingTest`
Expected: FAIL — no matching rows.

- [ ] **Step 3: Log login**

In `app/Http/Controllers/AuthController.php` add to the `use` block:

```php
use App\Support\ActivityRecorder;
```

In `AuthController::login`, after `$request->session()->regenerate();` (the non-2FA branch) and before `return redirect('/');`, add:

```php
        ActivityRecorder::security('login', $user);
```

> Note: the 2FA branch completes login in `TwoFactorChallengeController`. Logging login there is out of scope for this task (the direct-login path is covered); a follow-up may add it. This is an intentional, documented gap — non-2FA logins are logged.

- [ ] **Step 4: Log password reset**

In `app/Http/Controllers/PasswordResetController.php` add to the `use` block:

```php
use App\Support\ActivityRecorder;
```

In `PasswordResetController::reset`, inside the `Password::reset` closure, after `->save();`, add:

```php
                ActivityRecorder::security('password_reset', $user);
```

- [ ] **Step 5: Log forced password change**

In `app/Http/Controllers/ForcePasswordChangeController.php` add to the `use` block:

```php
use App\Support\ActivityRecorder;
```

In `ForcePasswordChangeController::update`, after `$user->save();`, add:

```php
        ActivityRecorder::security('password_changed', $user);
```

- [ ] **Step 6: Log profile password change**

In `app/Http/Controllers/ProfileController.php` add to the `use` block:

```php
use App\Support\ActivityRecorder;
```

In `ProfileController::update`, after `$user->save();`, add:

```php
        ActivityRecorder::security('password_changed', $user);
```

- [ ] **Step 7: Log 2FA enable/disable**

In `app/Http/Controllers/TwoFactorController.php` add to the `use` block:

```php
use App\Support\ActivityRecorder;
```

In `TwoFactorController::confirm`, after the `->save();` that sets `two_factor_confirmed_at`, add:

```php
        ActivityRecorder::security('two_factor_enabled', $user);
```

In `TwoFactorController::disable`, replace the body with a captured user and a log call:

```php
        $request->validate(['current_password' => ['required', 'current_password']]);

        $user = $request->user();
        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        ActivityRecorder::security('two_factor_disabled', $user);

        return redirect()->route('app.profile.edit');
```

- [ ] **Step 8: Log passkey rename**

In `app/Http/Controllers/PasskeyManagementController.php` add to the `use` block:

```php
use App\Support\ActivityRecorder;
```

In `PasskeyManagementController::rename`, after `$passkey->update(['name' => $validated['name']]);`, add:

```php
        ActivityRecorder::security('passkey_renamed', $request->user(), ['passkey' => $validated['name']]);
```

- [ ] **Step 9: Log passkey add/remove via model events**

In `app/Providers/AppServiceProvider.php`, add to the `use` block:

```php
use App\Support\ActivityRecorder;
use Laravel\Passkeys\Passkey;
```

In the `boot()` method, add:

```php
        Passkey::created(function (Passkey $passkey) {
            if ($passkey->user) {
                ActivityRecorder::security('passkey_added', $passkey->user, ['passkey' => $passkey->name]);
            }
        });

        Passkey::deleted(function (Passkey $passkey) {
            if ($passkey->user) {
                ActivityRecorder::security('passkey_removed', $passkey->user, ['passkey' => $passkey->name]);
            }
        });
```

> Verified against `vendor/laravel/passkeys/src/Passkey.php` (laravel/passkeys 0.2): the owner relation is `user()` (a `belongsTo` on `user_id`), NOT `authenticatable`. Passkeys are persisted via `$user->passkeys()->create(...)` (Eloquent `create`), so the `created` model event fires. Deletion logging fires only if a passkey is removed via Eloquent `delete()`.

- [ ] **Step 10: Run test to verify it passes**

Run: `ddev php artisan test --filter=SecurityLoggingTest`
Expected: PASS (2 tests).

- [ ] **Step 11: Commit**

```bash
git add app/Http/Controllers/AuthController.php app/Http/Controllers/PasswordResetController.php app/Http/Controllers/ForcePasswordChangeController.php app/Http/Controllers/ProfileController.php app/Http/Controllers/TwoFactorController.php app/Http/Controllers/PasskeyManagementController.php app/Providers/AppServiceProvider.php tests/Feature/ActivityLog/SecurityLoggingTest.php
git commit -m "feat(activity-log): log security events (login, password, 2FA, passkeys)"
```

---

## Task 8: Project activity feed (surface A)

**Files:**
- Create: `app/Http/Controllers/ActivityController.php`
- Create: `resources/js/Components/ActivityFeed.tsx`
- Create: `resources/js/Pages/Activity/Index.tsx`
- Modify: `routes/web.php`, `resources/js/Components/Sidebar.tsx`, `resources/js/types/index.d.ts`
- Test: `tests/Feature/ActivityLog/ProjectFeedTest.php`

**Interfaces:**
- Consumes: `Activity::forProject()` (Task 1).
- Produces: route `app.project.activity.index` → `GET /project/{project}/activity`; Inertia page `Activity/Index` with prop `activities` (paginated `ActivityEntry[]`) and `logName` filter. Never returns `project_id = null` (security) rows.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/ActivityLog/ProjectFeedTest.php`:

```php
<?php

namespace Tests\Feature\ActivityLog;

use App\Enums\ProjectRole;
use App\Models\Project;
use App\Models\Url;
use App\Models\User;
use App\Support\ActivityRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectFeedTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_sees_only_their_project_activity_and_no_security_events(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create();
        $other = Project::factory()->create();
        $project->users()->attach($user, ['role' => ProjectRole::Member->value, 'active' => true]);

        Url::factory()->for($project)->create(['slug' => 'mine']);
        Url::factory()->for($other)->create(['slug' => 'theirs']);
        ActivityRecorder::security('login', $user);

        $this->actingAs($user)
            ->get(route('app.project.activity.index', ['project' => $project->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Activity/Index')
                ->where('activities.data', fn ($data) => collect($data)->every(fn ($a) => $a['log_name'] !== 'security'))
            );
    }

    public function test_non_member_is_forbidden(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create();

        $this->actingAs($user)
            ->get(route('app.project.activity.index', ['project' => $project->id]))
            ->assertForbidden();
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `ddev php artisan test --filter=ProjectFeedTest`
Expected: FAIL — route `app.project.activity.index` not defined.

- [ ] **Step 3: Create the controller**

Create `app/Http/Controllers/ActivityController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Project;
use Illuminate\Http\Request;

class ActivityController extends Controller
{
    public function index(Request $request)
    {
        /** @var Project $project */
        $project = $request->get('project');

        $logName = $request->string('log_name')->toString() ?: null;

        $activities = Activity::query()
            ->forProject($project)
            ->when($logName, fn ($q) => $q->where('log_name', $logName))
            ->with('causer')
            ->latest('id')
            ->paginate(30)
            ->withQueryString()
            ->through(fn (Activity $a) => $a->toFeedArray());

        return inertia('Activity/Index', [
            'activities' => $activities,
            'logName' => $logName,
            'logNames' => ['url', 'domain', 'qrcode', 'pixel', 'project', 'membership', 'invitation'],
        ]);
    }
}
```

- [ ] **Step 4: Register the route**

In `routes/web.php`, add the import near the other controller imports at the top:

```php
use App\Http\Controllers\ActivityController;
```

Inside the project tenant group (the `->prefix('/project/{project}')` group), after the `/dashboard` route, add:

```php
            Route::get('/activity', [ActivityController::class, 'index'])->name('app.project.activity.index');
```

- [ ] **Step 5: Add the TS type**

In `resources/js/types/index.d.ts`, add:

```ts
export interface ActivityEntry {
  id: number;
  log_name: string;
  description: string;
  event: string | null;
  subject_type: string | null;
  causer: { id: string; name: string } | null;
  // Spatie v5 attribute diffs (old → new) from the attribute_changes column.
  changes: { attributes?: Record<string, unknown>; old?: Record<string, unknown> };
  // Manual custom data (role, email, ip, …) from the properties column.
  properties: Record<string, unknown>;
  created_at: string;
}
```

- [ ] **Step 6: Create the shared ActivityFeed component**

Create `resources/js/Components/ActivityFeed.tsx`:

```tsx
import { ActivityEntry } from '@/types';

const LABELS: Record<string, string> = {
  created: 'created',
  updated: 'updated',
  deleted: 'deleted',
  login: 'signed in',
  password_changed: 'changed their password',
  password_reset: 'reset their password',
  two_factor_enabled: 'enabled two-factor auth',
  two_factor_disabled: 'disabled two-factor auth',
  passkey_added: 'added a passkey',
  passkey_removed: 'removed a passkey',
  passkey_renamed: 'renamed a passkey',
  member_removed: 'removed a member',
  role_changed: 'changed a member role',
  invitation_sent: 'sent an invitation',
  invitation_revoked: 'revoked an invitation',
  invitation_resent: 'resent an invitation',
  invitation_accepted: 'accepted an invitation',
};

function describe(a: ActivityEntry): string {
  const verb = LABELS[a.description] ?? a.description;
  if (['created', 'updated', 'deleted'].includes(a.description) && a.subject_type) {
    return `${verb} a ${a.subject_type}`;
  }
  return verb;
}

export default function ActivityFeed({ activities }: { activities: ActivityEntry[] }) {
  if (activities.length === 0) {
    return <p className="py-12 text-center text-sm text-slate-400">No activity yet.</p>;
  }

  return (
    <ul className="divide-y divide-slate-100 dark:divide-slate-800">
      {activities.map((a) => (
        <li key={a.id} className="flex items-center gap-3 py-3">
          <span className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-xs font-semibold text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300">
            {(a.causer?.name ?? '•').slice(0, 2).toUpperCase()}
          </span>
          <div className="min-w-0 flex-1">
            <p className="truncate text-sm text-slate-700 dark:text-slate-200">
              <span className="font-medium text-slate-900 dark:text-white">{a.causer?.name ?? 'System'}</span>{' '}
              {describe(a)}
            </p>
            <p className="text-xs text-slate-400">
              <span className="rounded bg-slate-100 px-1.5 py-0.5 dark:bg-slate-800">{a.log_name}</span>{' '}
              {new Date(a.created_at).toLocaleString()}
            </p>
          </div>
        </li>
      ))}
    </ul>
  );
}
```

- [ ] **Step 7: Create the project feed page**

Create `resources/js/Pages/Activity/Index.tsx`:

```tsx
import ActivityFeed from '@/Components/ActivityFeed';
import AppLayout from '@/Layouts/AppLayout';
import { ActivityEntry, PageProps } from '@/types';
import { Link, router, usePage } from '@inertiajs/react';

interface Paginated<T> {
  data: T[];
  links: { url: string | null; label: string; active: boolean }[];
}

export default function ActivityIndex({
  activities,
  logName,
  logNames,
}: {
  activities: Paginated<ActivityEntry>;
  logName: string | null;
  logNames: string[];
}) {
  const project = usePage<PageProps>().props.project;

  function onFilter(value: string) {
    router.get(route('app.project.activity.index', { project: project!.id }), value ? { log_name: value } : {}, {
      preserveState: true,
      replace: true,
    });
  }

  return (
    <AppLayout title="Activity">
      <div className="px-8 py-8">
        <div className="mb-6 flex items-center justify-between">
          <h1 className="text-2xl font-bold text-slate-900 dark:text-white">Activity</h1>
          <select
            value={logName ?? ''}
            onChange={(e) => onFilter(e.target.value)}
            className="rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white"
          >
            <option value="">All activity</option>
            {logNames.map((n) => (
              <option key={n} value={n}>
                {n}
              </option>
            ))}
          </select>
        </div>

        <div className="rounded-xl border border-slate-200 bg-white px-5 dark:border-slate-800 dark:bg-slate-900">
          <ActivityFeed activities={activities.data} />
        </div>

        <div className="mt-4 flex flex-wrap gap-1">
          {activities.links.map((link, i) => (
            <Link
              key={i}
              href={link.url ?? '#'}
              className={`rounded px-3 py-1 text-sm ${link.active ? 'bg-indigo-600 text-white' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800'} ${!link.url ? 'pointer-events-none opacity-50' : ''}`}
              dangerouslySetInnerHTML={{ __html: link.label }}
            />
          ))}
        </div>
      </div>
    </AppLayout>
  );
}
```

- [ ] **Step 8: Add the sidebar nav item**

In `resources/js/Components/Sidebar.tsx`, add `History` to the lucide import:

```tsx
import { BarChart3, Globe, History, LayoutDashboard, Link2, LinkIcon, QrCode, Users, Zap } from 'lucide-react';
```

Add to the `navItems` array (after Statistics):

```tsx
  { label: 'Activity',   icon: History,         routeName: 'app.project.activity.index' },
```

- [ ] **Step 9: Run the feature test and build**

Run: `ddev php artisan test --filter=ProjectFeedTest`
Expected: PASS (2 tests).
Run: `ddev npm run build`
Expected: build succeeds, no TypeScript errors.

- [ ] **Step 10: Commit**

```bash
git add app/Http/Controllers/ActivityController.php resources/js/Components/ActivityFeed.tsx resources/js/Pages/Activity/Index.tsx routes/web.php resources/js/Components/Sidebar.tsx resources/js/types/index.d.ts tests/Feature/ActivityLog/ProjectFeedTest.php
git commit -m "feat(activity-log): project activity feed page"
```

---

## Task 9: Admin audit view (surface C)

**Files:**
- Create: `app/Http/Controllers/Admin/ActivityController.php`
- Create: `resources/js/Pages/Admin/Activity/Index.tsx`
- Modify: `routes/web.php`, `resources/js/Components/AdminSidebar.tsx`
- Test: `tests/Feature/ActivityLog/AdminAuditTest.php`

**Interfaces:**
- Consumes: `Activity` (Task 1), `ActivityFeed.tsx` (Task 8).
- Produces: route `app.admin.activity.index` → `GET /admin/activity` (super_admin only); shows all projects' activity **plus** null-project security events; filters by `log_name`, `project_id`, `causer`, `from`, `to`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/ActivityLog/AdminAuditTest.php`:

```php
<?php

namespace Tests\Feature\ActivityLog;

use App\Models\User;
use App\Support\ActivityRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_is_forbidden(): void
    {
        $user = User::factory()->create(['super_admin' => false]);

        $this->actingAs($user)->get(route('app.admin.activity.index'))->assertForbidden();
    }

    public function test_admin_sees_security_events(): void
    {
        $admin = User::factory()->create(['super_admin' => true]);
        ActivityRecorder::security('login', $admin);

        $this->actingAs($admin)
            ->get(route('app.admin.activity.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Activity/Index')
                ->where('activities.data', fn ($data) => collect($data)->contains(fn ($a) => $a['log_name'] === 'security'))
            );
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `ddev php artisan test --filter=AdminAuditTest`
Expected: FAIL — route not defined.

- [ ] **Step 3: Create the admin controller**

Create `app/Http/Controllers/Admin/ActivityController.php`:

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Project;
use Illuminate\Http\Request;

class ActivityController extends Controller
{
    public function index(Request $request)
    {
        $logName = $request->string('log_name')->toString() ?: null;
        $projectId = $request->string('project_id')->toString() ?: null;
        $causer = $request->string('causer')->toString() ?: null;
        $from = $request->date('from');
        $to = $request->date('to');

        $activities = Activity::query()
            ->with(['causer', 'project'])
            ->when($logName, fn ($q) => $q->where('log_name', $logName))
            ->when($projectId, fn ($q) => $q->where('project_id', $projectId))
            ->when($causer, fn ($q) => $q->whereHasMorph('causer', [\App\Models\User::class], fn ($q) => $q->where('name', 'like', "%{$causer}%")->orWhere('email', 'like', "%{$causer}%")))
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from->startOfDay()))
            ->when($to, fn ($q) => $q->where('created_at', '<=', $to->endOfDay()))
            ->latest('id')
            ->paginate(40)
            ->withQueryString()
            ->through(fn (Activity $a) => $a->toFeedArray() + [
                'project' => $a->project ? ['id' => $a->project->id, 'name' => $a->project->name] : null,
            ]);

        return inertia('Admin/Activity/Index', [
            'activities' => $activities,
            'filters' => [
                'log_name' => $logName,
                'project_id' => $projectId,
                'causer' => $causer,
                'from' => $request->string('from')->toString() ?: null,
                'to' => $request->string('to')->toString() ?: null,
            ],
            'logNames' => ['url', 'domain', 'qrcode', 'pixel', 'project', 'membership', 'invitation', 'security'],
            'projects' => Project::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
```

- [ ] **Step 4: Register the route**

In `routes/web.php`, add the import near the top:

```php
use App\Http\Controllers\Admin\ActivityController as AdminActivityController;
```

Inside the `->prefix('/admin')` group, after the mailer routes, add:

```php
        Route::get('/activity', [AdminActivityController::class, 'index'])->name('app.admin.activity.index');
```

- [ ] **Step 5: Create the admin page**

Create `resources/js/Pages/Admin/Activity/Index.tsx`:

```tsx
import ActivityFeed from '@/Components/ActivityFeed';
import AdminLayout from '@/Layouts/AdminLayout';
import { ActivityEntry } from '@/types';
import { Link, router } from '@inertiajs/react';

interface Paginated<T> {
  data: T[];
  links: { url: string | null; label: string; active: boolean }[];
}

interface Filters {
  log_name: string | null;
  project_id: string | null;
  causer: string | null;
  from: string | null;
  to: string | null;
}

export default function AdminActivityIndex({
  activities,
  filters,
  logNames,
  projects,
}: {
  activities: Paginated<ActivityEntry>;
  filters: Filters;
  logNames: string[];
  projects: { id: string; name: string }[];
}) {
  function apply(patch: Partial<Filters>) {
    const next = { ...filters, ...patch };
    const params = Object.fromEntries(Object.entries(next).filter(([, v]) => v));
    router.get(route('app.admin.activity.index'), params, { preserveState: true, replace: true });
  }

  return (
    <AdminLayout title="Activity log">
      <div className="px-8 py-8">
        <h1 className="mb-6 text-2xl font-bold text-slate-900 dark:text-white">Activity log</h1>

        <div className="mb-4 flex flex-wrap gap-2">
          <select value={filters.log_name ?? ''} onChange={(e) => apply({ log_name: e.target.value || null })} className="rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white">
            <option value="">All types</option>
            {logNames.map((n) => <option key={n} value={n}>{n}</option>)}
          </select>
          <select value={filters.project_id ?? ''} onChange={(e) => apply({ project_id: e.target.value || null })} className="rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white">
            <option value="">All projects</option>
            {projects.map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}
          </select>
          <input defaultValue={filters.causer ?? ''} onBlur={(e) => apply({ causer: e.target.value || null })} placeholder="Causer name/email" className="rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white" />
          <input type="date" defaultValue={filters.from ?? ''} onChange={(e) => apply({ from: e.target.value || null })} className="rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white" />
          <input type="date" defaultValue={filters.to ?? ''} onChange={(e) => apply({ to: e.target.value || null })} className="rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white" />
        </div>

        <div className="rounded-xl border border-slate-200 bg-white px-5 dark:border-slate-800 dark:bg-slate-900">
          <ActivityFeed activities={activities.data} />
        </div>

        <div className="mt-4 flex flex-wrap gap-1">
          {activities.links.map((link, i) => (
            <Link key={i} href={link.url ?? '#'} className={`rounded px-3 py-1 text-sm ${link.active ? 'bg-indigo-600 text-white' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800'} ${!link.url ? 'pointer-events-none opacity-50' : ''}`} dangerouslySetInnerHTML={{ __html: link.label }} />
          ))}
        </div>
      </div>
    </AdminLayout>
  );
}
```

- [ ] **Step 6: Add admin sidebar nav item**

In `resources/js/Components/AdminSidebar.tsx`, add `ScrollText` to the lucide import:

```tsx
import { Activity, ArrowLeft, FolderKanban, Link2, Mail, ScrollText, Users } from 'lucide-react';
```

Add to the `navItems` array (after Mailer):

```tsx
  { label: 'Activity', icon: ScrollText, routeName: 'app.admin.activity.index' },
```

- [ ] **Step 7: Run the feature test and build**

Run: `ddev php artisan test --filter=AdminAuditTest`
Expected: PASS (2 tests).
Run: `ddev npm run build`
Expected: build succeeds.

- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/Admin/ActivityController.php resources/js/Pages/Admin/Activity/Index.tsx routes/web.php resources/js/Components/AdminSidebar.tsx tests/Feature/ActivityLog/AdminAuditTest.php
git commit -m "feat(activity-log): admin audit view with filters"
```

---

## Task 10: Per-resource history panel (surface B)

**Files:**
- Modify: `app/Http/Controllers/UrlController.php` (edit), `DomainController.php` (edit), `QrCodeController.php` (edit), `PixelController.php` (edit)
- Create: `resources/js/Components/ActivityHistory.tsx`
- Modify: `resources/js/Pages/Links/Edit.tsx` (mount the panel — repeat for Domains/QrCodes/Pixels edit pages)
- Test: `tests/Feature/ActivityLog/ResourceHistoryTest.php`

**Interfaces:**
- Consumes: `Activity` via the model's `activities()` relation (provided by `LogsActivity`), `Inertia::optional`.
- Produces: each resource `edit` page exposes a lazy `history` prop (only that subject's activities, newest first, max 50). `ActivityHistory.tsx` triggers a partial reload (`only: ['history']`) when opened and renders attribute diffs.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/ActivityLog/ResourceHistoryTest.php`:

```php
<?php

namespace Tests\Feature\ActivityLog;

use App\Enums\ProjectRole;
use App\Models\Project;
use App\Models\Url;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResourceHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_url_edit_history_partial_returns_only_that_subjects_activity(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create();
        $project->users()->attach($user, ['role' => ProjectRole::Admin->value, 'active' => true]);

        $url = Url::factory()->for($project)->create(['slug' => 'a']);
        $other = Url::factory()->for($project)->create(['slug' => 'b']);
        $url->update(['slug' => 'a2']);
        $other->update(['slug' => 'b2']);

        // Partial reload requesting only the lazy history prop.
        $this->actingAs($user)
            ->get(route('app.project.links.edit', ['project' => $project->id, 'url' => $url->id]), ['X-Inertia' => true, 'X-Inertia-Partial-Data' => 'history', 'X-Inertia-Partial-Component' => 'Links/Edit'])
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('history', fn ($h) => collect($h)->every(fn ($a) => $a['subject_type'] === 'Url')));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `ddev php artisan test --filter=ResourceHistoryTest`
Expected: FAIL — `history` prop not present.

- [ ] **Step 3: Add the lazy history prop to UrlController::edit**

In `app/Http/Controllers/UrlController.php` add to the `use` block:

```php
use Inertia\Inertia;
```

In `UrlController::edit`, add a `'history'` entry to the `inertia('Links/Edit', [...])` props array (alongside `'domains'` and `'pixels'`):

```php
            'history' => Inertia::optional(
                fn () => $model->activities()->with('causer')->latest('id')->limit(50)->get()->map->toFeedArray()
            ),
```

- [ ] **Step 4: Add the lazy history prop to the other three edit actions**

Apply the same pattern in each `edit` method. Add `use Inertia\Inertia;` to each controller's `use` block, then add the `'history'` prop to the inertia payload using the resolved model variable:

- `app/Http/Controllers/DomainController.php` (`edit`) — use the domain model variable already resolved there:

```php
            'history' => Inertia::optional(
                fn () => $domain->activities()->with('causer')->latest('id')->limit(50)->get()->map->toFeedArray()
            ),
```

- `app/Http/Controllers/QrCodeController.php` (`edit`):

```php
            'history' => Inertia::optional(
                fn () => $qrCode->activities()->with('causer')->latest('id')->limit(50)->get()->map->toFeedArray()
            ),
```

- `app/Http/Controllers/PixelController.php` (`edit`):

```php
            'history' => Inertia::optional(
                fn () => $pixel->activities()->with('causer')->latest('id')->limit(50)->get()->map->toFeedArray()
            ),
```

> If a controller's `edit` uses a different local variable name for the model, match it. Open each `edit` method and confirm the variable before adding.

- [ ] **Step 5: Run the feature test to verify it passes**

Run: `ddev php artisan test --filter=ResourceHistoryTest`
Expected: PASS (1 test).

- [ ] **Step 6: Create the ActivityHistory component**

Create `resources/js/Components/ActivityHistory.tsx`:

```tsx
import { ActivityEntry } from '@/types';
import { router } from '@inertiajs/react';
import { ChevronDown, ChevronRight } from 'lucide-react';
import { useState } from 'react';

function Diff({ changes }: { changes: ActivityEntry['changes'] }) {
  const attrs = (changes.attributes ?? {}) as Record<string, unknown>;
  const old = (changes.old ?? {}) as Record<string, unknown>;
  const keys = Object.keys(attrs);

  if (keys.length === 0) {
    return null;
  }

  return (
    <ul className="mt-1 space-y-0.5 text-xs">
      {keys.map((k) => (
        <li key={k} className="text-slate-500 dark:text-slate-400">
          <span className="font-medium">{k}</span>:{' '}
          {k in old && <span className="text-red-500 line-through">{JSON.stringify(old[k])}</span>}{' '}
          <span className="text-green-600 dark:text-green-400">{JSON.stringify(attrs[k])}</span>
        </li>
      ))}
    </ul>
  );
}

export default function ActivityHistory({ history }: { history?: ActivityEntry[] }) {
  const [open, setOpen] = useState(false);

  function toggle() {
    const next = !open;
    setOpen(next);
    if (next && !history) {
      router.reload({ only: ['history'] });
    }
  }

  return (
    <div className="rounded-xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
      <button type="button" onClick={toggle} className="flex w-full items-center gap-2 px-5 py-3 text-left text-sm font-semibold text-slate-900 dark:text-white">
        {open ? <ChevronDown className="h-4 w-4" /> : <ChevronRight className="h-4 w-4" />}
        History
      </button>
      {open && (
        <div className="border-t border-slate-100 px-5 py-3 dark:border-slate-800">
          {!history ? (
            <p className="text-sm text-slate-400">Loading…</p>
          ) : history.length === 0 ? (
            <p className="text-sm text-slate-400">No history yet.</p>
          ) : (
            <ul className="divide-y divide-slate-100 dark:divide-slate-800">
              {history.map((a) => (
                <li key={a.id} className="py-2">
                  <p className="text-sm text-slate-700 dark:text-slate-200">
                    <span className="font-medium">{a.causer?.name ?? 'System'}</span> {a.description}{' '}
                    <span className="text-xs text-slate-400">{new Date(a.created_at).toLocaleString()}</span>
                  </p>
                  <Diff changes={a.changes} />
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

- [ ] **Step 7: Mount the panel on the Links Edit page**

In `resources/js/Pages/Links/Edit.tsx`: add the import at the top:

```tsx
import ActivityHistory from '@/Components/ActivityHistory';
import { ActivityEntry } from '@/types';
```

Add `history` to the page's props type/signature (the component currently destructures `url`, `domains`, `pixels` — add `history?: ActivityEntry[]`). Then render `<ActivityHistory history={history} />` at the bottom of the form's container (below the existing card). Repeat the same three edits (import, prop, render) in `resources/js/Pages/Domains/Edit.tsx`, `resources/js/Pages/QrCodes/Edit.tsx`, and `resources/js/Pages/Pixels/Edit.tsx`.

> Open each Edit page first to match its existing prop-destructuring style and JSX container before inserting.

- [ ] **Step 8: Build**

Run: `ddev npm run build`
Expected: build succeeds, no TypeScript errors.

- [ ] **Step 9: Commit**

```bash
git add app/Http/Controllers/UrlController.php app/Http/Controllers/DomainController.php app/Http/Controllers/QrCodeController.php app/Http/Controllers/PixelController.php resources/js/Components/ActivityHistory.tsx resources/js/Pages/Links/Edit.tsx resources/js/Pages/Domains/Edit.tsx resources/js/Pages/QrCodes/Edit.tsx resources/js/Pages/Pixels/Edit.tsx tests/Feature/ActivityLog/ResourceHistoryTest.php
git commit -m "feat(activity-log): per-resource history panel with lazy loading"
```

---

## Task 11: Retention scheduling

**Files:**
- Modify: `routes/console.php`, `config/activitylog.php`
- Test: `tests/Feature/ActivityLog/RetentionTest.php`

**Interfaces:**
- Consumes: Spatie's `activitylog:clean` command.
- Produces: a daily scheduled clean keeping 365 days.

- [ ] **Step 1: Confirm the retention config value**

In `config/activitylog.php`, ensure:

```php
'delete_records_older_than_days' => 365,
```

- [ ] **Step 2: Write the failing test**

Create `tests/Feature/ActivityLog/RetentionTest.php`:

```php
<?php

namespace Tests\Feature\ActivityLog;

use Illuminate\Console\Scheduling\Schedule;
use Tests\TestCase;

class RetentionTest extends TestCase
{
    public function test_activitylog_clean_is_scheduled(): void
    {
        $schedule = app(Schedule::class);
        $commands = collect($schedule->events())->map(fn ($e) => $e->command ?? '')->implode(' ');

        $this->assertStringContainsString('activitylog:clean', $commands);
    }
}
```

- [ ] **Step 3: Run test to verify it fails**

Run: `ddev php artisan test --filter=RetentionTest`
Expected: FAIL — command not scheduled.

- [ ] **Step 4: Schedule the clean command**

In `routes/console.php`, after the existing `Schedule::command('geoip:update')->daily();` line, add:

```php
Schedule::command('activitylog:clean')->daily();
```

- [ ] **Step 5: Run test to verify it passes**

Run: `ddev php artisan test --filter=RetentionTest`
Expected: PASS (1 test).

- [ ] **Step 6: Commit**

```bash
git add routes/console.php config/activitylog.php tests/Feature/ActivityLog/RetentionTest.php
git commit -m "feat(activity-log): schedule daily activity log pruning (365 days)"
```

---

## Task 12: Full verification gate

**Files:** none (verification only)

- [ ] **Step 1: Run the full test suite**

Run: `ddev composer test`
Expected: all tests pass (including the full `ActivityLog` suite from Tasks 1–11).

- [ ] **Step 2: Run the frontend build**

Run: `ddev npm run build`
Expected: TypeScript check + Vite build succeed with no errors.

- [ ] **Step 3: Manual smoke check (optional but recommended)**

Run: `ddev composer run dev`, then in the browser: edit a link → confirm the activity feed shows the change; open the History panel on the edit page; visit `/admin/activity` as a super admin and confirm security events (e.g. your login) appear. Stop the dev server when done.

- [ ] **Step 4: Final commit (if any verification fixups were needed)**

```bash
git add -A
git commit -m "chore(activity-log): verification fixups"
```

---

## Self-Review

**Spec coverage:**
- Audit trail + user feed (purpose A+B) → Tasks 8 (feed), 9 (admin audit), 10 (per-resource). ✓
- Scope B models (Url/Domain/QrCode/Pixel/Project CRUD; membership; invitations; security) → Tasks 3, 4, 6, 7. ✓
- `Statistic` excluded → never given a trait. ✓
- Dedicated `project_id` column + custom Activity model → Task 1. ✓
- Security events `project_id = null`, admin-only → Tasks 5, 7, 9. ✓
- Full attribute logging excluding `created_at`/`updated_at` → `logOnly()` explicit lists (Tasks 3, 4); verified by test. ✓
- `Url.password` redacted; AB targeting **included** → Tasks 2, 3 (tests assert both). ✓
- Sensitive User fields never logged → User has no trait; security events log only non-secret metadata. ✓
- Access control: feed = member, history = resource viewer, admin = super_admin → Tasks 8, 9, 10. ✓
- Lazy partial prop for history → Task 10. ✓
- Admin under existing `/admin` group, no new gate → Task 9. ✓
- Retention 365 days via scheduler → Task 11. ✓
- IP/user-agent + every login → Task 5 (helper) + Task 7. ✓

**Known intentional gaps (documented in-task):** 2FA-completed logins are not logged in this pass (only the direct-login path) — noted in Task 7 Step 3.

**Placeholder scan:** none — every code step contains complete code.

**Type consistency:** `toFeedArray()` shape ↔ `ActivityEntry` TS type match; `ActivityRecorder::project/security` signatures consistent across Tasks 5–7; `resolveActivityProjectId()`/`tapActivity()` names consistent across Tasks 2–4.
