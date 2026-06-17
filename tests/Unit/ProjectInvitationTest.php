<?php

namespace Tests\Unit;

use App\Enums\ProjectRole;
use App\Models\ProjectInvitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectInvitationTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_invitation(): void
    {
        $invite = ProjectInvitation::factory()->create();

        $this->assertTrue($invite->isPending());
        $this->assertFalse($invite->isExpired());
        $this->assertInstanceOf(ProjectRole::class, $invite->role);
    }

    public function test_expired_invitation(): void
    {
        $invite = ProjectInvitation::factory()->expired()->create();

        $this->assertTrue($invite->isExpired());
        $this->assertFalse($invite->isPending());
    }

    public function test_accepted_invitation_is_not_pending(): void
    {
        $invite = ProjectInvitation::factory()->accepted()->create();

        $this->assertFalse($invite->isPending());
    }

    public function test_hash_token_is_deterministic(): void
    {
        $this->assertSame(ProjectInvitation::hashToken('abc'), ProjectInvitation::hashToken('abc'));
        $this->assertNotSame('abc', ProjectInvitation::hashToken('abc'));
    }
}
