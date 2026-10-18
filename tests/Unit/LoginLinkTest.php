<?php

namespace Tests\Unit;

use App\Models\User;
use App\Support\LoginLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class LoginLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_issued_token_resolves_back_to_the_user(): void
    {
        $user = User::factory()->create();

        $token = LoginLink::issue($user);

        $this->assertTrue(LoginLink::consume($token)?->is($user));
    }

    public function test_token_is_single_use(): void
    {
        $user = User::factory()->create();

        $token = LoginLink::issue($user);
        LoginLink::consume($token);

        $this->assertNull(LoginLink::consume($token));
    }

    public function test_token_expires_after_five_minutes(): void
    {
        $user = User::factory()->create();

        $token = LoginLink::issue($user);

        $this->travel(6)->minutes();

        $this->assertNull(LoginLink::consume($token));
    }

    public function test_token_is_still_valid_just_before_expiry(): void
    {
        $user = User::factory()->create();

        $token = LoginLink::issue($user);

        $this->travel(4)->minutes();

        $this->assertTrue(LoginLink::consume($token)?->is($user));
    }

    public function test_unknown_token_resolves_to_nothing(): void
    {
        $this->assertNull(LoginLink::consume('not-a-real-token'));
    }

    public function test_plaintext_token_is_never_stored(): void
    {
        $user = User::factory()->create();

        $token = LoginLink::issue($user);

        $this->assertNull(Cache::get('login-link:'.$token));
        $this->assertSame(
            (string) $user->getKey(),
            (string) Cache::get('login-link:'.hash('sha256', $token)),
        );
    }

    public function test_each_issued_token_is_unique(): void
    {
        $user = User::factory()->create();

        $this->assertNotSame(LoginLink::issue($user), LoginLink::issue($user));
    }
}
