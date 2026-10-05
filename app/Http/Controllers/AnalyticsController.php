<?php

namespace App\Http\Controllers;

use App\Models\Goal;
use App\Services\AnalyticsAggregator;
use App\Services\GoalAggregator;
use App\Support\Analytics\AnalyticsQuery;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    public function show(Request $request, string $site, AnalyticsAggregator $agg, GoalAggregator $goalAgg)
    {
        $project = $request->get('project');
        $model = $project->sites()->findOrFail($site);

        $days = (int) $request->input('days', 30);
        $days = in_array($days, [1, 7, 30, 90], true) ? $days : 30;

        $filters = $request->input('filters');
        $query = AnalyticsQuery::lastDays($days, is_array($filters) ? $filters : []);
        $id = $model->id;

        return inertia('Analytics/Index', [
            'site' => [
                'id' => $model->id,
                'name' => $model->name,
                'domain' => $model->domain,
            ],
            'days' => $days,
            'filters' => (object) $query->filters,
            'summary' => fn () => $agg->summary($id, $query),
            'previousSummary' => fn () => $agg->summary($id, $query->previous()),
            'liveVisitors' => fn () => $agg->liveVisitors($id),
            'timeseries' => fn () => $agg->timeseries($id, $query),
            'topPaths' => fn () => $agg->topPaths($id, $query),
            'entryPages' => fn () => $agg->entryPages($id, $query),
            'exitPages' => fn () => $agg->exitPages($id, $query),
            'topReferrers' => fn () => $agg->topReferrers($id, $query),
            'channels' => fn () => $agg->channels($id, $query),
            'countries' => fn () => $agg->countriesWithCode($id, $query),
            'languages' => fn () => $agg->breakdown($id, 'language', $query),
            'clicksByCountry' => fn () => $agg->breakdownByCountryCode($id, $query),
            'browsers' => fn () => $agg->breakdown($id, 'browser', $query),
            'operatingSystems' => fn () => $agg->breakdown($id, 'os', $query),
            'devices' => fn () => $agg->breakdown($id, 'device', $query),
            'hourlyActivity' => fn () => $agg->hourlyActivity($id, $query),
            'utmSources' => fn () => $agg->utmBreakdown($id, 'utm_source', $query),
            'utmMediums' => fn () => $agg->utmBreakdown($id, 'utm_medium', $query),
            'utmCampaigns' => fn () => $agg->utmBreakdown($id, 'utm_campaign', $query),
            'utmSourceMediums' => fn () => $agg->utmSourceMedium($id, $query),
            'utmTerms' => fn () => $agg->utmBreakdown($id, 'utm_term', $query),
            'utmContents' => fn () => $agg->utmBreakdown($id, 'utm_content', $query),
            'topEvents' => fn () => $goalAgg->topEvents($id, $query),
            'goals' => fn () => $model->goals()->get()->map(fn (Goal $g) => array_merge([
                'id' => $g->id,
                'name' => $g->name,
                'type' => $g->type->value,
                'match_value' => $g->match_value,
            ], $goalAgg->conversions($g, $query), [
                'byCampaign' => $goalAgg->conversionsByCampaign($g, $query),
            ])),
        ]);
    }
}
