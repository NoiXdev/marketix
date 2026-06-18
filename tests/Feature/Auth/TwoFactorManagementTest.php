<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia;
use PragmaRX\Google2FAQRCode\Google2FA;
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

    public function test_enable_generates_a_pending_secret_and_edit_exposes_setup(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('app.profile.two-factor.enable'))
            ->assertRedirect(route('app.profile.edit'));

        $this->assertNotNull($user->refresh()->two_factor_secret);
        $this->assertNull($user->two_factor_confirmed_at);

        $this->actingAs($user)->get(route('app.profile.edit'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('twoFactorEnabled', false)
                ->where('twoFactorPending', true)
                ->where('twoFactorSetup.secretKey', $user->two_factor_secret)
                ->has('twoFactorSetup.qrCode'));
    }

    public function test_confirm_with_valid_code_enables_2fa_and_returns_recovery_codes(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('app.profile.two-factor.enable'));
        $secret = $user->refresh()->two_factor_secret;
        $code = (new Google2FA)->getCurrentOtp($secret);

        $this->actingAs($user)->post(route('app.profile.two-factor.confirm'), ['code' => $code])
            ->assertRedirect(route('app.profile.edit'))
            ->assertSessionHas('recoveryCodes');

        $this->assertTrue($user->refresh()->hasTwoFactorEnabled());
        $this->assertCount(8, $user->two_factor_recovery_codes);
    }

    public function test_confirm_with_invalid_code_fails(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('app.profile.two-factor.enable'));

        $this->actingAs($user)->post(route('app.profile.two-factor.confirm'), ['code' => '000000'])
            ->assertSessionHasErrors('code');

        $this->assertFalse($user->refresh()->hasTwoFactorEnabled());
    }

    public function test_disable_requires_current_password_and_clears_columns(): void
    {
        $user = User::factory()->create();
        $this->enableTwoFactor($user);

        $this->actingAs($user)->delete(route('app.profile.two-factor.disable'), ['current_password' => 'wrong'])
            ->assertSessionHasErrors('current_password');
        $this->assertTrue($user->refresh()->hasTwoFactorEnabled());

        $this->actingAs($user)->delete(route('app.profile.two-factor.disable'), ['current_password' => 'password'])
            ->assertRedirect(route('app.profile.edit'));
        $this->assertNull($user->refresh()->two_factor_secret);
        $this->assertFalse($user->hasTwoFactorEnabled());
    }

    public function test_regenerate_recovery_codes_requires_password_and_replaces_codes(): void
    {
        $user = User::factory()->create();
        $this->enableTwoFactor($user);
        $original = $user->refresh()->two_factor_recovery_codes;

        $this->actingAs($user)->post(route('app.profile.two-factor.recovery-codes'), ['current_password' => 'password'])
            ->assertRedirect(route('app.profile.edit'))
            ->assertSessionHas('recoveryCodes');

        $this->assertNotSame($original, $user->refresh()->two_factor_recovery_codes);
    }

    private function enableTwoFactor(User $user): void
    {
        $secret = (new \App\Support\TwoFactor(new Google2FA))->generateSecret();
        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => array_map(fn ($c) => Hash::make($c), ['AAAAA-AAAAA', 'BBBBB-BBBBB']),
            'two_factor_confirmed_at' => now(),
        ])->save();
    }
}
