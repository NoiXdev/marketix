# Reset Link Stats — Design

**Date:** 2026-06-21
**Branch:** new-features

## Goal

Let users permanently reset the click statistics for a single short link from
its stats page, behind a typed confirmation, with the action recorded in the
link's activity history.

## Background

Each click is stored as a row in the `statistics` table (`url_id` FK,
soft-deletable). Two cached counters live on the `urls` row: `clicks` and
`unique_clicks`, incremented atomically by `RecordClickStatisticJob`. The stats
shown on the link's Show page are computed on the fly from the `statistics`
table via `StatisticsAggregator`, while the headline counters come from the
cached columns. A reset must therefore clear both the rows and the counters.

## Behavior

- **Permanent delete.** Statistics rows are hard-deleted (`forceDelete`) so they
  are truly gone and the DB stays small. No undo.
- **Scope:** one link only. No project-wide reset.
- **Authorization:** project scoping via `$project->urls()->findOrFail($url)`,
  identical to `destroy()`/`toggleStatus()`. No new permission flag (consistent
  with existing destructive actions).

## Backend

### Route
Add to the link routes block in `routes/web.php` (auth + `ProjectBindingMiddleware`):

```
DELETE /project/{project}/links/{url}/stats
  -> UrlController@resetStats
  name: app.project.links.stats.reset
```

### Controller — `UrlController::resetStats(Request $request, string $url)`
Follows the `destroy()` pattern:

```php
public function resetStats(Request $request, string $url)
{
    $project = $request->get('project');
    $model = $project->urls()->findOrFail($url);

    DB::transaction(function () use ($model, $request) {
        Statistic::where('url_id', $model->id)->forceDelete();
        $model->forceFill(['clicks' => 0, 'unique_clicks' => 0])->save();

        activity('url')
            ->performedOn($model)
            ->causedBy($request->user())
            ->event('stats_reset')
            ->log('Stats reset');
    });

    return back()->with('success', 'Statistics reset.');
}
```

Notes:
- `forceDelete()` bypasses the `Statistic` soft-delete so rows are removed.
- `forceFill()` is used because `clicks`/`unique_clicks` are not in `$fillable`
  (they are maintained internally). Confirm during implementation; if they are
  guarded, set the attributes directly then `save()`.
- The activity entry uses the `url` log name so the existing `ActivityHistory`
  component renders it alongside other link events.

## Frontend — `resources/js/Pages/Links/Show.tsx`

- Add a **"Reset stats"** button near the existing Edit button, with
  destructive / de-emphasized styling.
- Clicking opens a confirmation dialog that requires the user to **type the
  link's slug** to enable the confirm button (guard against accidental,
  irreversible loss).
- On confirm:
  ```ts
  router.delete(route('app.project.links.stats.reset', { project: projectId, url: link.id }))
  ```
  (Ziggy object-param form per project convention.) Inertia reloads the Show
  page, which now displays zeroed stats and the new activity entry.

## Testing

Feature test (`tests/Feature`):
- Seed a link with several `statistics` rows and nonzero `clicks` /
  `unique_clicks`.
- Hit the reset route as an authorized project member.
- Assert: no `statistics` rows remain for that link (force-deleted), both cached
  counters are `0`, and an activity entry with event `stats_reset` exists for
  the link.
- Cross-tenant guard: a link belonging to **another project** cannot be reset
  via the current project route (expect 404).
- Use `route()` (not bare paths) per the project's test-host convention.

## Out of scope (YAGNI)

- Project-wide / bulk reset.
- Soft-delete or snapshot/baseline modes (explicitly chose permanent delete).
- New per-user permission flag.
