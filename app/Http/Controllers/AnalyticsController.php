<?php

namespace App\Http\Controllers;

use App\Models\Funnel;
use App\Models\Goal;
use App\Models\Site;
use App\Services\AnalyticsAggregator;
use App\Services\FunnelAggregator;
use App\Services\GoalAggregator;
use App\Services\RealtimeAggregator;
use App\Services\RevenueAggregator;
use App\Support\Analytics\PeriodResolver;
use App\Support\Analytics\ResolvedPeriod;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    public const TABS = ['overview', 'realtime', 'acquisition', 'behavior', 'audience', 'conversions', 'revenue'];

    public function __construct(
        private AnalyticsAggregator $agg,
        private GoalAggregator $goals,
        private FunnelAggregator $funnels,
        private RevenueAggregator $revenueReports,
        private RealtimeAggregator $realtimeReports,
    ) {}

    public function show(Request $request, string $site)
    {
        $project = $request->get('project');
        $model = $project->sites()->findOrFail($site);

        $hasData = $model->hasTrackedVisits();
        $tab = $request->input('tab');
        $tab = $hasData && in_array($tab, self::TABS, true) ? $tab : 'overview';

        $filters = $request->input('filters');
        $period = PeriodResolver::resolve(
            $request->only(['range', 'from', 'to', 'days', 'interval', 'compare']),
            is_array($filters) ? $filters : [],
        );

        return inertia('Analytics/Index', [
            'site' => [
                'id' => $model->id,
                'name' => $model->name,
                'domain' => $model->domain,
                'search_enabled' => $model->searchParams() !== [],
                'tracking_mode' => $model->tracking_mode->value,
                'has_data' => $hasData,
                'snippet' => $model->trackingSnippet(),
            ],
            'tab' => $tab,
            'period' => $period->toArray(),
            'filters' => (object) $period->query->filters,
            'liveVisitors' => fn () => $this->agg->liveVisitors($model->id),
            ...$this->tabProps($tab, $model, $period),
        ]);
    }

    /** @return array<string, \Closure> */
    private function tabProps(string $tab, Site $site, ResolvedPeriod $period): array
    {
        return match ($tab) {
            'acquisition' => $this->acquisition($site->id, $period),
            'behavior' => $this->behavior($site->id, $period),
            'audience' => $this->audience($site->id, $period),
            'conversions' => $this->conversions($site, $period),
            'revenue' => $this->revenue($site->id, $period),
            'realtime' => ['realtime' => fn () => $this->realtimeReports->snapshot($site->id)],
            default => $this->overview($site->id, $period),
        };
    }

    /** @return array<string, \Closure> */
    private function overview(string $id, ResolvedPeriod $period): array
    {
        $query = $period->query;
        $comparison = $period->comparison();

        return [
            'summary' => fn () => $this->agg->summary($id, $query),
            'previousSummary' => fn () => $comparison === null ? null : $this->agg->summary($id, $comparison),
            'timeseries' => fn () => $this->agg->timeseries($id, $query),
            'previousTimeseries' => fn () => $comparison === null ? null : $this->agg->timeseries($id, $comparison),
            'topPaths' => fn () => $this->agg->topPaths($id, $query, 6),
            'channels' => fn () => $this->agg->channels($id, $query)->take(6)->values(),
            'countries' => fn () => $this->agg->countriesWithCode($id, $query, 6),
            'devices' => fn () => $this->agg->breakdown($id, 'device', $query, 6),
        ];
    }

    /** @return array<string, \Closure> */
    private function acquisition(string $id, ResolvedPeriod $period): array
    {
        $query = $period->query;

        return [
            'channels' => fn () => $this->agg->channels($id, $query),
            'topReferrers' => fn () => $this->agg->topReferrers($id, $query),
            'utmSources' => fn () => $this->agg->utmBreakdown($id, 'utm_source', $query),
            'utmMediums' => fn () => $this->agg->utmBreakdown($id, 'utm_medium', $query),
            'utmCampaigns' => fn () => $this->agg->utmBreakdown($id, 'utm_campaign', $query),
            'utmSourceMediums' => fn () => $this->agg->utmSourceMedium($id, $query),
            'utmTerms' => fn () => $this->agg->utmBreakdown($id, 'utm_term', $query),
            'utmContents' => fn () => $this->agg->utmBreakdown($id, 'utm_content', $query),
        ];
    }

    /** @return array<string, \Closure> */
    private function behavior(string $id, ResolvedPeriod $period): array
    {
        $query = $period->query;

        return [
            'topPaths' => fn () => $this->agg->topPaths($id, $query),
            'entryPages' => fn () => $this->agg->entryPages($id, $query),
            'exitPages' => fn () => $this->agg->exitPages($id, $query),
            'hostnames' => fn () => $this->agg->hostnames($id, $query),
            'topEvents' => fn () => $this->goals->topEvents($id, $query),
            'interactions' => fn () => [
                'outbound' => $this->goals->eventBreakdown($id, $query, 'outbound_click', 'url'),
                'downloads' => $this->goals->eventBreakdown($id, $query, 'file_download', 'url'),
                'searches' => $this->goals->eventBreakdown($id, $query, 'site_search', 'term'),
                'notFound' => $this->goals->eventBreakdown($id, $query, 'not_found'),
            ],
            'hourlyActivity' => fn () => $this->agg->hourlyActivity($id, $query),
        ];
    }

    /** @return array<string, \Closure> */
    private function audience(string $id, ResolvedPeriod $period): array
    {
        $query = $period->query;

        return [
            'clicksByCountry' => fn () => $this->agg->breakdownByCountryCode($id, $query),
            'countries' => fn () => $this->agg->countriesWithCode($id, $query),
            'regions' => fn () => $this->agg->locations($id, 'region', $query),
            'cities' => fn () => $this->agg->locations($id, 'city', $query),
            'languages' => fn () => $this->agg->breakdown($id, 'language', $query),
            'visitorTypes' => fn () => $this->agg->visitorTypes($id, $query),
            'devices' => fn () => $this->agg->breakdown($id, 'device', $query),
            'browsers' => fn () => $this->agg->breakdown($id, 'browser', $query),
            'operatingSystems' => fn () => $this->agg->breakdown($id, 'os', $query),
        ];
    }

    /** @return array<string, \Closure> */
    private function revenue(string $id, ResolvedPeriod $period): array
    {
        $query = $period->query;

        return [
            'revenue' => function () use ($id, $query, $period) {
                $currencies = $this->revenueReports->currencies($id, $query);
                $currency = $currencies[0]['currency'] ?? '';
                $comparison = $period->comparison();

                return [
                    'currency' => $currency,
                    'currencies' => $currencies,
                    'summary' => $this->revenueReports->summary($id, $query, $currency),
                    'previousSummary' => $comparison === null ? null : $this->revenueReports->summary($id, $comparison, $currency),
                    'timeseries' => $this->revenueReports->timeseries($id, $query, $currency),
                    'channels' => $this->revenueReports->breakdown($id, $query, $currency, 'channel'),
                    'campaigns' => $this->revenueReports->breakdown($id, $query, $currency, 'utm_campaign'),
                    'landingPages' => $this->revenueReports->breakdown($id, $query, $currency, 'entry_path'),
                    'countries' => $this->revenueReports->breakdown($id, $query, $currency, 'country_code'),
                ];
            },
        ];
    }

    /** @return array<string, \Closure> */
    private function conversions(Site $site, ResolvedPeriod $period): array
    {
        $query = $period->query;

        return [
            'goals' => fn () => $site->goals()->get()->map(fn (Goal $g) => array_merge([
                'id' => $g->id,
                'name' => $g->name,
                'type' => $g->type->value,
                'match_value' => $g->match_value,
            ], $this->goals->conversions($g, $query), [
                'byCampaign' => $this->goals->conversionsByCampaign($g, $query),
            ])),
            'funnels' => fn () => $site->funnels()->oldest()->get()->map(fn (Funnel $funnel) => [
                'id' => $funnel->id,
                'name' => $funnel->name,
                ...$this->funnels->report($funnel, $query),
            ]),
        ];
    }
}
