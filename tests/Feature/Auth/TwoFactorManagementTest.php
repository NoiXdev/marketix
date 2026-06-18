<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TwoFactorManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_two_factor_columns_cast_and_default_disabled(): void
    {
        $user = User::factory()->create();

        $this->assertNull($user->two_factor_secret);
        $this->assertNull($user->two_factor_confirmed_at);
        $this->assertFalse($user->hasTwoFactorEnabled());

        $user->forceFill([
            'two_factor_secret' => 'SECRET123',
            'two_factor_recovery_codes' => ['hash-a', 'hash-b'],
            'two_factor_confirmed_at' => now(),
        ])->save();

        $fresh = $user->fresh();
        $this->assertSame('SECRET123', $fresh->two_factor_secret);
        $this->assertSame(['hash-a', 'hash-b'], $fresh->two_factor_recovery_codes);
        $this->assertTrue($fresh->hasTwoFactorEnabled());
    }
}
