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

        $user->dashboards()->create([
            'project_id' => $project->id,
            'name' => $request->validated('name'),
            'is_default' => false,
            'position' => $position,
            'widgets' => [],
        ]);

        return back();
    }

    public function update(DashboardSaveRequest $request, WidgetSanitizer $sanitizer, string $dashboard)
    {
        $project = $request->get('project');
        $model = $request->user()->dashboards()->where('project_id', $project->id)->findOrFail($dashboard);

        $model->update([
            'name' => $request->validated('name'),
            'widgets' => $sanitizer->sanitize($request->input('widgets', [])),
        ]);

        return back();
    }

    public function destroy(Request $request, string $dashboard)
    {
        $project = $request->get('project');
        $request->user()->dashboards()->where('project_id', $project->id)->findOrFail($dashboard)->delete();

        return back();
    }

    public function reorder(Request $request)
    {
        $project = $request->get('project');
        $ids = (array) $request->input('ids', []);
        foreach ($ids as $pos => $id) {
            $request->user()->dashboards()->where('project_id', $project->id)->where('id', $id)->update(['position' => $pos]);
        }

        return back();
    }
}
