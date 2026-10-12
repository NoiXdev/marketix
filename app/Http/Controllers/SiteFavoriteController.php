<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class SiteFavoriteController extends Controller
{
    public function store(Request $request, string $site)
    {
        $project = $request->get('project');
        $request->user()->favoriteSites()->syncWithoutDetaching($project->sites()->findOrFail($site));

        return redirect()->route('app.project.sites.index', ['project' => $project->id]);
    }

    public function destroy(Request $request, string $site)
    {
        $project = $request->get('project');
        $request->user()->favoriteSites()->detach($project->sites()->findOrFail($site));

        return redirect()->route('app.project.sites.index', ['project' => $project->id]);
    }
}
