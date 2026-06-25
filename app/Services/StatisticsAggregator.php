<?php

namespace App\Services;

use App\Models\Statistic;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class StatisticsAggregator
{
    /**
     * Base statistics query scoped to a project and (optionally) a single URL.
     */
    private function base(string $projectId, ?string $urlId): Builder
    {
        return Statistic::query()
            ->where('project_id', $projectId)
            ->when($urlId !== null, fn (Builder $q) => $q->where('url_id', $urlId));
    }

    /**
     * Daily total and unique (distinct-visitor_hash) clicks over the trailing window,
     * zero-filled so the chart has a continuous x-axis.
     *
     * @return list<array{date: string, clicks: int, unique: int}>
     */
    public function clicksByDay(string $projectId, ?string $urlId, int $days): array
    {
        $now = now();
        $since = $now->copy()->subDays($days - 1)->startOfDay();

        $rows = $this->base($projectId, $urlId)
            ->where('created_at', '>=', $since)
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as clicks'),
                DB::raw('COUNT(DISTINCT visitor_hash) as unique_clicks'),
            )
            ->groupBy('date')
            ->get()
            ->keyBy('date');

        $out = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = $now->copy()->subDays($i)->format('Y-m-d');
            $row = $rows->get($date);
            $out[] = [
                'date' => $date,
                'clicks' => (int) ($row->clicks ?? 0),
                'unique' => (int) ($row->unique_clicks ?? 0),
            ];
        }

        return $out;
    }

    /**
     * Daily total and unique clicks across an inclusive [start, end] day span,
     * zero-filled for a continuous x-axis.
     *
     * @return list<array{date: string, clicks: int, unique: int}>
     */
    public function clicksByDayBetween(string $projectId, ?string $urlId, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $startDay = $start->startOfDay();
        $endDay = $end->startOfDay();
        $days = $startDay->diffInDays($endDay) + 1;

        $rows = $this->base($projectId, $urlId)
            ->whereBetween('created_at', [$start->startOfDay(), $end->endOfDay()])
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as clicks'),
                DB::raw('COUNT(DISTINCT visitor_hash) as unique_clicks'),
            )
            ->groupBy('date')
            ->get()
            ->keyBy('date');

        $out = [];
        for ($i = 0; $i < $days; $i++) {
            $date = $startDay->addDays($i)->format('Y-m-d');
            $row = $rows->get($date);
            $out[] = [
                'date' => $date,
                'clicks' => (int) ($row->clicks ?? 0),
                'unique' => (int) ($row->unique_clicks ?? 0),
            ];
        }

        return $out;
    }

    /**
     * Total clicks, optionally restricted to a trailing window.
     */
    public function totalClicks(string $projectId, ?string $urlId, Carbon|CarbonImmutable|null $since = null, Carbon|CarbonImmutable|null $until = null): int
    {
        return $this->base($projectId, $urlId)
            ->when($since, fn (Builder $q) => $q->where('created_at', '>=', $since))
            ->when($until, fn (Builder $q) => $q->where('created_at', '<=', $until))
            ->count();
    }

    /**
     * Distinct-visitor clicks, optionally restricted to a trailing window.
     */
    public function uniqueClicks(string $projectId, ?string $urlId, Carbon|CarbonImmutable|null $since = null, Carbon|CarbonImmutable|null $until = null): int
    {
        return $this->base($projectId, $urlId)
            ->when($since, fn (Builder $q) => $q->where('created_at', '>=', $since))
            ->when($until, fn (Builder $q) => $q->where('created_at', '<=', $until))
            ->distinct('visitor_hash')
            ->count('visitor_hash');
    }

    /**
     * Top values for a column (country, city, browser, os, domain), keyed by
     * that column plus a `count`. Optionally restricted to a trailing window.
     *
     * @return Collection<int, \stdClass>
     */
    public function breakdown(string $projectId, ?string $urlId, string $column, Carbon|CarbonImmutable|null $since = null, Carbon|CarbonImmutable|null $until = null, int $limit = 8): Collection
    {
        $allowed = ['country', 'city', 'browser', 'os', 'domain'];
        if (! in_array($column, $allowed, true)) {
            throw new \InvalidArgumentException("Unknown breakdown column: {$column}");
        }

        return $this->base($projectId, $urlId)
            ->when($since, fn (Builder $q) => $q->where('created_at', '>=', $since))
            ->when($until, fn (Builder $q) => $q->where('created_at', '<=', $until))
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
     * @return Collection<int, Statistic>
     */
    public function recentClicks(string $projectId, ?string $urlId, Carbon|CarbonImmutable|null $since = null, Carbon|CarbonImmutable|null $until = null, int $limit = 50): Collection
    {
        return $this->base($projectId, $urlId)
            ->when($since, fn (Builder $q) => $q->where('created_at', '>=', $since))
            ->when($until, fn (Builder $q) => $q->where('created_at', '<=', $until))
            ->latest()
            ->limit($limit)
            ->get(['id', 'country', 'city', 'browser', 'os', 'domain', 'created_at']);
    }
}
