<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Project;
use Illuminate\Http\Request;

class ActivityController extends Controller
{
    public function index(Request $request)
    {
        /** @var Project $project */
        $project = $request->get('project');

        $logName = $request->string('log_name')->toString() ?: null;

        $activities = Activity::query()
            ->forProject($project)
            ->when($logName, fn ($q) => $q->where('log_name', $logName))
            ->with('causer')
            ->latest('id')
            ->paginate(30)
            ->withQueryString()
            ->through(fn (Activity $a) => $a->toFeedArray());

        return inertia('Activity/Index', [
            'activities' => $activities,
            'logName' => $logName,
            'logNames' => ['url', 'domain', 'qrcode', 'pixel', 'project', 'membership', 'invitation'],
        ]);
    }
}
