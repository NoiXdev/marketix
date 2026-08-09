<?php

namespace App\Http\Controllers;

use App\Services\EventPropertyAggregator;
use Illuminate\Http\Request;

class EventAnalyticsController extends Controller
{
    public function show(Request $request, string $site, EventPropertyAggregator $agg)
    {
        $project = $request->get('project');
        $model = $project->sites()->findOrFail($site);

        $name = (string) $request->query('name', '');
        $days = (int) $request->input('days', 30);
        $days = in_array($days, [1, 7, 30, 90], true) ? $days : 30;

        $result = $name !== ''
            ? $agg->forEvent($model->id, $name, $days)
            : ['total' => 0, 'keys' => []];

        return inertia('Analytics/EventDetail', [
            'site' => ['id' => $model->id, 'name' => $model->name, 'domain' => $model->domain],
            'event' => $name,
            'days' => $days,
            'total' => $result['total'],
            'keys' => $result['keys'],
        ]);
    }
}
