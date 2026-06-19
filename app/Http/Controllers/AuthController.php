<?php

namespace App\Http\Controllers;

use App\Support\ActivityRecorder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function showLogin()
    {
        return inertia('Auth/Login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (! Auth::validate($credentials)) {
            return back()->withErrors([
                'email' => 'These credentials do not match our records.',
            ])->onlyInput('email');
        }

        $user = Auth::getProvider()->retrieveByCredentials($credentials);

        if ($user->hasTwoFactorEnabled()) {
            $request->session()->put('auth.2fa.pending_id', $user->getKey());
            $request->session()->put('auth.2fa.remember', $request->boolean('remember'));

            return redirect()->route('app.auth.two-factor.show');
        }

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        ActivityRecorder::security('login', $user);

        return redirect('/');
    }

    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('app.auth.show-login');
    }
}
