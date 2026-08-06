<?php

namespace App\Actions;

use App\Models\Project;
use App\Models\Url;
use App\Models\User;

class CreateLink
{
    /**
     * Persist a new Url under the given project for the given user.
     *
     * Consumes already-validated data (slug, url, type, status, password,
     * expired_at, targeting_geo, targeting_device, targeting_language,
     * targeting_ab). Owns persistence only — no HTTP concerns (validation,
     * redirect, flash) live here; callers (HTTP controller, MCP tools) own
     * those.
     *
     * `user_id` is set explicitly rather than relying on UrlObserver's
     * auth()-based default, since callers such as MCP tools act on behalf
     * of a specific user outside the web auth context.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(Project $project, User $user, array $data): Url
    {
        return $project->urls()->create([
            ...$data,
            'user_id' => $user->id,
        ]);
    }
}
