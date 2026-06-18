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

    public function test_can_resend_when_never_sent(): void
    {
        $invite = ProjectInvitation::factory()->make(['last_sent_at' => null]);

        $this->assertTrue($invite->canResend());
    }

    public function test_cannot_resend_within_cooldown(): void
    {
        $invite = ProjectInvitation::factory()->make(['last_sent_at' => now()->subSeconds(10)]);

        $this->assertFalse($invite->canResend());
    }

    public function test_can_resend_after_cooldown(): void
    {
        $invite = ProjectInvitation::factory()->make(['last_sent_at' => now()->subSeconds(120)]);

        $this->assertTrue($invite->canResend());
    }

    public function test_cannot_resend_accepted_invitation(): void
    {
        $invite = ProjectInvitation::factory()->make([
            'last_sent_at' => now()->subSeconds(120),
            'accepted_at' => now(),
        ]);

        $this->assertFalse($invite->canResend());
    }

    public function test_can_resend_expired_invitation(): void
    {
        $invite = ProjectInvitation::factory()->make([
            'expires_at' => now()->subDay(),
            'last_sent_at' => now()->subDay(),
            'accepted_at' => null,
        ]);

        $this->assertTrue($invite->canResend());
    }
}
