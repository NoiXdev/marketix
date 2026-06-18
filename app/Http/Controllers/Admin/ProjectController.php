<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProjectRequest;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->string('search')->toString();

        $projects = Project::query()
            ->when($search, fn ($q) => $q->where('name', 'like', "%{$search}%"))
            ->withCount('users')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Project $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'locked' => $p->locked,
                'users_count' => $p->users_count,
            ]);

        return inertia('Admin/Projects/Index', ['projects' => $projects, 'search' => $search]);
    }

    public function create()
    {
        return inertia('Admin/Projects/Create');
    }

    public function store(ProjectRequest $request)
    {
        Project::create($request->validated());

        return redirect()->route('app.admin.projects.index')->with('success', 'Project created.');
    }

    public function edit(string $project)
    {
        $model = Project::findOrFail($project);
        $memberIds = $model->users()->pluck('users.id');

        return inertia('Admin/Projects/Edit', [
            'project' => ['id' => $model->id, 'name' => $model->name, 'locked' => $model->locked],
            'members' => $model->users()->get()->map(fn ($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'role' => $u->pivot->role->value,
            ]),
            'assignableUsers' => User::whereNotIn('id', $memberIds)->orderBy('name')->get(['id', 'name', 'email']),
        ]);
    }

    public function update(ProjectRequest $request, string $project)
    {
        $model = Project::findOrFail($project);
        $model->update($request->validated());

        return redirect()->route('app.admin.projects.index')->with('success', 'Project updated.');
    }

    public function destroy(string $project)
    {
        Project::findOrFail($project)->delete();

        return redirect()->route('app.admin.projects.index')->with('success', 'Project deleted.');
    }
}
