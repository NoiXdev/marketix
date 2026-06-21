# Reset Link Stats Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let a user permanently reset the click statistics for a single short link from its stats page, behind a typed-slug confirmation, with the reset recorded in the link's activity history.

**Architecture:** A new project-scoped `DELETE` route hits `UrlController::resetStats`, which force-deletes the link's `statistics` rows, zeroes the cached `clicks`/`unique_clicks` counters, and writes a `stats_reset` activity entry — all in one DB transaction. The Links/Show page gets a destructive "Reset stats" button that opens a SweetAlert2 typed-confirmation dialog (new `confirmTyped` helper) before issuing the Inertia `router.delete`.

**Tech Stack:** Laravel 13 / PHP 8.3, Inertia + React 19 + TypeScript, SweetAlert2, spatie/laravel-activitylog, server-side i18n catalogs under `lang/{en,nl,de,fr}/links.php`.

## Global Constraints

- Run all php/composer/npm via DDEV: `ddev php`, `ddev composer`, `ddev npm`, `ddev exec`.
- Frontend gate is `ddev npm run build` (ESLint is broken in this repo — do NOT rely on `npm run lint`).
- In React, `route()` always takes object params: `route(name, { project: id, url: id })` — never bare values (Ziggy bug workaround).
- In feature tests, address routes via `route(...)`, never bare path strings (phpunit host mismatch gotcha).
- Authorization is project scoping only (`$project->urls()->findOrFail($url)`) — no new permission flag, matching `destroy()`/`toggleStatus()`.

---

## File Structure

- **Modify** `routes/web.php` — add the reset-stats route in the Links block.
- **Modify** `app/Http/Controllers/UrlController.php` — add `resetStats()` method + imports.
- **Create** `tests/Feature/ResetLinkStatsTest.php` — feature tests for the endpoint.
- **Modify** `resources/js/lib/confirm.ts` — add `confirmTyped` helper.
- **Modify** `resources/js/Pages/Links/Show.tsx` — add the "Reset stats" button + handler.
- **Modify** `lang/en/links.php`, `lang/nl/links.php`, `lang/de/links.php`, `lang/fr/links.php` — add `reset_stats` strings.

---

## Task 1: Backend — reset-stats endpoint (route + controller)

**Files:**
- Modify: `routes/web.php` (Links block, around line 106)
- Modify: `app/Http/Controllers/UrlController.php` (add method after `destroy()`, line 176; add imports at top)
- Test: `tests/Feature/ResetLinkStatsTest.php` (create)

**Interfaces:**
- Produces: route name `app.project.links.stats.reset`, method `DELETE /project/{project}/links/{url}/stats` → `UrlController@resetStats`. The frontend (Task 2) calls this route name.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/ResetLinkStatsTest.php`:

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
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class ResetLinkStatsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: Project, 2: Url}
     */
    private function makeProjectWithUrl(string $slug = 'promo'): array
    {
        $user = User::factory()->create();
        $project = Project::create(['name' => 'Acme']);
        $user->projects()->attach($project);
        $domain = Domain::firstOrCreate(['project_id' => $project->id, 'name' => 'links.test']);

        $url = Url::create([
            'project_id' => $project->id,
            'domain_id' => $domain->id,
            'user_id' => $user->id,
            'slug' => $slug,
            'url' => 'https://example.com/default',
            'type' => RedirectType::cases()[0],
            'status' => UrlStatus::ACTIVATED,
            'archived' => false,
            'clicks' => 5,
            'unique_clicks' => 3,
        ]);

        return [$user, $project, $url];
    }

    public function test_it_permanently_deletes_statistics_and_zeroes_counters(): void
    {
        [$user, $project, $url] = $this->makeProjectWithUrl();

        Statistic::factory()->forUrl($url)->create(['ip' => '10.0.0.1']);
        Statistic::factory()->forUrl($url)->create(['ip' => '10.0.0.2']);

        $this->actingAs($user)
            ->delete(route('app.project.links.stats.reset', ['project' => $project->id, 'url' => $url->id]))
            ->assertRedirect();

        // Rows are force-deleted (not just soft-deleted) — table is empty.
        $this->assertDatabaseCount('statistics', 0);

        $url->refresh();
        $this->assertSame(0, (int) $url->clicks);
        $this->assertSame(0, (int) $url->unique_clicks);
    }

    public function test_it_records_a_stats_reset_activity_entry(): void
    {
        [$user, $project, $url] = $this->makeProjectWithUrl();
        Statistic::factory()->forUrl($url)->create(['ip' => '10.0.0.1']);

        $this->actingAs($user)
            ->delete(route('app.project.links.stats.reset', ['project' => $project->id, 'url' => $url->id]));

        $this->assertTrue(
            Activity::where('event', 'stats_reset')
                ->where('subject_id', $url->id)
                ->where('causer_id', $user->id)
                ->exists()
        );
    }

    public function test_guests_are_redirected_to_login(): void
    {
        [, $project, $url] = $this->makeProjectWithUrl();

        $this->delete(route('app.project.links.stats.reset', ['project' => $project->id, 'url' => $url->id]))
            ->assertRedirect(route('app.auth.show-login'));
    }

    public function test_a_user_cannot_reset_a_link_in_another_project(): void
    {
        [$user] = $this->makeProjectWithUrl('mine');
        [, $otherProject, $otherUrl] = $this->makeProjectWithUrl('theirs');

        $this->actingAs($user)
            ->delete(route('app.project.links.stats.reset', ['project' => $otherProject->id, 'url' => $otherUrl->id]))
            ->assertForbidden();
    }

    public function test_resetting_a_foreign_url_in_own_project_is_not_found(): void
    {
        [$user, $project] = $this->makeProjectWithUrl('mine');
        [, , $foreignUrl] = $this->makeProjectWithUrl('theirs');

        $this->actingAs($user)
            ->delete(route('app.project.links.stats.reset', ['project' => $project->id, 'url' => $foreignUrl->id]))
            ->assertNotFound();
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `ddev php artisan test --filter=ResetLinkStatsTest`
Expected: FAIL — route `app.project.links.stats.reset` is not defined (route-not-found / `RouteNotFoundException`).

- [ ] **Step 3: Add the route**

In `routes/web.php`, immediately after the `destroy` line (line 106):

```php
            Route::delete('/links/{url}', [UrlController::class, 'destroy'])->name('app.project.links.destroy');
            Route::delete('/links/{url}/stats', [UrlController::class, 'resetStats'])->name('app.project.links.stats.reset');
```

- [ ] **Step 4: Add controller imports**

At the top of `app/Http/Controllers/UrlController.php`, add to the `use` block (after the existing imports, lines 5-9):

```php
use App\Models\Statistic;
use Illuminate\Support\Facades\DB;
```

- [ ] **Step 5: Add the `resetStats` method**

In `app/Http/Controllers/UrlController.php`, after `destroy()` (after line 176, before the closing `}`):

```php
    public function resetStats(Request $request, string $url)
    {
        $project = $request->get('project');
        $model = $project->urls()->findOrFail($url);

        DB::transaction(function () use ($model, $request) {
            Statistic::where('url_id', $model->id)->forceDelete();
            $model->update(['clicks' => 0, 'unique_clicks' => 0]);

            activity('url')
                ->performedOn($model)
                ->causedBy($request->user())
                ->event('stats_reset')
                ->log('Stats reset');
        });

        return back()->with('success', 'Statistics reset.');
    }
```

Note: `clicks`/`unique_clicks` are not in the model's activity `logOnly` list, so `update()` here does not produce a spurious "updated" activity entry — only the explicit `stats_reset` entry is logged.

- [ ] **Step 6: Run the test to verify it passes**

Run: `ddev php artisan test --filter=ResetLinkStatsTest`
Expected: PASS — all 5 tests green.

- [ ] **Step 7: Commit**

```bash
git add routes/web.php app/Http/Controllers/UrlController.php tests/Feature/ResetLinkStatsTest.php
git commit -m "feat(links): add reset-stats endpoint

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

## Task 2: Frontend — "Reset stats" button with typed confirmation

**Files:**
- Modify: `resources/js/lib/confirm.ts` (add `confirmTyped`)
- Modify: `resources/js/Pages/Links/Show.tsx` (button + handler + imports)
- Modify: `lang/en/links.php`, `lang/nl/links.php`, `lang/de/links.php`, `lang/fr/links.php` (add `reset_stats` block)

**Interfaces:**
- Consumes: route name `app.project.links.stats.reset` from Task 1.
- Produces: nothing consumed by later tasks (final task).

- [ ] **Step 1: Add the `confirmTyped` helper**

In `resources/js/lib/confirm.ts`, append at the end of the file (after `confirmAction`):

```ts
type ConfirmTypedOptions = {
    /** Dialog heading. */
    title?: string;
    /** Body text — names the entity and notes irreversibility. */
    text?: string;
    /** The exact string the user must type to enable confirmation. */
    match: string;
    /** Label for the confirm button. Defaults to 'Confirm'. */
    confirmText?: string;
    /** Validation message shown when the typed value does not match. */
    mismatchText?: string;
};

/**
 * Themed destructive confirmation that requires the user to type an exact
 * string (e.g. the entity's slug) before confirming. Resolves to `true` only
 * when confirmed with a matching value.
 */
export async function confirmTyped(opts: ConfirmTypedOptions): Promise<boolean> {
    const isDark = resolveIsDark(getStoredTheme());

    const result = await Swal.fire({
        title: opts.title ?? 'Are you sure?',
        text: opts.text,
        icon: 'warning',
        iconColor: '#ef4444',
        input: 'text',
        inputPlaceholder: opts.match,
        inputAttributes: { autocapitalize: 'off', autocorrect: 'off', autocomplete: 'off' },
        showCancelButton: true,
        confirmButtonText: opts.confirmText ?? 'Confirm',
        cancelButtonText: 'Cancel',
        focusCancel: false,
        reverseButtons: true,
        buttonsStyling: false,
        background: isDark ? '#1e293b' : '#ffffff',
        color: isDark ? '#e2e8f0' : '#0f172a',
        preConfirm: (value: string) => {
            if (value !== opts.match) {
                Swal.showValidationMessage(opts.mismatchText ?? `Please type "${opts.match}" to confirm.`);
                return false;
            }
            return true;
        },
        customClass: {
            popup: 'rounded-xl',
            confirmButton:
                'inline-flex items-center rounded-md bg-red-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-red-500 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2',
            cancelButton:
                'mr-3 inline-flex items-center rounded-md border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-slate-400 focus:ring-offset-2 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800',
        },
    });

    return result.isConfirmed;
}
```

- [ ] **Step 2: Add translation strings (all four locales)**

In `lang/en/links.php`, after the `delete` block (closes at line 35), insert:

```php
    'reset_stats' => [
        'button'   => 'Reset stats',
        'title'    => 'Reset statistics?',
        'confirm'  => 'This permanently deletes all click statistics for ":slug" and cannot be undone. Type the link slug to confirm.',
        'mismatch' => 'Please type ":slug" to confirm.',
    ],
```

In `lang/de/links.php`, after its `delete` block, insert:

```php
    'reset_stats' => [
        'button'   => 'Statistiken zurücksetzen',
        'title'    => 'Statistiken zurücksetzen?',
        'confirm'  => 'Dadurch werden alle Klickstatistiken für „:slug" dauerhaft gelöscht und können nicht wiederhergestellt werden. Geben Sie den Link-Slug ein, um zu bestätigen.',
        'mismatch' => 'Bitte geben Sie „:slug" ein, um zu bestätigen.',
    ],
```

In `lang/nl/links.php`, after its `delete` block, insert:

```php
    'reset_stats' => [
        'button'   => 'Statistieken resetten',
        'title'    => 'Statistieken resetten?',
        'confirm'  => 'Hiermee worden alle klikstatistieken voor ":slug" permanent verwijderd en dit kan niet ongedaan worden gemaakt. Typ de link-slug om te bevestigen.',
        'mismatch' => 'Typ ":slug" om te bevestigen.',
    ],
```

In `lang/fr/links.php`, after its `delete` block, insert:

```php
    'reset_stats' => [
        'button'   => 'Réinitialiser les statistiques',
        'title'    => 'Réinitialiser les statistiques ?',
        'confirm'  => 'Cela supprime définitivement toutes les statistiques de clics pour « :slug » et est irréversible. Saisissez le slug du lien pour confirmer.',
        'mismatch' => 'Veuillez saisir « :slug » pour confirmer.',
    ],
```

If a locale's `delete` block is not present or is laid out differently, place the `reset_stats` block adjacent to the other action/delete entries — key order within the array does not matter.

- [ ] **Step 3: Wire the button and handler into Show.tsx**

In `resources/js/Pages/Links/Show.tsx`:

(a) Add `confirmTyped` import near the top (after the existing `@/lib/i18n` import, line 3):

```tsx
import { confirmTyped } from '@/lib/confirm';
```

(b) Add `RotateCcw` to the existing `lucide-react` import (the icon list spanning lines 6-15) — add it alphabetically, e.g. after `QrCode as QrCodeIcon,`:

```tsx
  QrCode as QrCodeIcon,
  RotateCcw,
```

(c) Add the handler inside the `LinksShow` component, right after the existing `setDays` function (after line 152):

```tsx
  async function resetStats() {
    const ok = await confirmTyped({
      title: t('links.reset_stats.title'),
      text: t('links.reset_stats.confirm', { slug: link.slug }),
      match: link.slug,
      confirmText: t('links.reset_stats.button'),
      mismatchText: t('links.reset_stats.mismatch', { slug: link.slug }),
    });
    if (!ok) return;
    router.delete(route('app.project.links.stats.reset', { project: project!.id, url: link.id }));
  }
```

(d) Add the button in the header action group, immediately after the `ReportDownloadButton` (line 211), inside the same `<div className="flex shrink-0 items-center gap-2">`:

```tsx
              <ReportDownloadButton projectId={project!.id} urlId={link.id} />
              <button
                onClick={resetStats}
                className="inline-flex items-center gap-2 rounded-md border border-red-200 px-3 py-1.5 text-sm font-semibold text-red-600 hover:bg-red-50 dark:border-red-900/40 dark:text-red-400 dark:hover:bg-red-900/20"
              >
                <RotateCcw className="h-4 w-4" />
                {t('links.reset_stats.button')}
              </button>
```

- [ ] **Step 4: Verify the build passes**

Run: `ddev npm run build`
Expected: TypeScript check + Vite build complete with no errors.

- [ ] **Step 5: Manual verification**

Run the app (`ddev composer dev` or the running dev server). On a link's stats page (`/project/{project}/links/{url}`):
1. Click **Reset stats** → dialog appears asking to type the slug.
2. Type the wrong value → confirm shows the mismatch validation message and does not submit.
3. Type the exact slug → confirm → page reloads with all-time clicks and unique clicks at 0 and charts/breakdowns empty.
4. Open the link's **Edit** page → the activity history shows a "Stats reset" entry attributed to the current user.

- [ ] **Step 6: Commit**

```bash
git add resources/js/lib/confirm.ts resources/js/Pages/Links/Show.tsx lang/en/links.php lang/de/links.php lang/nl/links.php lang/fr/links.php
git commit -m "feat(links): reset-stats button with typed confirmation on stats page

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

## Self-Review Notes

- **Spec coverage:** Permanent delete (Task 1, `forceDelete`) ✓; zero counters (Task 1, `update`) ✓; activity log entry (Task 1, `activity()`) ✓; stats-page typed-slug confirmation (Task 2, `confirmTyped`) ✓; project-scoped authorization, no new permission flag (Task 1) ✓; feature test incl. cross-tenant guard (Task 1) ✓; one-link-only scope (no bulk route) ✓.
- **Type/name consistency:** route name `app.project.links.stats.reset` and method `resetStats` are identical across route, controller, tests, and frontend. i18n keys `links.reset_stats.{button,title,confirm,mismatch}` match between the lang files and Show.tsx usage.
- **No placeholders:** every code and command step is concrete.
