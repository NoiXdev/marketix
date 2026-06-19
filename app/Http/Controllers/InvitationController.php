<?php

namespace App\Http\Controllers;

use App\Models\ProjectInvitation;
use App\Models\User;
use App\Support\ActivityRecorder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class InvitationController extends Controller
{
    public function show(Request $request, string $token)
    {
        $invitation = $this->resolve($token);

        if (! $invitation || ! $invitation->isPending()) {
            return inertia('Auth/AcceptInvitation', ['state' => 'invalid']);
        }

        $existing = User::where('email', $invitation->email)->first();
        $current = $request->user();

        if ($current && Str::lower($current->email) !== Str::lower($invitation->email)) {
            return inertia('Auth/AcceptInvitation', [
                'state' => 'wrong_user',
                'email' => $invitation->email,
            ]);
        }

        return inertia('Auth/AcceptInvitation', [
            'state' => 'valid',
            'token' => $token,
            'email' => $invitation->email,
            'projectName' => $invitation->project->name,
            'needsAccount' => $existing === null,
            'authenticated' => $current !== null,
        ]);
    }

    public function accept(Request $request, string $token)
    {
        $invitation = $this->resolve($token);

        if (! $invitation || ! $invitation->isPending()) {
            return redirect()->route('app.invitations.show', ['token' => $token]);
        }

        // Guard: if a different user is already authenticated, refuse before any
        // account creation or attachment occurs (prevents minting accounts for
        // emails that don't belong to the authenticated user).
        $current = $request->user();
        if ($current && Str::lower($current->email) !== Str::lower($invitation->email)) {
            return redirect()->route('app.invitations.show', ['token' => $token]);
        }

        $user = User::where('email', $invitation->email)->first();

        if (! $user) {
            $data = $request->validate([
                'name' => ['required', 'string', 'max:255'],
                'password' => ['required', 'string', 'min:8', 'confirmed'],
            ]);

            $user = User::create([
                'name' => $data['name'],
                'email' => $invitation->email,
                'password' => $data['password'],
            ]);
        } elseif (! $request->user() || $request->user()->id !== $user->id) {
            // Existing account but not logged in as them — send to login first.
            return redirect()->route('app.auth.show-login')
                ->with('warning', 'Please log in as '.$invitation->email.' to accept this invitation.');
        }

        $invitation->project->users()->syncWithoutDetaching([
            $user->id => ['role' => $invitation->role->value, 'active' => true],
        ]);

        $invitation->update(['accepted_at' => now()]);

        ActivityRecorder::project('invitation', 'invitation_accepted', $invitation->project_id, $user, $invitation->project, [
            'email' => $invitation->email,
        ]);

        Auth::login($user);

        return redirect()->route('app.project.dashboard', ['project' => $invitation->project_id]);
    }

    private function resolve(string $token): ?ProjectInvitation
    {
        return ProjectInvitation::where('token', ProjectInvitation::hashToken($token))->first();
    }
}
