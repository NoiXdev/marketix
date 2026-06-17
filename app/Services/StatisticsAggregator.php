<?php

namespace App\Services;

use App\Models\Statistic;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class StatisticsAggregator
{
    /**
     * Base statistics query scoped to a project and (optionally) a single URL.
     */
    private function base(int $projectId, ?int $urlId): Builder
    {
        return Statistic::query()
            ->where('project_id', $projectId)
            ->when($urlId !== null, fn (Builder $q) => $q->where('url_id', $urlId));
    }

    /**
     * Daily total and unique (distinct-IP) clicks over the trailing window,
     * zero-filled so the chart has a continuous x-axis.
     *
     * @return list<array{date: string, clicks: int, unique: int}>
     */
    public function clicksByDay(int $projectId, ?int $urlId, int $days): array
    {
        $now   = now();
        $since = $now->copy()->subDays($days - 1)->startOfDay();

        $rows = $this->base($projectId, $urlId)
            ->where('created_at', '>=', $since)
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as clicks'),
                DB::raw('COUNT(DISTINCT ip) as unique_clicks'),
            )
            ->groupBy('date')
            ->get()
            ->keyBy('date');

        $out = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date  = $now->copy()->subDays($i)->format('Y-m-d');
            $row   = $rows->get($date);
            $out[] = [
                'date'   => $date,
                'clicks' => (int) ($row->clicks ?? 0),
                'unique' => (int) ($row->unique_clicks ?? 0),
            ];
        }

        return $out;
    }

    /**
     * Total clicks, optionally restricted to a trailing window.
     */
    public function totalClicks(int $projectId, ?int $urlId, ?Carbon $since = null): int
    {
        return $this->base($projectId, $urlId)
            ->when($since, fn (Builder $q) => $q->where('created_at', '>=', $since))
            ->count();
    }

    /**
     * Distinct-IP clicks, optionally restricted to a trailing window.
     */
    public function uniqueClicks(int $projectId, ?int $urlId, ?Carbon $since = null): int
    {
        return $this->base($projectId, $urlId)
            ->when($since, fn (Builder $q) => $q->where('created_at', '>=', $since))
            ->distinct('ip')
            ->count('ip');
    }

    /**
     * Top values for a column (country, city, browser, os, domain), keyed by
     * that column plus a `count`. Optionally restricted to a trailing window.
     *
     * @return Collection<int, \stdClass>
     */
    public function breakdown(int $projectId, ?int $urlId, string $column, ?Carbon $since = null, int $limit = 8): Collection
    {
        $allowed = ['country', 'city', 'browser', 'os', 'domain'];
        if (! in_array($column, $allowed, true)) {
            throw new \InvalidArgumentException("Unknown breakdown column: {$column}");
        }

        return $this->base($projectId, $urlId)
            ->when($since, fn (Builder $q) => $q->where('created_at', '>=', $since))
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->select($column, DB::raw('COUNT(*) as count'))
            ->groupBy($column)
            ->orderByDesc('count')
            ->limit($limit)
            ->get();
    }

    /**
     * The most recent individual clicks, latest first.
     *
     * @return Collection<int, \App\Models\Statistic>
     */
    public function recentClicks(int $projectId, ?int $urlId, ?Carbon $since = null, int $limit = 50): Collection
    {
        return $this->base($projectId, $urlId)
            ->when($since, fn (Builder $q) => $q->where('created_at', '>=', $since))
            ->latest()
            ->limit($limit)
            ->get(['id', 'country', 'city', 'browser', 'os', 'domain', 'created_at']);
    }
}
