<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\Request;

class ActivityController extends Controller
{
    public function index(Request $request)
    {
        $logName = $request->string('log_name')->toString() ?: null;
        $projectId = $request->string('project_id')->toString() ?: null;
        $causer = $request->string('causer')->toString() ?: null;
        $from = $request->date('from');
        $to = $request->date('to');

        $activities = Activity::query()
            ->with(['causer', 'project'])
            ->when($logName, fn ($q) => $q->where('log_name', $logName))
            ->when($projectId, fn ($q) => $q->where('project_id', $projectId))
            ->when($causer, fn ($q) => $q->whereHasMorph('causer', [User::class], fn ($q) => $q->where('name', 'like', "%{$causer}%")->orWhere('email', 'like', "%{$causer}%")))
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from->startOfDay()))
            ->when($to, fn ($q) => $q->where('created_at', '<=', $to->endOfDay()))
            ->latest('id')
            ->paginate(40)
            ->withQueryString()
            ->through(fn (Activity $a) => $a->toFeedArray() + [
                'project' => $a->project ? ['id' => $a->project->id, 'name' => $a->project->name] : null,
            ]);

        return inertia('Admin/Activity/Index', [
            'activities' => $activities,
            'filters' => [
                'log_name' => $logName,
                'project_id' => $projectId,
                'causer' => $causer,
                'from' => $request->string('from')->toString() ?: null,
                'to' => $request->string('to')->toString() ?: null,
            ],
            'logNames' => ['url', 'domain', 'qrcode', 'pixel', 'project', 'membership', 'invitation', 'security'],
            'projects' => Project::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
