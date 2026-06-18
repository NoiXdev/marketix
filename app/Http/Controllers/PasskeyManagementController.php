<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Laravel\Passkeys\Passkey;

class PasskeyManagementController extends Controller
{
    public function rename(Request $request, Passkey $passkey)
    {
        abort_unless((string) $passkey->user_id === (string) $request->user()->getKey(), 403);

        $validated = $request->validate(['name' => ['required', 'string', 'max:255']]);

        $passkey->update(['name' => $validated['name']]);

        return back()->with('status', 'passkey-renamed');
    }
}
