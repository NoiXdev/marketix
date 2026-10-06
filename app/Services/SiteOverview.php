<?php

namespace App\Services;

use App\Models\Site;
use App\Models\Visit;
use App\Support\Analytics\AnalyticsQuery;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class SiteOverview
{
    public const DAYS = 30;

    public function __construct(private AnalyticsAggregator $analytics) {}

    /**
     * @param  Collection<int, Site>  $sites
     * @return array<string, ?array{visitors: int, previous_visitors: int, page_views: int, engagement_rate: float, trend: list<int>, live: int, last_seen_at: string}>
     */
    public function forSites(Collection $sites): array
    {
        $lastSeen = Visit::query()
            ->whereIn('site_id', $sites->pluck('id'))
            ->where('is_bot', false)
            ->groupBy('site_id')
            ->selectRaw('site_id, MAX(last_activity_at) as last_seen_at')
            ->pluck('last_seen_at', 'site_id');

        $query = AnalyticsQuery::lastDays(self::DAYS);

        return $sites->mapWithKeys(function (Site $site) use ($lastSeen, $query) {
            $seen = $lastSeen->get($site->id);
            if ($seen === null) {
                return [$site->id => null];
            }

            $summary = $this->analytics->summary($site->id, $query);

            return [$site->id => [
                'visitors' => $summary['visitors'],
                'previous_visitors' => $this->analytics->summary($site->id, $query->previous())['visitors'],
                'page_views' => $summary['page_views'],
                'engagement_rate' => $summary['engagement_rate'],
                'trend' => array_column($this->analytics->timeseries($site->id, $query), 'visitors'),
                'live' => $this->analytics->liveVisitors($site->id),
                'last_seen_at' => CarbonImmutable::parse($seen)->toIso8601String(),
            ]];
        })->all();
    }
}
