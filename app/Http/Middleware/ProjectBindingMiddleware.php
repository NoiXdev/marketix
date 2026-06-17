<?php

namespace App\Http\Middleware;

use App\Models\Project;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;

class ProjectBindingMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if (! Auth::check()) {
            return redirect()->route('app.auth.show-login');
        }

        $project = Project::findOrFail($request->route('project'));

        if (! auth()->user()->canAccessProject($project)) {
            abort(403);
        }

        Inertia::share('project', $project);
        Inertia::share('projects', auth()->user()->accessibleProjects()->get(['projects.id', 'projects.name'])
            ->map(fn ($p) => ['id' => $p->id, 'name' => $p->name]));
        Inertia::share('currentProjectRole', auth()->user()->roleInProject($project)?->value);

        URL::defaults(['project' => $request->route('project')]);

        // Remove from route params so controllers don't receive it as an argument
        $request->route()->forgetParameter('project');

        // Make the resolved model available via $request->get('project')
        $request->merge(['project' => $project]);

        return $next($request);
    }
}
