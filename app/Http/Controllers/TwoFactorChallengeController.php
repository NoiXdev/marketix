<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\TwoFactor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class TwoFactorChallengeController extends Controller
{
    public function __construct(private readonly TwoFactor $twoFactor) {}

    public function show(Request $request)
    {
        $user = $this->pendingUser($request);

        if (! $user) {
            $request->session()->forget(['auth.2fa.pending_id', 'auth.2fa.remember']);

            return redirect()->route('app.auth.show-login');
        }

        return inertia('Auth/TwoFactorChallenge', [
            'hasPasskeys' => $user->hasPasskeysEnabled(),
        ]);
    }

    public function store(Request $request)
    {
        $user = $this->pendingUser($request);

        if (! $user) {
            $request->session()->forget(['auth.2fa.pending_id', 'auth.2fa.remember']);

            return redirect()->route('app.auth.show-login');
        }

        $validated = $request->validate([
            'code' => ['nullable', 'string'],
            'recovery_code' => ['nullable', 'string'],
        ]);

        if (filled($validated['recovery_code'] ?? null)) {
            if (! $this->redeemRecoveryCode($user, $validated['recovery_code'])) {
                throw ValidationException::withMessages(['recovery_code' => 'The provided recovery code was invalid.']);
            }
        } elseif (filled($validated['code'] ?? null)) {
            if (! $this->twoFactor->verify($user->two_factor_secret, $validated['code'])) {
                throw ValidationException::withMessages(['code' => 'The provided code was invalid.']);
            }
        } else {
            throw ValidationException::withMessages(['code' => 'Enter your authentication or recovery code.']);
        }

        $this->completeLogin($request, $user);

        return redirect()->intended('/');
    }

    private function pendingUser(Request $request): ?User
    {
        $id = $request->session()->get('auth.2fa.pending_id');

        return $id ? User::find($id) : null;
    }

    private function redeemRecoveryCode(User $user, string $code): bool
    {
        $codes = $user->two_factor_recovery_codes ?? [];

        foreach ($codes as $index => $hash) {
            if (Hash::check($code, $hash)) {
                unset($codes[$index]);
                $user->forceFill(['two_factor_recovery_codes' => array_values($codes)])->save();

                return true;
            }
        }

        return false;
    }

    private function completeLogin(Request $request, User $user): void
    {
        Auth::login($user, (bool) $request->session()->pull('auth.2fa.remember', false));
        $request->session()->forget('auth.2fa.pending_id');
        $request->session()->regenerate();
    }
}
