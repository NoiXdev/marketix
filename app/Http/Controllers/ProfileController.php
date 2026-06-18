<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function edit(Request $request)
    {
        $user = $request->user();

        return inertia('Profile/Edit', [
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
            ],
        ]);
    }

    public function update(ProfileUpdateRequest $request)
    {
        $user = $request->user();
        $user->password = $request->validated()['password'];
        $user->save();

        return redirect()
            ->route('app.profile.edit')
            ->with('success', 'Password updated.');
    }
}
