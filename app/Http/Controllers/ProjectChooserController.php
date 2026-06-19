<?php

namespace App\Http\Controllers;

use App\Enums\ProjectRole;
use App\Models\Project;
use Illuminate\Http\Request;

class ProjectChooserController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $projects = $user->accessibleProjects()
            ->get()
            ->sortBy('name')
            ->map(fn (Project $project) => [
                'id' => $project->id,
                'name' => $project->name,
                'role' => $user->super_admin
                    ? ProjectRole::Admin->value
                    : ($project->pivot->role?->value ?? ProjectRole::Member->value),
            ])
            ->values();

        return inertia('ChooseProject', [
            'projects' => $projects,
        ]);
    }
}
