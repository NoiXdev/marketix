<?php

namespace App\Http\Controllers;

use App\Support\ActivityRecorder;
use Illuminate\Http\Request;
use Laravel\Passkeys\Passkey;

class PasskeyManagementController extends Controller
{
    public function rename(Request $request, Passkey $passkey)
    {
        abort_unless((string) $passkey->user_id === (string) $request->user()->getKey(), 403);

        $validated = $request->validate(['name' => ['required', 'string', 'max:255']]);

        $passkey->update(['name' => $validated['name']]);

        ActivityRecorder::security('passkey_renamed', $request->user(), ['passkey' => $validated['name']]);

        return back()->with('status', 'passkey-renamed');
    }
}
