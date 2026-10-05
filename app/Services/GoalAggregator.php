<?php

namespace App\Services;

use App\Enums\GoalType;
use App\Models\Event;
use App\Models\Goal;
use App\Models\PageView;
use App\Support\Analytics\AnalyticsQuery;
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

    /**
     * Base query of the rows (events or page_views) that satisfy a goal, in range, bot-excluded.
     */
    private function matchBase(Goal $goal, AnalyticsQuery $query): Builder
    {
        if ($goal->type === GoalType::Event) {
            return $this->scoped(Event::query(), $goal->site_id, $query)
                ->where('site_id', $goal->site_id)
                ->where('is_bot', false)
                ->whereBetween('created_at', [$query->from, $query->to])
                ->where('name', $goal->match_value);
        }

        // pageview goal: exact path, or prefix when match_value ends with '/*'
        $q = $this->scoped(PageView::query(), $goal->site_id, $query)
            ->where('site_id', $goal->site_id)
            ->where('is_bot', false)
            ->whereBetween('created_at', [$query->from, $query->to]);

        if (str_ends_with($goal->match_value, '/*')) {
            $prefix = substr($goal->match_value, 0, -1); // keep trailing slash, drop '*'
            $escaped = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $prefix);
            $q->whereRaw("path LIKE ? ESCAPE '!'", [$escaped.'%']);
        } else {
            $q->where('path', $goal->match_value);
        }

        return $q;
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
