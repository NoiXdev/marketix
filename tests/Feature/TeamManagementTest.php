<?php

namespace Tests\Feature;

use App\Enums\ProjectRole;
use App\Mail\ProjectInvitationMail;
use App\Models\Project;
use App\Models\ProjectInvitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class TeamManagementTest extends TestCase
{
    use RefreshDatabase;

    private function projectWithAdmin(): array
    {
        $admin = User::factory()->create();
        $project = Project::factory()->create();
        $project->users()->attach($admin, ['role' => ProjectRole::Admin->value, 'active' => true]);

        return [$admin, $project];
    }

    public function test_member_cannot_view_team_page(): void
    {
        $member = User::factory()->create();
        $project = Project::factory()->create();
        $project->users()->attach($member, ['role' => ProjectRole::Member->value, 'active' => true]);

        $this->actingAs($member)
            ->get(route('app.project.team.index', ['project' => $project->id]))
            ->assertForbidden();
    }

    public function test_admin_sees_members_and_pending_invitations(): void
    {
        [$admin, $project] = $this->projectWithAdmin();
        ProjectInvitation::factory()->create(['project_id' => $project->id, 'email' => 'pending@example.com']);
        ProjectInvitation::factory()->accepted()->create(['project_id' => $project->id, 'email' => 'gone@example.com']);

        $this->actingAs($admin)
            ->get(route('app.project.team.index', ['project' => $project->id]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Team/Index')
                ->has('members', 1)
                ->where('members.0.email', $admin->email)
                ->where('members.0.role', 'admin')
                ->has('invitations', 1)
                ->where('invitations.0.email', 'pending@example.com')
            );
    }

    public function test_admin_can_invite_a_new_email(): void
    {
        Mail::fake();
        [$admin, $project] = $this->projectWithAdmin();

        $this->actingAs($admin)
            ->post(route('app.project.team.invitations.store', ['project' => $project->id]), [
                'email' => 'new@example.com',
                'role' => 'member',
            ])
            ->assertRedirect(route('app.project.team.index', ['project' => $project->id]));

        $this->assertDatabaseHas('project_invitations', [
            'project_id' => $project->id,
            'email' => 'new@example.com',
            'role' => 'member',
        ]);
        Mail::assertQueued(ProjectInvitationMail::class);
    }

    public function test_cannot_invite_existing_active_member(): void
    {
        [$admin, $project] = $this->projectWithAdmin();

        $this->actingAs($admin)
            ->post(route('app.project.team.invitations.store', ['project' => $project->id]), [
                'email' => $admin->email,
                'role' => 'member',
            ])
            ->assertSessionHasErrors('email');
    }

    public function test_reinviting_replaces_existing_pending_invite(): void
    {
        Mail::fake();
        [$admin, $project] = $this->projectWithAdmin();

        $this->actingAs($admin)->post(route('app.project.team.invitations.store', ['project' => $project->id]), [
            'email' => 'dup@example.com', 'role' => 'member',
        ]);
        $this->actingAs($admin)->post(route('app.project.team.invitations.store', ['project' => $project->id]), [
            'email' => 'dup@example.com', 'role' => 'admin',
        ]);

        $this->assertSame(1, $project->invitations()->where('email', 'dup@example.com')->count());
        $this->assertDatabaseHas('project_invitations', ['email' => 'dup@example.com', 'role' => 'admin']);
    }

    public function test_admin_can_revoke_invitation(): void
    {
        [$admin, $project] = $this->projectWithAdmin();
        $invite = ProjectInvitation::factory()->create(['project_id' => $project->id]);

        $this->actingAs($admin)
            ->delete(route('app.project.team.invitations.destroy', ['project' => $project->id, 'invitation' => $invite->id]))
            ->assertRedirect();

        $this->assertDatabaseMissing('project_invitations', ['id' => $invite->id]);
    }

    public function test_admin_can_change_member_role(): void
    {
        [$admin, $project] = $this->projectWithAdmin();
        $member = User::factory()->create();
        $project->users()->attach($member, ['role' => ProjectRole::Member->value, 'active' => true]);

        $this->actingAs($admin)
            ->patch(route('app.project.team.members.update', ['project' => $project->id, 'user' => $member->id]), [
                'role' => 'admin',
            ])
            ->assertRedirect();

        $this->assertSame(ProjectRole::Admin, $member->fresh()->roleInProject($project));
    }

    public function test_admin_can_remove_a_member(): void
    {
        [$admin, $project] = $this->projectWithAdmin();
        $member = User::factory()->create();
        $project->users()->attach($member, ['role' => ProjectRole::Member->value, 'active' => true]);

        $this->actingAs($admin)
            ->delete(route('app.project.team.members.destroy', ['project' => $project->id, 'user' => $member->id]))
            ->assertRedirect();

        $this->assertFalse($member->fresh()->canAccessProject($project));
    }

    public function test_cannot_remove_self(): void
    {
        [$admin, $project] = $this->projectWithAdmin();

        $this->actingAs($admin)
            ->delete(route('app.project.team.members.destroy', ['project' => $project->id, 'user' => $admin->id]))
            ->assertSessionHas('error');

        $this->assertTrue($admin->fresh()->canAccessProject($project));
    }

    public function test_cannot_demote_last_admin(): void
    {
        [$admin, $project] = $this->projectWithAdmin();

        $this->actingAs($admin)
            ->patch(route('app.project.team.members.update', ['project' => $project->id, 'user' => $admin->id]), [
                'role' => 'member',
            ])
            ->assertSessionHas('error');

        $this->assertSame(ProjectRole::Admin, $admin->fresh()->roleInProject($project));
    }

    public function test_non_admin_member_cannot_send_invitation(): void
    {
        // The project_admin middleware must block non-admin members from POSTing
        // to the invitations store endpoint.
        $member = User::factory()->create();
        $project = Project::factory()->create();
        $project->users()->attach($member, ['role' => ProjectRole::Member->value, 'active' => true]);

        $this->actingAs($member)
            ->post(route('app.project.team.invitations.store', ['project' => $project->id]), [
                'email' => 'anyone@example.com',
                'role' => 'member',
            ])
            ->assertForbidden();
    }

    public function test_admin_can_resend_a_pending_invitation(): void
    {
        Mail::fake();
        [$admin, $project] = $this->projectWithAdmin();
        $invite = ProjectInvitation::factory()->create([
            'project_id' => $project->id,
            'last_sent_at' => now()->subMinutes(5),
            'expires_at' => now()->addDay(),
        ]);
        $oldToken = $invite->token;

        $this->actingAs($admin)
            ->post(route('app.project.team.invitations.resend', ['project' => $project->id, 'invitation' => $invite->id]))
            ->assertRedirect(route('app.project.team.index', ['project' => $project->id]))
            ->assertSessionHas('success');

        $fresh = $invite->fresh();
        $this->assertNotSame($oldToken, $fresh->token);
        $this->assertTrue($fresh->expires_at->isFuture());
        $this->assertNotNull($fresh->last_sent_at);
        Mail::assertQueued(ProjectInvitationMail::class);
    }

    public function test_admin_can_resend_an_expired_invitation(): void
    {
        Mail::fake();
        [$admin, $project] = $this->projectWithAdmin();
        $invite = ProjectInvitation::factory()->expired()->create([
            'project_id' => $project->id,
            'last_sent_at' => now()->subDays(8),
        ]);

        $this->actingAs($admin)
            ->post(route('app.project.team.invitations.resend', ['project' => $project->id, 'invitation' => $invite->id]))
            ->assertRedirect();

        $this->assertTrue($invite->fresh()->expires_at->isFuture());
        Mail::assertQueued(ProjectInvitationMail::class);
    }

    public function test_resend_within_cooldown_is_rejected(): void
    {
        Mail::fake();
        [$admin, $project] = $this->projectWithAdmin();
        $invite = ProjectInvitation::factory()->create([
            'project_id' => $project->id,
            'last_sent_at' => now()->subSeconds(5),
        ]);

        $this->actingAs($admin)
            ->post(route('app.project.team.invitations.resend', ['project' => $project->id, 'invitation' => $invite->id]))
            ->assertSessionHas('error');

        Mail::assertNothingQueued();
    }

    public function test_resend_of_accepted_invitation_is_not_found(): void
    {
        Mail::fake();
        [$admin, $project] = $this->projectWithAdmin();
        $invite = ProjectInvitation::factory()->accepted()->create([
            'project_id' => $project->id,
            'last_sent_at' => now()->subDay(),
        ]);

        $this->actingAs($admin)
            ->post(route('app.project.team.invitations.resend', ['project' => $project->id, 'invitation' => $invite->id]))
            ->assertNotFound();

        Mail::assertNothingQueued();
    }

    public function test_non_admin_member_cannot_resend(): void
    {
        $member = User::factory()->create();
        $project = Project::factory()->create();
        $project->users()->attach($member, ['role' => ProjectRole::Member->value, 'active' => true]);
        $invite = ProjectInvitation::factory()->create(['project_id' => $project->id]);

        $this->actingAs($member)
            ->post(route('app.project.team.invitations.resend', ['project' => $project->id, 'invitation' => $invite->id]))
            ->assertForbidden();
    }

    public function test_index_includes_expired_invitations_with_flags(): void
    {
        [$admin, $project] = $this->projectWithAdmin();
        ProjectInvitation::factory()->expired()->create([
            'project_id' => $project->id,
            'email' => 'expired@example.com',
            'last_sent_at' => now()->subDays(8),
        ]);

        $this->actingAs($admin)
            ->get(route('app.project.team.index', ['project' => $project->id]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('invitations', 1)
                ->where('invitations.0.email', 'expired@example.com')
                ->where('invitations.0.expired', true)
                ->where('invitations.0.can_resend', true)
            );
    }
}
