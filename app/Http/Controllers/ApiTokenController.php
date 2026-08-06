<?php

namespace App\Http\Controllers;

use App\Http\Requests\ApiTokenRequest;
use Illuminate\Http\Request;

class ApiTokenController extends Controller
{
    public function store(ApiTokenRequest $request)
    {
        $validated = $request->validated();

        $plain = $request->user()->createToken($validated['name'])->plainTextToken;

        return back()->with('token', $plain);
    }

    public function destroy(Request $request, string $token)
    {
        $request->user()->tokens()->where('id', $token)->firstOrFail()->delete();

        return back()->with('success', 'API token revoked.');
    }
}
