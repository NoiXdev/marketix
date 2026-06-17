<?php

namespace Tests\Feature;

use App\Enums\ProjectRole;
use App\Models\Project;
use App\Models\ProjectInvitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class InvitationAcceptTest extends TestCase
{
    use RefreshDatabase;

    private function makeInvite(array $overrides = []): array
    {
        $project = Project::factory()->create();
        $inviter = User::factory()->create();
        $project->users()->attach($inviter, ['role' => ProjectRole::Admin->value, 'active' => true]);
        $token = Str::random(40);
        $invite = ProjectInvitation::factory()->create(array_merge([
            'project_id' => $project->id,
            'invited_by' => $inviter->id,
            'token' => ProjectInvitation::hashToken($token),
        ], $overrides));

        return [$invite, $token, $project];
    }

    public function test_show_valid_invitation_for_new_email(): void
    {
        [$invite, $token] = $this->makeInvite(['email' => 'newbie@example.com']);

        $this->get(route('app.invitations.show', ['token' => $token]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Auth/AcceptInvitation')
                ->where('state', 'valid')
                ->where('needsAccount', true)
                ->where('email', 'newbie@example.com')
            );
    }

    public function test_show_invalid_for_expired_token(): void
    {
        [$invite, $token] = $this->makeInvite(['email' => 'x@example.com', 'expires_at' => now()->subDay()]);

        $this->get(route('app.invitations.show', ['token' => $token]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->where('state', 'invalid'));
    }

    public function test_show_invalid_for_unknown_token(): void
    {
        $this->get(route('app.invitations.show', ['token' => 'nope']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->where('state', 'invalid'));
    }

    public function test_new_user_accepts_and_joins(): void
    {
        [$invite, $token, $project] = $this->makeInvite(['email' => 'newbie@example.com', 'role' => ProjectRole::Member->value]);

        $this->post(route('app.invitations.accept', ['token' => $token]), [
            'name' => 'New Bie',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect(route('app.project.dashboard', ['project' => $project->id]));

        $user = User::where('email', 'newbie@example.com')->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->canAccessProject($project));
        $this->assertSame(ProjectRole::Member, $user->roleInProject($project));
        $this->assertNotNull($invite->fresh()->accepted_at);
        $this->assertAuthenticatedAs($user);
    }

    public function test_existing_logged_in_user_accepts(): void
    {
        $user = User::factory()->create(['email' => 'member@example.com']);
        [$invite, $token, $project] = $this->makeInvite(['email' => 'member@example.com']);

        $this->actingAs($user)
            ->post(route('app.invitations.accept', ['token' => $token]))
            ->assertRedirect(route('app.project.dashboard', ['project' => $project->id]));

        $this->assertTrue($user->fresh()->canAccessProject($project));
    }

    public function test_wrong_logged_in_user_sees_wrong_user_state(): void
    {
        $other = User::factory()->create(['email' => 'other@example.com']);
        [$invite, $token] = $this->makeInvite(['email' => 'member@example.com']);

        $this->actingAs($other)
            ->get(route('app.invitations.show', ['token' => $token]))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('state', 'wrong_user'));
    }

    public function test_different_logged_in_user_cannot_accept_invite_for_no_account_email(): void
    {
        // A logged-in user who owns a valid token for a different email must NOT
        // be able to mint a new account bound to that email (Fix 1).
        $actor = User::factory()->create(['email' => 'actor@example.com']);
        [$invite, $token] = $this->makeInvite(['email' => 'victim@example.com']);

        $response = $this->actingAs($actor)
            ->post(route('app.invitations.accept', ['token' => $token]), [
                'name' => 'Stolen Name',
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ]);

        // Must NOT create the account
        $this->assertNull(User::where('email', 'victim@example.com')->first(), 'Account must not be created');
        // Must NOT log the actor out or switch to a different user
        $this->assertAuthenticatedAs($actor, 'web');
        // Must redirect to the show route (which renders the wrong_user state)
        $response->assertRedirect(route('app.invitations.show', ['token' => $token]));
    }

    public function test_show_same_email_different_case_does_not_show_wrong_user(): void
    {
        // Fix 2: the wrong_user guard in show() must be case-insensitive.
        // A logged-in user whose email matches the invitation email except for
        // case must NOT see the wrong_user state.
        $user = User::factory()->create(['email' => 'member@example.com']);
        [$invite, $token] = $this->makeInvite(['email' => 'MEMBER@EXAMPLE.COM']);

        $this->actingAs($user)
            ->get(route('app.invitations.show', ['token' => $token]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('state', 'valid')
            );
    }

    public function test_accept_guard_does_not_block_the_matching_user(): void
    {
        // The accept() wrong-user guard must not block the legitimate case: a
        // logged-in user whose email matches the invitation is attached normally.
        // (A genuine cross-case accept() assertion is impractical here because the
        // existing-user lookup `User::where('email', ...)` is case-sensitive on
        // SQLite; the case-insensitive comparison itself is covered by the show()
        // test test_show_same_email_different_case_does_not_show_wrong_user.)
        $user = User::factory()->create(['email' => 'member@example.com']);
        [$invite, $token, $project] = $this->makeInvite(['email' => 'member@example.com']);

        // The invitation and logged-in user share the same email (same case),
        // so the guard must pass and the user is attached to the project.
        $this->actingAs($user)
            ->post(route('app.invitations.accept', ['token' => $token]))
            ->assertRedirect(route('app.project.dashboard', ['project' => $project->id]));

        $this->assertTrue($user->fresh()->canAccessProject($project));
    }
}
