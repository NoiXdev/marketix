<?php

namespace App\Services;

use App\Enums\GoalType;
use App\Models\Goal;
use App\Models\PageView;
use App\Models\Site;
use App\Models\Visit;
use App\Support\Analytics\AnalyticsQuery;
use App\Support\Analytics\ChannelClassifier;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AnalyticsAggregator
{
    public const LIVE_WINDOW_MINUTES = 5;

    public const ENGAGED_MIN_SECONDS = 10;

    public const ENGAGED_MIN_PAGE_VIEWS = 2;

    private const PAGE_VIEW_FILTERS = ['hostname', 'path', 'language', 'country_code', 'region', 'city', 'browser', 'os', 'device'];

    private const VISIT_FILTERS = ['entry_path', 'exit_path', 'referer_domain', 'country_code', 'browser', 'os', 'device', 'utm_source', 'utm_medium', 'utm_campaign'];

    private const CAMPAIGN_COLUMNS = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'];

    /** @var array<string, ?string> */
    private array $siteDomains = [];

    /** @var array<string, list<string>> */
    private array $keyEvents = [];

    private function base(string $siteId, AnalyticsQuery|int $range): Builder
    {
        $query = AnalyticsQuery::from($range);

        $builder = PageView::query()
            ->where('site_id', $siteId)
            ->where('is_bot', false)
            ->whereBetween('created_at', [$query->from, $query->to]);

        return $this->applyPageViewFilters($builder, $siteId, $query->filters);
    }

    private function visitBase(string $siteId, AnalyticsQuery|int $range): Builder
    {
        $query = AnalyticsQuery::from($range);

        $builder = Visit::query()
            ->where('site_id', $siteId)
            ->where('is_bot', false)
            ->whereBetween('started_at', [$query->from, $query->to]);

        return $this->applyVisitFilters($builder, $siteId, $query->filters);
    }

    /** @param array<string, string> $filters */
    private function applyPageViewFilters(Builder $builder, string $siteId, array $filters): Builder
    {
        $visitFilters = [];
        foreach ($filters as $key => $value) {
            if (in_array($key, self::PAGE_VIEW_FILTERS, true)) {
                $builder->where($key, $value);
            } else {
                $visitFilters[$key] = $value;
            }
        }

        if ($visitFilters !== []) {
            $builder->whereIn('visit_id', $this->applyVisitFilters(
                Visit::query()->select('id')->where('site_id', $siteId),
                $siteId,
                $visitFilters,
            ));
        }

        return $builder;
    }

    /** @param array<string, string> $filters */
    private function applyVisitFilters(Builder $builder, string $siteId, array $filters): Builder
    {
        $pageViewFilters = [];
        foreach ($filters as $key => $value) {
            if ($key === 'channel') {
                [$sql, $bindings] = $this->channelExpression($siteId);
                $builder->whereRaw("({$sql}) = ?", [...$bindings, $value]);
            } elseif ($key === 'visitor_type') {
                if (in_array($value, AnalyticsQuery::VISITOR_TYPES, true)) {
                    $builder->where('is_returning', $value === 'returning');
                }
            } elseif (in_array($key, self::VISIT_FILTERS, true)) {
                $builder->where($key, $value);
            } else {
                $pageViewFilters[$key] = $value;
            }
        }

        if ($pageViewFilters !== []) {
            $pageViews = PageView::query()->select('visit_id')->where('site_id', $siteId);
            foreach ($pageViewFilters as $key => $value) {
                $pageViews->where($key, $value);
            }
            $builder->whereIn('id', $pageViews);
        }

        return $builder;
    }

    public function visitScope(string $siteId, AnalyticsQuery|int $range): ?Builder
    {
        $query = AnalyticsQuery::from($range);
        if (! $query->hasFilters()) {
            return null;
        }

        return $this->applyVisitFilters(Visit::query()->select('id')->where('site_id', $siteId), $siteId, $query->filters);
    }

    private function siteDomain(string $siteId): ?string
    {
        if (! array_key_exists($siteId, $this->siteDomains)) {
            $this->siteDomains[$siteId] = Site::withTrashed()->whereKey($siteId)->value('domain');
        }

        return $this->siteDomains[$siteId];
    }

    /** @return array{0: string, 1: list<string>} */
    public function channelExpression(string $siteId): array
    {
        return ChannelClassifier::expression($this->siteDomain($siteId));
    }

    private function isSqlite(): bool
    {
        return DB::connection()->getDriverName() === 'sqlite';
    }

    private function sessionSecondsExpression(): string
    {
        return $this->isSqlite()
            ? "(strftime('%s', last_activity_at) - strftime('%s', started_at))"
            : 'TIMESTAMPDIFF(SECOND, started_at, last_activity_at)';
    }

    private function durationExpression(): string
    {
        return 'AVG('.$this->sessionSecondsExpression().')';
    }

    /** @return list<string> */
    private function keyEvents(string $siteId): array
    {
        if (! array_key_exists($siteId, $this->keyEvents)) {
            $goalEvents = Goal::query()
                ->where('site_id', $siteId)
                ->where('type', GoalType::Event)
                ->pluck('match_value')
                ->all();

            $this->keyEvents[$siteId] = array_values(array_unique([RevenueAggregator::EVENT, ...$goalEvents]));
        }

        return $this->keyEvents[$siteId];
    }

    /** @return array{0: string, 1: list<string>} */
    private function engagedSumExpression(string $siteId): array
    {
        $events = $this->keyEvents($siteId);
        $placeholders = implode(', ', array_fill(0, count($events), '?'));
        $condition = 'pageview_count >= '.self::ENGAGED_MIN_PAGE_VIEWS
            .' OR '.$this->sessionSecondsExpression().' >= '.self::ENGAGED_MIN_SECONDS
            ." OR EXISTS (SELECT 1 FROM events WHERE events.visit_id = visits.id AND events.name IN ({$placeholders}))";

        return ["SUM(CASE WHEN {$condition} THEN 1 ELSE 0 END) as engaged", $events];
    }

    private function rate(int $part, int $total): ?float
    {
        return $total > 0 ? round($part / $total * 100, 1) : null;
    }

    private function campaignCondition(): string
    {
        return '('.implode(' OR ', array_map(fn ($c) => "{$c} IS NOT NULL", self::CAMPAIGN_COLUMNS)).')';
    }

    public function bucketExpression(string $column, AnalyticsQuery $query): string
    {
        $sqlite = $this->isSqlite();

        return match ($query->interval) {
            'hour' => $sqlite ? "strftime('%Y-%m-%d %H:00', {$column})" : "DATE_FORMAT({$column}, '%Y-%m-%d %H:00')",
            'week' => $sqlite
                ? "date({$column}, '-' || ((CAST(strftime('%w', {$column}) AS INTEGER) + 6) % 7) || ' days')"
                : "DATE_FORMAT(DATE_SUB({$column}, INTERVAL WEEKDAY({$column}) DAY), '%Y-%m-%d')",
            'month' => $sqlite ? "strftime('%Y-%m-01', {$column})" : "DATE_FORMAT({$column}, '%Y-%m-01')",
            default => "DATE({$column})",
        };
    }

    /** @return list<string> */
    public function bucketKeys(AnalyticsQuery $query): array
    {
        [$start, $step, $format, $end] = match ($query->interval) {
            'hour' => [$query->from->startOfDay(), 'addHour', 'Y-m-d H:00', $query->to->endOfDay()],
            'week' => [$query->from->startOfWeek(CarbonInterface::MONDAY), 'addWeek', 'Y-m-d', $query->to],
            'month' => [$query->from->startOfMonth(), 'addMonthNoOverflow', 'Y-m-01', $query->to],
            default => [$query->from->startOfDay(), 'addDay', 'Y-m-d', $query->to],
        };

        $keys = [];
        for ($date = $start; $date->lte($end); $date = $date->{$step}()) {
            $keys[] = $date->format($format);
        }

        return $keys;
    }

    /** @return list<array{date: string, views: int, visitors: int}> */
    public function pageViewsByDay(string $siteId, int $days): array
    {
        return array_map(
            fn (array $point) => ['date' => $point['date'], 'views' => $point['views'], 'visitors' => $point['visitors']],
            $this->timeseries($siteId, AnalyticsQuery::lastDays($days)->daily()),
        );
    }

    /**
     * @return list<array{date: string, views: int, visitors: int, sessions: int, bounce_rate: ?float, engagement_rate: ?float, avg_duration: ?int, campaign_share: ?float}>
     */
    public function timeseries(string $siteId, AnalyticsQuery|int $range): array
    {
        $query = AnalyticsQuery::from($range);

        $views = $this->base($siteId, $query)
            ->select(
                DB::raw($this->bucketExpression('created_at', $query).' as bucket'),
                DB::raw('COUNT(*) as views'),
                DB::raw('COUNT(DISTINCT visitor_hash) as visitors'),
            )
            ->groupBy('bucket')->get()->keyBy('bucket');

        $sessions = $this->visitBase($siteId, $query)
            ->select(
                DB::raw($this->bucketExpression('started_at', $query).' as bucket'),
                DB::raw('COUNT(*) as sessions'),
                DB::raw('SUM(CASE WHEN pageview_count = 1 THEN 1 ELSE 0 END) as bounced'),
                DB::raw($this->durationExpression().' as avg_duration'),
                DB::raw('SUM(CASE WHEN '.$this->campaignCondition().' THEN 1 ELSE 0 END) as campaign'),
            )
            ->selectRaw(...$this->engagedSumExpression($siteId))
            ->groupBy('bucket')->get()->keyBy('bucket');

        $out = [];
        foreach ($this->bucketKeys($query) as $key) {
            $v = $views->get($key);
            $s = $sessions->get($key);
            $count = (int) ($s->sessions ?? 0);

            $out[] = [
                'date' => $key,
                'views' => (int) ($v->views ?? 0),
                'visitors' => (int) ($v->visitors ?? 0),
                'sessions' => $count,
                'bounce_rate' => $count > 0 ? round((int) $s->bounced / $count * 100, 1) : null,
                'engagement_rate' => $this->rate((int) ($s->engaged ?? 0), $count),
                'avg_duration' => $count > 0 ? (int) round((float) $s->avg_duration) : null,
                'campaign_share' => $count > 0 ? round((int) $s->campaign / $count * 100, 1) : null,
            ];
        }

        return $out;
    }

    /**
     * @return array{page_views: int, visitors: int, sessions: int, bounce_rate: float, engagement_rate: float, avg_duration: int, campaign_share: float}
     */
    public function summary(string $siteId, AnalyticsQuery|int $range): array
    {
        $views = $this->base($siteId, $range)
            ->select(DB::raw('COUNT(*) as views'), DB::raw('COUNT(DISTINCT visitor_hash) as visitors'))
            ->first();

        $sessions = $this->visitBase($siteId, $range)
            ->select(
                DB::raw('COUNT(*) as sessions'),
                DB::raw('SUM(CASE WHEN pageview_count = 1 THEN 1 ELSE 0 END) as bounced'),
                DB::raw($this->durationExpression().' as avg_duration'),
                DB::raw('SUM(CASE WHEN '.$this->campaignCondition().' THEN 1 ELSE 0 END) as campaign'),
            )
            ->selectRaw(...$this->engagedSumExpression($siteId))
            ->first();

        $count = (int) ($sessions->sessions ?? 0);

        return [
            'page_views' => (int) ($views->views ?? 0),
            'visitors' => (int) ($views->visitors ?? 0),
            'sessions' => $count,
            'bounce_rate' => $count > 0 ? round((int) $sessions->bounced / $count * 100, 1) : 0.0,
            'engagement_rate' => $this->rate((int) ($sessions->engaged ?? 0), $count) ?? 0.0,
            'avg_duration' => $count > 0 ? (int) round((float) $sessions->avg_duration) : 0,
            'campaign_share' => $count > 0 ? round((int) $sessions->campaign / $count * 100, 1) : 0.0,
        ];
    }

    public function totalPageViews(string $siteId, AnalyticsQuery|int $range): int
    {
        return $this->base($siteId, $range)->count();
    }

    public function uniqueVisitors(string $siteId, AnalyticsQuery|int $range): int
    {
        return $this->base($siteId, $range)->distinct('visitor_hash')->count('visitor_hash');
    }

    public function bounceRate(string $siteId, AnalyticsQuery|int $range): float
    {
        $total = $this->visitBase($siteId, $range)->count();
        if ($total === 0) {
            return 0.0;
        }
        $bounced = $this->visitBase($siteId, $range)->where('pageview_count', 1)->count();

        return round($bounced / $total * 100, 1);
    }

    public function avgDurationSeconds(string $siteId, AnalyticsQuery|int $range): int
    {
        $avg = $this->visitBase($siteId, $range)
            ->select(DB::raw($this->durationExpression().' as avg_seconds'))
            ->value('avg_seconds');

        return (int) round((float) $avg);
    }

    public function liveVisitors(string $siteId): int
    {
        return Visit::query()
            ->where('site_id', $siteId)
            ->where('is_bot', false)
            ->where('last_activity_at', '>=', now()->subMinutes(self::LIVE_WINDOW_MINUTES))
            ->distinct('visitor_hash')
            ->count('visitor_hash');
    }

    /** @return Collection<int, \stdClass> */
    public function topPaths(string $siteId, AnalyticsQuery|int $range, int $limit = 10): Collection
    {
        return $this->base($siteId, $range)
            ->select(
                'path',
                DB::raw('COUNT(*) as count'),
                DB::raw('COUNT(DISTINCT visitor_hash) as visitors'),
                DB::raw('AVG(engaged_seconds) as avg_engaged'),
                DB::raw('AVG(scroll_depth) as avg_scroll'),
            )
            ->groupBy('path')->orderByDesc('count')->limit($limit)->get()
            ->each(function ($row) {
                $row->avg_engaged = $row->avg_engaged === null ? null : (int) round((float) $row->avg_engaged);
                $row->avg_scroll = $row->avg_scroll === null ? null : (int) round((float) $row->avg_scroll);
            })
            ->pipe(fn (Collection $rows) => $this->withTitles($rows, 'path', $siteId, $range));
    }

    /**
     * Adds the most recent page title seen in the range for each row's path, so
     * renamed pages show their current title.
     *
     * @param  Collection<int, \stdClass>  $rows
     * @return Collection<int, \stdClass>
     */
    private function withTitles(Collection $rows, string $column, string $siteId, AnalyticsQuery|int $range): Collection
    {
        $paths = $rows->pluck($column)->filter()->unique()->values()->all();

        $titles = $paths === [] ? collect() : $this->base($siteId, $range)
            ->whereIn('path', $paths)
            ->whereNotNull('title')->where('title', '!=', '')
            ->select('path', 'title', DB::raw('MAX(created_at) as last_seen'))
            ->groupBy('path', 'title')->get()
            ->sortBy('last_seen')
            ->pluck('title', 'path'); // newer titles overwrite older ones

        return $rows->each(fn ($row) => $row->title = $titles->get($row->{$column}));
    }

    /** @return Collection<int, \stdClass> */
    public function hostnames(string $siteId, AnalyticsQuery|int $range, int $limit = 10): Collection
    {
        return $this->base($siteId, $range)
            ->whereNotNull('hostname')->where('hostname', '!=', '')
            ->select('hostname', DB::raw('COUNT(*) as count'), DB::raw('COUNT(DISTINCT visitor_hash) as visitors'))
            ->groupBy('hostname')->orderByDesc('count')->limit($limit)->get();
    }

    /** @return Collection<int, \stdClass> */
    public function entryPages(string $siteId, AnalyticsQuery|int $range, int $limit = 10): Collection
    {
        return $this->visitBase($siteId, $range)
            ->select(
                'entry_path',
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(CASE WHEN pageview_count = 1 THEN 1 ELSE 0 END) as bounced'),
            )
            ->groupBy('entry_path')->orderByDesc('count')->limit($limit)->get()
            ->each(function ($row) {
                $row->bounce_rate = $row->count > 0 ? round((int) $row->bounced / (int) $row->count * 100, 1) : 0.0;
                unset($row->bounced);
            })
            ->pipe(fn (Collection $rows) => $this->withTitles($rows, 'entry_path', $siteId, $range));
    }

    /** @return Collection<int, \stdClass> */
    public function exitPages(string $siteId, AnalyticsQuery|int $range, int $limit = 10): Collection
    {
        return $this->visitBase($siteId, $range)
            ->select('exit_path', DB::raw('COUNT(*) as count'))
            ->groupBy('exit_path')->orderByDesc('count')->limit($limit)->get()
            ->pipe(fn (Collection $rows) => $this->withTitles($rows, 'exit_path', $siteId, $range));
    }

    /** @return Collection<int, \stdClass> */
    public function topReferrers(string $siteId, AnalyticsQuery|int $range, int $limit = 10): Collection
    {
        $builder = $this->visitBase($siteId, $range)
            ->whereNotNull('referer_domain')->where('referer_domain', '!=', '');

        $self = ChannelClassifier::normalizeDomain($this->siteDomain($siteId));
        if ($self !== null) {
            $builder->where('referer_domain', '!=', $self)
                ->where('referer_domain', 'not like', '%.'.$self);
        }

        return $builder
            ->select('referer_domain', DB::raw('COUNT(*) as count'))
            ->groupBy('referer_domain')->orderByDesc('count')->limit($limit)->get();
    }

    /** @return Collection<int, \stdClass> */
    public function channels(string $siteId, AnalyticsQuery|int $range): Collection
    {
        [$sql, $bindings] = $this->channelExpression($siteId);

        return $this->visitBase($siteId, $range)
            ->selectRaw("{$sql} as channel", $bindings)
            ->selectRaw('COUNT(*) as count')
            ->selectRaw('COUNT(DISTINCT visitor_hash) as visitors')
            ->selectRaw(...$this->engagedSumExpression($siteId))
            ->selectRaw($this->durationExpression().' as avg_duration')
            ->groupBy('channel')->orderByDesc('count')->get()
            ->each(function ($row) {
                $row->engagement_rate = $this->rate((int) $row->engaged, (int) $row->count);
                $row->avg_duration = (int) round((float) $row->avg_duration);
                unset($row->engaged);
            });
    }

    /** @return Collection<int, \stdClass> */
    public function breakdown(string $siteId, string $column, AnalyticsQuery|int $range, int $limit = 8): Collection
    {
        $allowed = ['country', 'browser', 'os', 'device', 'language'];
        if (! in_array($column, $allowed, true)) {
            throw new \InvalidArgumentException("Unknown breakdown column: {$column}");
        }

        return $this->base($siteId, $range)
            ->whereNotNull($column)->where($column, '!=', '')
            ->select($column, DB::raw('COUNT(*) as count'))
            ->groupBy($column)->orderByDesc('count')->limit($limit)->get();
    }

    /** @return Collection<int, \stdClass> */
    public function countriesWithCode(string $siteId, AnalyticsQuery|int $range, int $limit = 8): Collection
    {
        return $this->base($siteId, $range)
            ->whereNotNull('country')->where('country', '!=', '')
            ->select(
                'country',
                DB::raw('MAX(country_code) as country_code'),
                DB::raw('COUNT(*) as count'),
            )
            ->groupBy('country')->orderByDesc('count')->limit($limit)->get();
    }

    /**
     * Regions or cities, kept apart per country so equal names in different
     * countries do not merge.
     *
     * @return Collection<int, \stdClass>
     */
    public function locations(string $siteId, string $column, AnalyticsQuery|int $range, int $limit = 8): Collection
    {
        if (! in_array($column, ['region', 'city'], true)) {
            throw new \InvalidArgumentException("Unknown location column: {$column}");
        }

        return $this->base($siteId, $range)
            ->whereNotNull($column)->where($column, '!=', '')
            ->select($column, 'country_code', DB::raw('COUNT(*) as count'))
            ->groupBy($column, 'country_code')->orderByDesc('count')->limit($limit)->get();
    }

    /** @return Collection<int, \stdClass> */
    public function breakdownByCountryCode(string $siteId, AnalyticsQuery|int $range, int $limit = 250): Collection
    {
        return $this->base($siteId, $range)
            ->whereNotNull('country_code')->where('country_code', '!=', '')
            ->select(
                'country_code',
                DB::raw('MAX(country) as country'),
                DB::raw('COUNT(*) as count'),
            )
            ->groupBy('country_code')->orderByDesc('count')->limit($limit)->get();
    }

    /** @return list<array{0: int, 1: int}> */
    public function hourlyActivity(string $siteId, AnalyticsQuery|int $range): array
    {
        $bucket = $this->isSqlite()
            ? "strftime('%Y-%m-%d %H', started_at)"
            : "DATE_FORMAT(started_at, '%Y-%m-%d %H')";
        $timezone = config('app.timezone');

        return $this->visitBase($siteId, $range)
            ->select(DB::raw("{$bucket} as bucket"), DB::raw('COUNT(*) as count'))
            ->groupBy('bucket')->orderBy('bucket')->get()
            ->map(fn ($row) => [
                CarbonImmutable::parse($row->bucket.':00:00', $timezone)->getTimestamp(),
                (int) $row->count,
            ])
            ->all();
    }

    /** @return Collection<int, \stdClass> */
    public function utmBreakdown(string $siteId, string $column, AnalyticsQuery|int $range, int $limit = 8): Collection
    {
        $allowed = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'];
        if (! in_array($column, $allowed, true)) {
            throw new \InvalidArgumentException("Unknown UTM column: {$column}");
        }

        return $this->visitBase($siteId, $range)
            ->whereNotNull($column)->where($column, '!=', '')
            ->select(
                $column.' as value',
                DB::raw('COUNT(*) as sessions'),
                DB::raw('COUNT(DISTINCT visitor_hash) as visitors'),
            )
            ->groupBy($column)->orderByDesc('sessions')->limit($limit)->get();
    }

    /** @return Collection<int, \stdClass> */
    public function utmSourceMedium(string $siteId, AnalyticsQuery|int $range, int $limit = 8): Collection
    {
        $value = $this->isSqlite()
            ? "utm_source || ' / ' || COALESCE(utm_medium, '(none)')"
            : "CONCAT(utm_source, ' / ', COALESCE(utm_medium, '(none)'))";

        return $this->visitBase($siteId, $range)
            ->whereNotNull('utm_source')->where('utm_source', '!=', '')
            ->select(
                DB::raw("{$value} as value"),
                DB::raw('COUNT(*) as sessions'),
                DB::raw('COUNT(DISTINCT visitor_hash) as visitors'),
            )
            ->groupBy('utm_source', 'utm_medium')->orderByDesc('sessions')->limit($limit)->get();
    }

    /** @return array{total: int, from_campaigns: int, percent: float} */
    public function campaignShare(string $siteId, AnalyticsQuery|int $range): array
    {
        $total = $this->visitBase($siteId, $range)->count();

        $fromCampaigns = $this->visitBase($siteId, $range)
            ->whereRaw($this->campaignCondition())
            ->count();

        return [
            'total' => $total,
            'from_campaigns' => $fromCampaigns,
            'percent' => $total > 0 ? round($fromCampaigns / $total * 100, 1) : 0.0,
        ];
    }

    /**
     * New vs. returning visitors, by the type of each visit in the range. A
     * visitor with both a first and a later visit in the range counts in both.
     *
     * @return list<array{type: string, visitors: int, sessions: int, engagement_rate: ?float}>
     */
    public function visitorTypes(string $siteId, AnalyticsQuery|int $range): array
    {
        $rows = $this->visitBase($siteId, $range)
            ->select(
                'is_returning',
                DB::raw('COUNT(*) as sessions'),
                DB::raw('COUNT(DISTINCT visitor_hash) as visitors'),
            )
            ->selectRaw(...$this->engagedSumExpression($siteId))
            ->groupBy('is_returning')->get()
            ->keyBy(fn ($row) => $row->is_returning ? 'returning' : 'new');

        return array_map(function (string $type) use ($rows) {
            $row = $rows->get($type);
            $sessions = (int) ($row->sessions ?? 0);

            return [
                'type' => $type,
                'visitors' => (int) ($row->visitors ?? 0),
                'sessions' => $sessions,
                'engagement_rate' => $this->rate((int) ($row->engaged ?? 0), $sessions),
            ];
        }, AnalyticsQuery::VISITOR_TYPES);
    }

    public function totalSessions(string $siteId, AnalyticsQuery|int $range): int
    {
        return $this->visitBase($siteId, $range)->count();
    }
}
