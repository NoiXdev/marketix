<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ProjectRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserProjectController extends Controller
{
    public function store(Request $request, string $user)
    {
        $model = User::findOrFail($user);
        $data = $request->validate([
            'project_id' => ['required', 'exists:projects,id'],
            'role' => ['required', Rule::enum(ProjectRole::class)],
        ]);

        $model->projects()->syncWithoutDetaching([
            $data['project_id'] => ['role' => $data['role'], 'active' => true],
        ]);

        return back()->with('success', 'Project assigned.');
    }

    public function update(Request $request, string $user, string $project)
    {
        $model = User::findOrFail($user);
        $data = $request->validate(['role' => ['required', Rule::enum(ProjectRole::class)]]);

        $model->projects()->updateExistingPivot($project, ['role' => $data['role']]);

        return back()->with('success', 'Membership updated.');
    }

    public function destroy(string $user, string $project)
    {
        User::findOrFail($user)->projects()->detach($project);

        return back()->with('success', 'Project removed.');
    }
}
