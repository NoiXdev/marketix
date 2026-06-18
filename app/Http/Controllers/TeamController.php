<?php

namespace App\Http\Controllers;

use App\Enums\ProjectRole;
use App\Http\Requests\InvitationRequest;
use App\Mail\ProjectInvitationMail;
use App\Models\Project;
use App\Models\ProjectInvitation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class TeamController extends Controller
{
    public function index(Request $request)
    {
        /** @var Project $project */
        $project = $request->get('project');

        return inertia('Team/Index', [
            'members' => $project->users()->get()->map(fn ($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'role' => $u->pivot->role->value,
            ]),
            'invitations' => $project->invitations()
                ->whereNull('accepted_at')
                ->where('expires_at', '>', now())
                ->latest()
                ->get()
                ->map(fn ($i) => [
                    'id' => $i->id,
                    'email' => $i->email,
                    'role' => $i->role->value,
                    'expires_at' => $i->expires_at->toIso8601String(),
                ]),
        ]);
    }

    public function storeInvitation(InvitationRequest $request)
    {
        /** @var Project $project */
        $project = $request->get('project');
        $data = $request->validated();

        // Replace any existing pending invite for this email.
        $project->invitations()->where('email', $data['email'])->whereNull('accepted_at')->delete();

        $token = Str::random(40);

        $invitation = $project->invitations()->create([
            'email' => $data['email'],
            'role' => $data['role'],
            'token' => ProjectInvitation::hashToken($token),
            'invited_by' => $request->user()->id,
            'expires_at' => now()->addDays(7),
        ]);

        Mail::to($data['email'])->queue(new ProjectInvitationMail($invitation, $token));

        return redirect()->route('app.project.team.index', ['project' => $project->id])
            ->with('success', 'Invitation sent.');
    }

    public function destroyInvitation(Request $request, string $invitation)
    {
        /** @var Project $project */
        $project = $request->get('project');

        $project->invitations()->findOrFail($invitation)->delete();

        return redirect()->route('app.project.team.index', ['project' => $project->id])
            ->with('success', 'Invitation revoked.');
    }

    public function updateMember(Request $request, string $user)
    {
        /** @var Project $project */
        $project = $request->get('project');
        $data = $request->validate(['role' => ['required', Rule::enum(ProjectRole::class)]]);

        if ($data['role'] === ProjectRole::Member->value && $this->isLastAdmin($project, $user)) {
            return back()->with('error', 'A project must keep at least one admin.');
        }

        $project->users()->updateExistingPivot($user, ['role' => $data['role']]);

        return redirect()->route('app.project.team.index', ['project' => $project->id])
            ->with('success', 'Member updated.');
    }

    public function destroyMember(Request $request, string $user)
    {
        /** @var Project $project */
        $project = $request->get('project');

        if ($user === $request->user()->id) {
            return back()->with('error', 'You cannot remove yourself from the project.');
        }

        if ($this->isLastAdmin($project, $user)) {
            return back()->with('error', 'A project must keep at least one admin.');
        }

        $project->users()->detach($user);

        return redirect()->route('app.project.team.index', ['project' => $project->id])
            ->with('success', 'Member removed.');
    }

    private function isLastAdmin(Project $project, string $userId): bool
    {
        $isAdmin = $project->users()->where('users.id', $userId)
            ->wherePivot('role', ProjectRole::Admin->value)->exists();

        if (! $isAdmin) {
            return false;
        }

        return $project->users()->wherePivot('role', ProjectRole::Admin->value)->count() <= 1;
    }
}
