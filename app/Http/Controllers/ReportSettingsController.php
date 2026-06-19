<?php

namespace App\Http\Controllers;

use App\Enums\ReportFrequency;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReportSettingsController extends Controller
{
    public function show(Request $request)
    {
        /** @var Project $project */
        $project = $request->get('project');

        $current = $request->user()->projects()->whereKey($project->id)->first()?->pivot->report_frequency
            ?? ReportFrequency::Off;

        return inertia('Settings/Notifications', [
            'frequency' => $current->value,
            'options' => ReportFrequency::options(),
        ]);
    }

    public function update(Request $request)
    {
        /** @var Project $project */
        $project = $request->get('project');

        $data = $request->validate([
            'frequency' => ['required', Rule::enum(ReportFrequency::class)],
        ]);

        $request->user()->projects()->updateExistingPivot($project->id, [
            'report_frequency' => $data['frequency'],
        ]);

        return redirect()
            ->route('app.project.settings.notifications', ['project' => $project->id])
            ->with('success', 'Report preferences updated.');
    }
}
