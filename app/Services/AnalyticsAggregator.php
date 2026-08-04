<?php

namespace App\Services;

use App\Models\PageView;
use App\Models\Visit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AnalyticsAggregator
{
    private function base(string $siteId, int $days): Builder
    {
        return PageView::query()
            ->where('site_id', $siteId)
            ->where('is_bot', false)
            ->where('created_at', '>=', now()->subDays($days - 1)->startOfDay());
    }

    private function visitBase(string $siteId, int $days): Builder
    {
        return Visit::query()
            ->where('site_id', $siteId)
            ->where('is_bot', false)
            ->where('started_at', '>=', now()->subDays($days - 1)->startOfDay());
    }

    /** @return list<array{date: string, views: int, visitors: int}> */
    public function pageViewsByDay(string $siteId, int $days): array
    {
        $now = now();
        $rows = $this->base($siteId, $days)
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as views'),
                DB::raw('COUNT(DISTINCT visitor_hash) as visitors'),
            )
            ->groupBy('date')->get()->keyBy('date');

        $out = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = $now->copy()->subDays($i)->format('Y-m-d');
            $row = $rows->get($date);
            $out[] = [
                'date' => $date,
                'views' => (int) ($row->views ?? 0),
                'visitors' => (int) ($row->visitors ?? 0),
            ];
        }

        return $out;
    }

    public function totalPageViews(string $siteId, int $days): int
    {
        return $this->base($siteId, $days)->count();
    }

    public function uniqueVisitors(string $siteId, int $days): int
    {
        return $this->base($siteId, $days)->distinct('visitor_hash')->count('visitor_hash');
    }

    public function bounceRate(string $siteId, int $days): float
    {
        $total = $this->visitBase($siteId, $days)->count();
        if ($total === 0) {
            return 0.0;
        }
        $bounced = $this->visitBase($siteId, $days)->where('pageview_count', 1)->count();

        return round($bounced / $total * 100, 1);
    }

    public function avgDurationSeconds(string $siteId, int $days): int
    {
        // Driver-agnostic: the test suite runs on SQLite (:memory:), production on MariaDB.
        $driver = DB::connection()->getDriverName();
        $expr = $driver === 'sqlite'
            ? "AVG(strftime('%s', last_activity_at) - strftime('%s', started_at))"
            : 'AVG(TIMESTAMPDIFF(SECOND, started_at, last_activity_at))';

        $avg = $this->visitBase($siteId, $days)
            ->select(DB::raw("{$expr} as avg_seconds"))
            ->value('avg_seconds');

        return (int) round((float) $avg);
    }

    /** @return Collection<int, \stdClass> */
    public function topPaths(string $siteId, int $days, int $limit = 10): Collection
    {
        return $this->base($siteId, $days)
            ->select('path', DB::raw('COUNT(*) as count'))
            ->groupBy('path')->orderByDesc('count')->limit($limit)->get();
    }

    /** @return Collection<int, \stdClass> */
    public function topReferrers(string $siteId, int $days, int $limit = 10): Collection
    {
        return $this->base($siteId, $days)
            ->whereNotNull('referer_domain')->where('referer_domain', '!=', '')
            ->select('referer_domain', DB::raw('COUNT(*) as count'))
            ->groupBy('referer_domain')->orderByDesc('count')->limit($limit)->get();
    }

    /** @return Collection<int, \stdClass> */
    public function breakdown(string $siteId, string $column, int $days, int $limit = 8): Collection
    {
        $allowed = ['country', 'browser', 'os', 'device', 'language'];
        if (! in_array($column, $allowed, true)) {
            throw new \InvalidArgumentException("Unknown breakdown column: {$column}");
        }

        return $this->base($siteId, $days)
            ->whereNotNull($column)->where($column, '!=', '')
            ->select($column, DB::raw('COUNT(*) as count'))
            ->groupBy($column)->orderByDesc('count')->limit($limit)->get();
    }
}
