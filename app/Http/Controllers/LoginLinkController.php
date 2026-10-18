<?php

namespace App\Http\Controllers;

use App\Support\ActivityRecorder;
use App\Support\LoginLink;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Consumes a support login link: authenticates the target user outright,
 * skipping password, two-factor and passkey checks.
 */
class LoginLinkController extends Controller
{
    public function __invoke(Request $request, string $token)
    {
        $user = LoginLink::consume($token);

        if ($user === null) {
            Auth::logout();

            return redirect()->route('app.auth.show-login')->withErrors([
                'email' => __('auth.login_link.invalid'),
            ]);
        }

        Auth::login($user);
        $request->session()->regenerate();

        ActivityRecorder::security('login_link.used', $user);

        return redirect()->route('app.root');
    }
}
