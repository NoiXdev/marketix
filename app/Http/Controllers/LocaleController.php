<?php

namespace App\Http\Controllers;

use App\Support\Locales;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LocaleController extends Controller
{
    public function update(Request $request)
    {
        $validated = $request->validate([
            'locale' => ['required', 'string', Rule::in(Locales::codes())],
        ]);

        $locale = $validated['locale'];

        if ($user = $request->user()) {
            $user->update(['locale' => $locale]);
        }

        // One year, in minutes.
        return back()->withCookie(cookie('locale', $locale, 60 * 24 * 365));
    }
}
