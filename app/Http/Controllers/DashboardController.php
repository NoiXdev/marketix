<?php

namespace App\Http\Controllers;

use App\Models\Statistic;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function show(Request $request)
    {
        $project = $request->get('project');

        $days = (int) $request->input('days', 30);
        $days = in_array($days, [7, 30, 90, 180, 365], true) ? $days : 30;

        return inertia('Dashboard', [
            'urlsCount' => $project->urls()->count(),
            'domainsCount' => $project->domains()->count(),
            'totalClicks' => $project->urls()->sum('clicks'),
            'totalUniqueClicks' => $project->urls()->sum('unique_clicks'),
            'days' => $days,
            'clicksByDay' => $this->clicksByDay($project->id, $days),
        ]);
    }

    /**
     * Daily total and unique (distinct-visitor_hash) clicks over the trailing window,
     * with zero-clicks days filled in so the chart has a continuous x-axis.
     *
     * @return list<array{date: string, clicks: int, unique: int}>
     */
    private function clicksByDay(string $projectId, int $days): array
    {
        $since = now()->subDays($days - 1)->startOfDay();

        $rows = Statistic::where('project_id', $projectId)
            ->where('created_at', '>=', $since)
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as clicks'),
                DB::raw('COUNT(DISTINCT visitor_hash) as unique_clicks'),
            )
            ->groupBy('date')
            ->get()
            ->keyBy('date');

        $clicksByDay = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $row = $rows->get($date);
            $clicksByDay[] = [
                'date' => $date,
                'clicks' => (int) ($row->clicks ?? 0),
                'unique' => (int) ($row->unique_clicks ?? 0),
            ];
        }

        return $clicksByDay;
    }
}
