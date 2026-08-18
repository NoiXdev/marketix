<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * One-click entry into the shared demo account. Registered only when
 * config('demo.enabled') is true, so this route simply does not exist on a
 * normal instance.
 */
class DemoLoginController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = User::query()->where('email', config('demo.email'))->first();

        if (! $user) {
            // Nothing to log into — the instance has not been seeded.
            throw new NotFoundHttpException;
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect('/');
    }
}
