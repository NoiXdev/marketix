<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ForcePasswordChange
{
    /**
     * Routes a flagged user may still reach, so they are never trapped.
     */
    private const ALLOWED = [
        'app.password.change.show',
        'app.password.change.update',
        'app.auth.logout',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->force_password_change
            && ! in_array($request->route()?->getName(), self::ALLOWED, true)) {
            return redirect()->route('app.password.change.show');
        }

        return $next($request);
    }
}
