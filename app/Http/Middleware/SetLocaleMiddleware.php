<?php

namespace App\Http\Middleware;

use App\Support\Locales;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

class SetLocaleMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        App::setLocale($this->resolve($request));

        return $next($request);
    }

    private function resolve(Request $request): string
    {
        $user = $request->user();
        if ($user && Locales::isSupported((string) $user->locale)) {
            return $user->locale;
        }

        $cookie = $request->cookie('locale');
        if (is_string($cookie) && Locales::isSupported($cookie)) {
            return $cookie;
        }

        $preferred = $request->getPreferredLanguage(Locales::codes());
        if (is_string($preferred) && Locales::isSupported($preferred)) {
            return $preferred;
        }

        return Locales::default();
    }
}
