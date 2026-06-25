<?php

namespace App\Http\Controllers;

use App\Models\Statistic;
use App\Services\StatisticsAggregator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StatisticsController extends Controller
{
    public function show(Request $request, StatisticsAggregator $stats)
    {
        $project = $request->get('project');
        $days = (int) $request->input('days', 30);
        $days = in_array($days, [7, 30, 90], true) ? $days : 30;

        // Top links is a project-wide join (urls + domains), not part of the
        // per-link aggregator, so it stays here.
        $topLinks = Statistic::where('statistics.project_id', $project->id)
            ->join('urls', 'statistics.url_id', '=', 'urls.id')
            ->join('domains', 'urls.domain_id', '=', 'domains.id')
            ->select(
                'urls.id',
                'urls.slug',
                'domains.name as domain_name',
                DB::raw('COUNT(*) as clicks'),
            )
            ->groupBy('urls.id', 'urls.slug', 'domains.name')
            ->orderByDesc('clicks')
            ->limit(10)
            ->get();

        return inertia('Statistics/Index', [
            'days' => $days,
            'totalClicks' => $stats->totalClicks($project->id, null),
            'uniqueClicks' => $stats->uniqueClicks($project->id, null),
            'clicksByDay' => $stats->clicksByDay($project->id, null, $days),
            'topLinks' => $topLinks,
            'topCountries' => $stats->breakdown($project->id, null, 'country'),
            'clicksByCountry' => $stats->breakdownByCountryCode($project->id, null),
            'topBrowsers' => $stats->breakdown($project->id, null, 'browser'),
            'topOs' => $stats->breakdown($project->id, null, 'os'),
            'topReferrers' => $stats->breakdown($project->id, null, 'domain'),
        ]);
    }
}
