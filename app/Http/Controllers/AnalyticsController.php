<?php

namespace App\Http\Controllers;

use App\Models\Goal;
use App\Services\AnalyticsAggregator;
use App\Services\GoalAggregator;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    public function show(Request $request, string $site, AnalyticsAggregator $agg, GoalAggregator $goalAgg)
    {
        $project = $request->get('project');
        $model = $project->sites()->findOrFail($site);

        $days = (int) $request->input('days', 30);
        $days = in_array($days, [1, 7, 30, 90], true) ? $days : 30;

        return inertia('Analytics/Index', [
            'site' => [
                'id' => $model->id,
                'name' => $model->name,
                'domain' => $model->domain,
            ],
            'days' => $days,
            'totalPageViews' => $agg->totalPageViews($model->id, $days),
            'uniqueVisitors' => $agg->uniqueVisitors($model->id, $days),
            'bounceRate' => $agg->bounceRate($model->id, $days),
            'avgDurationSeconds' => $agg->avgDurationSeconds($model->id, $days),
            'pageViewsByDay' => $agg->pageViewsByDay($model->id, $days),
            'topPaths' => $agg->topPaths($model->id, $days),
            'topReferrers' => $agg->topReferrers($model->id, $days),
            'countries' => $agg->breakdown($model->id, 'country', $days),
            'browsers' => $agg->breakdown($model->id, 'browser', $days),
            'operatingSystems' => $agg->breakdown($model->id, 'os', $days),
            'devices' => $agg->breakdown($model->id, 'device', $days),
            'campaignShare' => $agg->campaignShare($model->id, $days),
            'utmSources' => $agg->utmBreakdown($model->id, 'utm_source', $days),
            'utmMediums' => $agg->utmBreakdown($model->id, 'utm_medium', $days),
            'utmCampaigns' => $agg->utmBreakdown($model->id, 'utm_campaign', $days),
            'utmSourceMediums' => $agg->utmSourceMedium($model->id, $days),
            'utmTerms' => $agg->utmBreakdown($model->id, 'utm_term', $days),
            'utmContents' => $agg->utmBreakdown($model->id, 'utm_content', $days),
            'topEvents' => $goalAgg->topEvents($model->id, $days),
            'goals' => $model->goals()->get()->map(fn (Goal $g) => array_merge([
                'id' => $g->id,
                'name' => $g->name,
                'type' => $g->type->value,
                'match_value' => $g->match_value,
            ], $goalAgg->conversions($g, $days), [
                'byCampaign' => $goalAgg->conversionsByCampaign($g, $days),
            ])),
        ]);
    }
}
