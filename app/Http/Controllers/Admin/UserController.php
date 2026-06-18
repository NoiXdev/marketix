<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UserRequest;
use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->string('search')->toString();

        $users = User::query()
            ->when($search, fn ($q) => $q->where(fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")))
            ->withCount('projects')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'super_admin' => $u->super_admin,
                'projects_count' => $u->projects_count,
            ]);

        return inertia('Admin/Users/Index', ['users' => $users, 'search' => $search]);
    }

    public function create()
    {
        return inertia('Admin/Users/Create');
    }

    public function store(UserRequest $request)
    {
        $data = $request->validated();

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
        ]);

        $user->super_admin = (bool) ($data['super_admin'] ?? false);
        $user->force_password_change = (bool) ($data['force_password_change'] ?? false);
        $user->save();

        return redirect()->route('app.admin.users.index')->with('success', 'User created.');
    }

    public function edit(string $user)
    {
        $model = User::findOrFail($user);

        return inertia('Admin/Users/Edit', [
            'user' => [
                'id' => $model->id,
                'name' => $model->name,
                'email' => $model->email,
                'super_admin' => $model->super_admin,
                'force_password_change' => $model->force_password_change,
            ],
        ]);
    }

    public function update(UserRequest $request, string $user)
    {
        $model = User::findOrFail($user);
        $data = $request->validated();

        $wantsSuperAdmin = (bool) ($data['super_admin'] ?? false);
        if ($model->id === $request->user()->id && $model->super_admin && ! $wantsSuperAdmin) {
            return back()->with('error', 'You cannot remove your own super-admin access.');
        }

        $model->name = $data['name'];
        $model->email = $data['email'];
        if (! empty($data['password'])) {
            $model->password = $data['password'];
        }
        $model->super_admin = $wantsSuperAdmin;
        $model->force_password_change = (bool) ($data['force_password_change'] ?? false);
        $model->save();

        return redirect()->route('app.admin.users.index')->with('success', 'User updated.');
    }

    public function destroy(Request $request, string $user)
    {
        if ($user === $request->user()->id) {
            return back()->with('error', 'You cannot delete your own account.');
        }

        $model = User::findOrFail($user);
        $model->projects()->detach();
        $model->delete();

        return redirect()->route('app.admin.users.index')->with('success', 'User deleted.');
    }
}
