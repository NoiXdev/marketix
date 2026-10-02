<?php

namespace App\Http\Controllers;

use App\Actions\ResolveDefaultDashboard;
use App\Models\Dashboard;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request, ResolveDefaultDashboard $resolveDefault)
    {
        $project = $request->get('project');
        $user = $request->user();

        $default = $resolveDefault($user, $project);

        $dashboards = $user->dashboards()->where('project_id', $project->id)->orderBy('position')->get();

        $active = null;
        if ($id = $request->query('dashboard')) {
            $active = $dashboards->firstWhere('id', $id);
        }
        $active ??= $dashboards->firstWhere('id', $default->id) ?? $default;

        return inertia('Dashboard', [
            'dashboards' => $dashboards->map(fn (Dashboard $d) => ['id' => $d->id, 'name' => $d->name, 'is_default' => $d->is_default, 'position' => $d->position])->values(),
            'active' => ['id' => $active->id, 'name' => $active->name, 'widgets' => $active->widgets],
        ]);
    }
}
