<?php

namespace App\Actions;

use App\Models\Dashboard;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Str;

class ResolveDefaultDashboard
{
    public function __invoke(User $user, Project $project): Dashboard
    {
        $existing = $user->dashboards()->where('project_id', $project->id)->orderBy('position')->first();
        if ($existing) {
            return $existing;
        }

        return $user->dashboards()->create([
            'project_id' => $project->id,
            'name' => 'Overview',
            'is_default' => true,
            'position' => 0,
            'widgets' => $this->seed(),
        ]);
    }

    /** @return array<int, array<string, mixed>> */
    private function seed(): array
    {
        $kpi = fn (string $metric, int $x) => ['id' => (string) Str::ulid(), 'type' => 'kpi', 'config' => ['metric' => $metric, 'days' => 30, 'title' => null], 'layout' => ['x' => $x, 'y' => 0, 'w' => 3, 'h' => 2]];

        return [
            $kpi('clicks', 0), $kpi('unique_visitors', 3), $kpi('active_links', 6), $kpi('avg_per_link', 9),
            ['id' => (string) Str::ulid(), 'type' => 'timeseries', 'config' => ['days' => 30, 'title' => null], 'layout' => ['x' => 0, 'y' => 2, 'w' => 8, 'h' => 5]],
            ['id' => (string) Str::ulid(), 'type' => 'top_list', 'config' => ['dimension' => 'links', 'limit' => 5, 'days' => 30, 'title' => null], 'layout' => ['x' => 8, 'y' => 2, 'w' => 4, 'h' => 5]],
            ['id' => (string) Str::ulid(), 'type' => 'geo_map', 'config' => ['days' => 30, 'title' => null], 'layout' => ['x' => 0, 'y' => 7, 'w' => 8, 'h' => 6]],
            ['id' => (string) Str::ulid(), 'type' => 'activity', 'config' => ['limit' => 6, 'title' => null], 'layout' => ['x' => 8, 'y' => 7, 'w' => 4, 'h' => 6]],
            ['id' => (string) Str::ulid(), 'type' => 'quick_actions', 'config' => ['title' => null], 'layout' => ['x' => 0, 'y' => 13, 'w' => 12, 'h' => 2]],
        ];
    }
}
