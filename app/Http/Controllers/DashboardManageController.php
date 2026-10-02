<?php

namespace App\Http\Controllers;

use App\Http\Requests\DashboardSaveRequest;
use App\Support\Widgets\WidgetSanitizer;
use Illuminate\Http\Request;

class DashboardManageController extends Controller
{
    public function store(DashboardSaveRequest $request)
    {
        $project = $request->get('project');
        $user = $request->user();
        $position = (int) $user->dashboards()->where('project_id', $project->id)->max('position') + 1;

        $dashboard = $user->dashboards()->create([
            'project_id' => $project->id,
            'name' => $request->validated('name'),
            'is_default' => false,
            'position' => $position,
            'widgets' => [],
        ]);

        return redirect()->route('app.project.dashboard', ['project' => $project->id, 'dashboard' => $dashboard->id]);
    }

    public function update(DashboardSaveRequest $request, WidgetSanitizer $sanitizer, string $dashboard)
    {
        $project = $request->get('project');
        $model = $request->user()->dashboards()->where('project_id', $project->id)->findOrFail($dashboard);

        $attrs = ['name' => $request->validated('name')];
        if ($request->has('widgets')) {
            $attrs['widgets'] = $sanitizer->sanitize($request->input('widgets', []));
        }

        $model->update($attrs);

        return back();
    }

    public function destroy(Request $request, string $dashboard)
    {
        $project = $request->get('project');
        $user = $request->user();
        $model = $user->dashboards()->where('project_id', $project->id)->findOrFail($dashboard);
        $wasDefault = $model->is_default;
        $model->delete();

        if ($wasDefault) {
            $next = $user->dashboards()->where('project_id', $project->id)->orderBy('position')->first();
            $next?->update(['is_default' => true]);
        }

        return back();
    }

    public function reorder(Request $request)
    {
        $request->validate(['ids' => ['array'], 'ids.*' => ['string']]);

        $project = $request->get('project');
        $ids = (array) $request->input('ids', []);
        foreach ($ids as $pos => $id) {
            $request->user()->dashboards()->where('project_id', $project->id)->where('id', $id)->update(['position' => $pos]);
        }

        return back();
    }
}
