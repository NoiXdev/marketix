<?php

namespace App\Services;

use App\Enums\GoalType;
use App\Models\Event;
use App\Models\Goal;
use App\Models\PageView;
use App\Support\Analytics\AnalyticsQuery;
use App\Support\Analytics\PathMatcher;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class GoalAggregator
{
    public function __construct(private AnalyticsAggregator $analytics = new AnalyticsAggregator) {}

    /** @return Collection<int, \stdClass> */
    public function topEvents(string $siteId, AnalyticsQuery|int $range, int $limit = 8): Collection
    {
        $query = AnalyticsQuery::from($range);

        return $this->scoped(Event::query(), $siteId, $query)
            ->where('site_id', $siteId)
            ->where('is_bot', false)
            ->whereBetween('created_at', [$query->from, $query->to])
            ->select('name', DB::raw('COUNT(*) as count'), DB::raw('COUNT(DISTINCT visitor_hash) as visitors'))
            ->groupBy('name')->orderByDesc('count')->limit($limit)->get();
    }

    /** @return Collection<int, \stdClass> */
    public function eventBreakdown(string $siteId, AnalyticsQuery|int $range, string $event, ?string $property = null, int $limit = 10): Collection
    {
        if ($property !== null && preg_match('/^[A-Za-z0-9_]+$/', $property) !== 1) {
            throw new \InvalidArgumentException("Invalid event property: {$property}");
        }

        $query = AnalyticsQuery::from($range);
        $value = match (true) {
            $property === null => 'path',
            DB::connection()->getDriverName() === 'sqlite' => "json_extract(props, '$.{$property}')",
            default => "JSON_UNQUOTE(JSON_EXTRACT(props, '$.{$property}'))",
        };

        return $this->scoped(Event::query(), $siteId, $query)
            ->where('site_id', $siteId)
            ->where('is_bot', false)
            ->whereBetween('created_at', [$query->from, $query->to])
            ->where('name', $event)
            ->whereRaw("{$value} IS NOT NULL")
            ->select(DB::raw("{$value} as value"), DB::raw('COUNT(*) as count'), DB::raw('COUNT(DISTINCT visitor_hash) as visitors'))
            ->groupBy('value')->orderByDesc('count')->limit($limit)->get();
    }

    /**
     * Rows (events or page_views) that satisfy a goal or funnel step, in range, bot-excluded.
     */
    public function matches(string $siteId, GoalType $type, string $value, AnalyticsQuery $query): Builder
    {
        $builder = $type === GoalType::Event
            ? Event::query()->where('name', $value)
            : PathMatcher::apply(PageView::query(), $value);

        return $this->scoped($builder, $siteId, $query)
            ->where('site_id', $siteId)
            ->where('is_bot', false)
            ->whereBetween('created_at', [$query->from, $query->to]);
    }

    private function matchBase(Goal $goal, AnalyticsQuery $query): Builder
    {
        return $this->matches($goal->site_id, $goal->type, $goal->match_value, $query);
    }

    private function scoped(Builder $builder, string $siteId, AnalyticsQuery $query): Builder
    {
        $visits = $this->analytics->visitScope($siteId, $query);

        return $visits === null ? $builder : $builder->whereIn('visit_id', $visits);
    }

    /** @return array{conversions: int, visitors: int, rate: float} */
    public function conversions(Goal $goal, AnalyticsQuery|int $range): array
    {
        $query = AnalyticsQuery::from($range);
        $base = $this->matchBase($goal, $query);
        $conversions = (clone $base)->distinct('visit_id')->count('visit_id');
        $visitors = (clone $base)->distinct('visitor_hash')->count('visitor_hash');
        $total = $this->analytics->totalSessions($goal->site_id, $query);

        return [
            'conversions' => $conversions,
            'visitors' => $visitors,
            'rate' => $total > 0 ? round($conversions / $total * 100, 1) : 0.0,
        ];
    }

    /** @return Collection<int, \stdClass> */
    public function conversionsByCampaign(Goal $goal, AnalyticsQuery|int $range, int $limit = 5): Collection
    {
        $visitIds = $this->matchBase($goal, AnalyticsQuery::from($range))->select('visit_id')->distinct();

        return DB::table('visits')
            ->whereIn('id', $visitIds)
            ->whereNotNull('utm_source')->where('utm_source', '!=', '')
            ->select('utm_source as value', DB::raw('COUNT(*) as conversions'))
            ->groupBy('utm_source')->orderByDesc('conversions')->limit($limit)->get();
    }
}
