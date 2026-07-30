<?php

namespace App\Http\Controllers;

use App\Support\ActivityRecorder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

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

        $key = $this->throttleKey($request);

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);

            throw ValidationException::withMessages([
                'email' => "Too many login attempts. Please try again in {$seconds} seconds.",
            ]);
        }

        if (! Auth::validate($credentials)) {
            RateLimiter::hit($key); // 1 decay minute (default)

            return back()->withErrors([
                'email' => 'These credentials do not match our records.',
            ])->onlyInput('email');
        }

        RateLimiter::clear($key);

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

    private function throttleKey(Request $request): string
    {
        return Str::lower((string) $request->input('email')).'|'.$request->ip();
    }
}
