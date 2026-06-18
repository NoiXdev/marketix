<?php

namespace App\Http\Controllers;

use App\Http\Requests\ForcePasswordChangeRequest;
use Illuminate\Http\Request;

class ForcePasswordChangeController extends Controller
{
    public function show(Request $request)
    {
        return inertia('Auth/ForcePasswordChange');
    }

    public function update(ForcePasswordChangeRequest $request)
    {
        $user = $request->user();
        $user->password = $request->validated()['password'];
        $user->force_password_change = false;
        $user->save();

        return redirect('/')->with('success', 'Password updated.');
    }
}
