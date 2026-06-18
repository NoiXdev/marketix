<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Passkeys\Actions\GenerateVerificationOptions;
use Laravel\Passkeys\Actions\VerifyPasskey;
use Laravel\Passkeys\Http\Requests\PasskeyVerificationRequest;
use Laravel\Passkeys\Support\WebAuthn;

class TwoFactorPasskeyController extends Controller
{
    public function options(Request $request, GenerateVerificationOptions $generate): JsonResponse
    {
        $user = $this->pendingUser($request);

        $options = $generate($user);

        $request->session()->put('passkey.verification_options', WebAuthn::toJson($options));

        return response()->json(['options' => WebAuthn::toBrowserArray($options)]);
    }

    public function verify(PasskeyVerificationRequest $request, VerifyPasskey $verify): JsonResponse
    {
        $user = $this->pendingUser($request);

        $verify($request->credential(), $request->verificationOptions(), $user);

        Auth::login($user, (bool) $request->session()->pull('auth.2fa.remember', false));
        $request->session()->forget('auth.2fa.pending_id');
        $request->session()->regenerate();

        return response()->json([
            'redirect' => redirect()->intended('/')->getTargetUrl(),
        ]);
    }

    private function pendingUser(Request $request): User
    {
        $id = $request->session()->get('auth.2fa.pending_id');

        abort_if(! $id, 409, 'No two-factor challenge in progress.');

        return User::findOrFail($id);
    }
}
