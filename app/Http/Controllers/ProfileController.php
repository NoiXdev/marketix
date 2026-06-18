<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function edit(Request $request)
    {
        $user = $request->user();

        $pending = $user->two_factor_secret && ! $user->hasTwoFactorEnabled();

        return inertia('Profile/Edit', [
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
            ],
            'twoFactorEnabled' => $user->hasTwoFactorEnabled(),
            'twoFactorPending' => (bool) $pending,
            'twoFactorSetup' => $pending ? [
                'secretKey' => $user->two_factor_secret,
                'qrCode' => app(\App\Support\TwoFactor::class)->qrCodeDataUri($user->email, $user->two_factor_secret),
            ] : null,
            'recoveryCodes' => $request->session()->get('recoveryCodes'),
        ]);
    }

    public function update(ProfileUpdateRequest $request)
    {
        $user = $request->user();
        $user->password = $request->validated()['password'];
        $user->save();

        return redirect()
            ->route('app.profile.edit')
            ->with('success', 'Password updated.');
    }
}
