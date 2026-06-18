<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ProjectRole;
use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProjectMemberController extends Controller
{
    public function store(Request $request, string $project)
    {
        $model = Project::findOrFail($project);
        $data = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'role' => ['required', Rule::enum(ProjectRole::class)],
        ]);

        $model->users()->syncWithoutDetaching([
            $data['user_id'] => ['role' => $data['role'], 'active' => true],
        ]);

        return back()->with('success', 'Member assigned.');
    }

    public function update(Request $request, string $project, string $user)
    {
        // Intentionally no last-admin guard here — super admins are the recovery
        // path for projects that have lost all admins. See TeamController for the
        // last-admin guard that applies to regular project members.
        $model = Project::findOrFail($project);
        $data = $request->validate(['role' => ['required', Rule::enum(ProjectRole::class)]]);

        $model->users()->updateExistingPivot($user, ['role' => $data['role']]);

        return back()->with('success', 'Member updated.');
    }

    public function destroy(string $project, string $user)
    {
        // Intentionally no last-admin guard here — super admins are the recovery
        // path for projects that have lost all admins. See TeamController for the
        // last-admin guard that applies to regular project members.
        Project::findOrFail($project)->users()->detach($user);

        return back()->with('success', 'Member removed.');
    }
}
