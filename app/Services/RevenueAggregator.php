<?php

namespace App\Services;

use App\Enums\GoalType;
use App\Support\Analytics\AnalyticsQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RevenueAggregator
{
    public const EVENT = 'purchase';

    private const DIMENSIONS = ['channel', 'utm_campaign', 'entry_path', 'country_code'];

    public function __construct(
        private AnalyticsAggregator $analytics = new AnalyticsAggregator,
        private GoalAggregator $goals = new GoalAggregator,
    ) {}

    private function isSqlite(): bool
    {
        return DB::connection()->getDriverName() === 'sqlite';
    }

    private function valueExpression(): string
    {
        return $this->isSqlite()
            ? "COALESCE(CAST(json_extract(props, '$.value') AS REAL), 0)"
            : "COALESCE(CAST(JSON_UNQUOTE(JSON_EXTRACT(props, '$.value')) AS DECIMAL(14,2)), 0)";
    }

    private function currencyExpression(): string
    {
        return $this->isSqlite()
            ? "UPPER(COALESCE(json_extract(props, '$.currency'), ''))"
            : "UPPER(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(props, '$.currency')), ''))";
    }

    private function purchases(string $siteId, AnalyticsQuery $query, string $currency): Builder
    {
        return $this->goals
            ->matches($siteId, GoalType::Event, self::EVENT, $query)
            ->whereRaw($this->currencyExpression().' = ?', [$currency]);
    }

    /** @return list<array{currency: string, revenue: float, orders: int}> */
    public function currencies(string $siteId, AnalyticsQuery|int $range): array
    {
        $query = AnalyticsQuery::from($range);

        return $this->goals
            ->matches($siteId, GoalType::Event, self::EVENT, $query)
            ->select(
                DB::raw($this->currencyExpression().' as currency'),
                DB::raw('SUM('.$this->valueExpression().') as revenue'),
                DB::raw('COUNT(*) as orders'),
            )
            ->groupBy('currency')
            ->orderByDesc('revenue')
            ->get()
            ->map(fn ($row) => ['currency' => (string) $row->currency, 'revenue' => round((float) $row->revenue, 2), 'orders' => (int) $row->orders])
            ->values()
            ->all();
    }

    /**
     * @return array{revenue: float, orders: int, average_order_value: float, purchase_rate: float, revenue_per_visitor: float}
     */
    public function summary(string $siteId, AnalyticsQuery|int $range, string $currency): array
    {
        $query = AnalyticsQuery::from($range);

        $row = $this->purchases($siteId, $query, $currency)
            ->select(
                DB::raw('SUM('.$this->valueExpression().') as revenue'),
                DB::raw('COUNT(*) as orders'),
                DB::raw('COUNT(DISTINCT visit_id) as sessions'),
            )
            ->first();

        $totals = $this->analytics->summary($siteId, $query);
        $revenue = round((float) ($row->revenue ?? 0), 2);
        $orders = (int) ($row->orders ?? 0);

        return [
            'revenue' => $revenue,
            'orders' => $orders,
            'average_order_value' => $orders > 0 ? round($revenue / $orders, 2) : 0.0,
            'purchase_rate' => $totals['sessions'] > 0 ? round((int) $row->sessions / $totals['sessions'] * 100, 2) : 0.0,
            'revenue_per_visitor' => $totals['visitors'] > 0 ? round($revenue / $totals['visitors'], 2) : 0.0,
        ];
    }

    /** @return list<array{date: string, revenue: float, orders: int}> */
    public function timeseries(string $siteId, AnalyticsQuery|int $range, string $currency): array
    {
        $query = AnalyticsQuery::from($range);

        $rows = $this->purchases($siteId, $query, $currency)
            ->select(
                DB::raw($this->analytics->bucketExpression('created_at', $query).' as bucket'),
                DB::raw('SUM('.$this->valueExpression().') as revenue'),
                DB::raw('COUNT(*) as orders'),
            )
            ->groupBy('bucket')
            ->get()
            ->keyBy('bucket');

        return array_map(fn (string $key) => [
            'date' => $key,
            'revenue' => round((float) ($rows->get($key)->revenue ?? 0), 2),
            'orders' => (int) ($rows->get($key)->orders ?? 0),
        ], $this->analytics->bucketKeys($query));
    }

    /** @return Collection<int, array{value: string, revenue: float, orders: int}> */
    public function breakdown(string $siteId, AnalyticsQuery|int $range, string $currency, string $dimension, int $limit = 10): Collection
    {
        if (! in_array($dimension, self::DIMENSIONS, true)) {
            throw new \InvalidArgumentException("Unknown revenue dimension: {$dimension}");
        }

        $query = AnalyticsQuery::from($range);

        $perVisit = $this->purchases($siteId, $query, $currency)
            ->select('visit_id', DB::raw('SUM('.$this->valueExpression().') as revenue'), DB::raw('COUNT(*) as orders'))
            ->groupBy('visit_id')
            ->toBase();

        $builder = DB::table('visits')->joinSub($perVisit, 'r', 'r.visit_id', '=', 'visits.id');

        if ($dimension === 'channel') {
            [$sql, $bindings] = $this->analytics->channelExpression($siteId);
            $builder->selectRaw("{$sql} as value", $bindings);
        } else {
            $builder->whereNotNull("visits.{$dimension}")->where("visits.{$dimension}", '!=', '')->selectRaw("visits.{$dimension} as value");
        }

        return $builder
            ->selectRaw('SUM(r.revenue) as revenue')
            ->selectRaw('SUM(r.orders) as orders')
            ->groupBy('value')
            ->orderByDesc('revenue')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => ['value' => (string) $row->value, 'revenue' => round((float) $row->revenue, 2), 'orders' => (int) $row->orders]);
    }
}
