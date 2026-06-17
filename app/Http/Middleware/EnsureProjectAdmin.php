<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureProjectAdmin
{
    public function handle(Request $request, Closure $next)
    {
        $project = $request->get('project');

        if (! $project || ! $request->user()?->isProjectAdmin($project)) {
            abort(403);
        }

        return $next($request);
    }
}
