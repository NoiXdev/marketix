<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Services\StatisticsAggregator;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function show(Request $request, StatisticsAggregator $stats)
    {
        $project = $request->get('project');

        $days = (int) $request->input('days', 30);
        $days = in_array($days, [7, 30, 90, 365], true) ? $days : 30;

        $now = now();
        $since = $now->copy()->subDays($days - 1)->startOfDay();
        $until = $now;
        $prevSince = $since->copy()->subDays($days);
        $prevUntil = $since->copy()->subSecond();

        $curClicks = $stats->totalClicks($project->id, null, $since, $until);
        $prevClicks = $stats->totalClicks($project->id, null, $prevSince, $prevUntil);
        $curUnique = $stats->uniqueClicks($project->id, null, $since, $until);
        $prevUnique = $stats->uniqueClicks($project->id, null, $prevSince, $prevUntil);

        $linksNow = $project->urls()->count();
        $linksBefore = $project->urls()->where('created_at', '<', $since)->count();
        $linksPrevEnd = $project->urls()->where('created_at', '<=', $prevUntil)->count();
        $avgCur = $linksNow > 0 ? (int) round($curClicks / $linksNow) : 0;
        $avgPrev = $linksPrevEnd > 0 ? (int) round($prevClicks / $linksPrevEnd) : 0;

        return inertia('Dashboard', [
            'days' => $days,
            'kpis' => [
                'clicks' => ['value' => $curClicks, 'deltaPct' => $this->pct($curClicks, $prevClicks)],
                'uniqueVisitors' => ['value' => $curUnique, 'deltaPct' => $this->pct($curUnique, $prevUnique)],
                'activeLinks' => [
                    'value' => $linksNow,
                    'deltaPct' => $this->pct($linksNow, $linksBefore),
                    'newInPeriod' => $project->urls()->where('created_at', '>=', $since)->count(),
                    'domains' => $project->domains()->count(),
                    'qrCodes' => $project->qrCodes()->count(),
                ],
                'avgPerLink' => ['value' => $avgCur, 'deltaPct' => $this->pct($avgCur, $avgPrev)],
            ],
            'clicksByDay' => $stats->clicksByDay($project->id, null, $days),
            'topLinks' => $stats->topLinks($project->id, $since, $until, 5),
            'topCountries' => $stats->breakdownByCountryCode($project->id, null, $since, $until, 5),
            'recentActivity' => Activity::query()
                ->forProject($project)
                ->with('causer')
                ->latest('id')
                ->limit(6)
                ->get()
                ->map(fn (Activity $a) => $a->toFeedArray()),
        ]);
    }

    private function pct(int $cur, int $prev): ?float
    {
        return $prev > 0 ? round(($cur - $prev) / $prev * 100, 1) : null;
    }
}
