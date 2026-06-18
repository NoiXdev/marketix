<?php

namespace App\Http\Controllers;

use App\Support\TwoFactor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class TwoFactorController extends Controller
{
    public function __construct(private readonly TwoFactor $twoFactor) {}

    public function enable(Request $request)
    {
        $user = $request->user();

        if ($user->hasTwoFactorEnabled()) {
            return back()->withErrors(['code' => 'Two-factor authentication is already enabled.']);
        }

        $user->forceFill([
            'two_factor_secret' => $this->twoFactor->generateSecret(),
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        return redirect()->route('app.profile.edit');
    }

    public function confirm(Request $request)
    {
        $request->validate(['code' => ['required', 'string']]);

        $user = $request->user();

        if (! $user->two_factor_secret || $user->hasTwoFactorEnabled()) {
            return back()->withErrors(['code' => 'Two-factor setup is not in progress.']);
        }

        if (! $this->twoFactor->verify($user->two_factor_secret, $request->string('code')->toString())) {
            return back()->withErrors(['code' => 'The provided code was invalid.']);
        }

        $plain = $this->twoFactor->generateRecoveryCodes();

        $user->forceFill([
            'two_factor_recovery_codes' => array_map(fn (string $c): string => Hash::make($c), $plain),
            'two_factor_confirmed_at' => now(),
        ])->save();

        return redirect()->route('app.profile.edit')->with('recoveryCodes', $plain);
    }

    public function disable(Request $request)
    {
        $request->validate(['current_password' => ['required', 'current_password']]);

        $request->user()->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        return redirect()->route('app.profile.edit');
    }

    public function regenerateRecoveryCodes(Request $request)
    {
        $request->validate(['current_password' => ['required', 'current_password']]);

        $user = $request->user();
        abort_unless($user->hasTwoFactorEnabled(), 400);

        $plain = $this->twoFactor->generateRecoveryCodes();

        $user->forceFill([
            'two_factor_recovery_codes' => array_map(fn (string $c): string => Hash::make($c), $plain),
        ])->save();

        return redirect()->route('app.profile.edit')->with('recoveryCodes', $plain);
    }
}
