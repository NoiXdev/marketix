<?php

namespace App\Mcp\Concerns;

use App\Models\Project;
use Laravel\Mcp\Request;

trait ResolvesProject
{
    /**
     * Resolve the {@see Project} referenced by the request's "project" argument.
     *
     * Accepts either the project's id or its name (case-insensitively). Returns
     * null when no value is given, no matching project is found among the
     * caller's projects, or the caller cannot access the matched project.
     */
    protected function resolveProject(Request $request): ?Project
    {
        $value = $request->get('project');

        if ($value === null || $value === '') {
            return null;
        }

        $user = $request->user();

        if ($user === null) {
            return null;
        }

        $project = $user->projects->first(
            fn (Project $project): bool => (string) $project->id === (string) $value
                || strcasecmp($project->name, (string) $value) === 0,
        );

        if ($project === null || ! $user->canAccessProject($project)) {
            return null;
        }

        return $project;
    }
}
