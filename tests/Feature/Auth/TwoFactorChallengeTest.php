<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Support\TwoFactor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use PragmaRX\Google2FAQRCode\Google2FA;
use Tests\TestCase;

class TwoFactorChallengeTest extends TestCase
{
    use RefreshDatabase;

    private function userWithTwoFactor(): array
    {
        $secret = (new TwoFactor(new Google2FA))->generateSecret();
        $user = User::factory()->create();
        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => [Hash::make('AAAAA-AAAAA')],
            'two_factor_confirmed_at' => now(),
        ])->save();

        return [$user, $secret];
    }

    public function test_login_with_2fa_user_does_not_authenticate_and_redirects_to_challenge(): void
    {
        [$user] = $this->userWithTwoFactor();

        $this->post(route('app.auth.login'), ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('app.auth.two-factor.show'));

        $this->assertGuest();
        $this->assertEquals($user->getKey(), session('auth.2fa.pending_id'));
    }

    public function test_login_without_2fa_authenticates_directly(): void
    {
        $user = User::factory()->create();

        $this->post(route('app.auth.login'), ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect('/');
        $this->assertAuthenticated();
    }

    public function test_challenge_screen_redirects_without_pending_session(): void
    {
        $this->get(route('app.auth.two-factor.show'))->assertRedirect(route('app.auth.show-login'));
    }

    public function test_valid_totp_code_completes_login(): void
    {
        [$user, $secret] = $this->userWithTwoFactor();
        $this->post(route('app.auth.login'), ['email' => $user->email, 'password' => 'password']);

        $code = (new Google2FA)->getCurrentOtp($secret);
        $this->post(route('app.auth.two-factor.store'), ['code' => $code])->assertRedirect('/');

        $this->assertAuthenticatedAs($user);
        $this->assertNull(session('auth.2fa.pending_id'));
    }

    public function test_invalid_totp_code_keeps_guest(): void
    {
        [$user] = $this->userWithTwoFactor();
        $this->post(route('app.auth.login'), ['email' => $user->email, 'password' => 'password']);

        $this->post(route('app.auth.two-factor.store'), ['code' => '000000'])->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_recovery_code_completes_login_and_is_consumed(): void
    {
        [$user] = $this->userWithTwoFactor();
        $this->post(route('app.auth.login'), ['email' => $user->email, 'password' => 'password']);

        $this->post(route('app.auth.two-factor.store'), ['recovery_code' => 'AAAAA-AAAAA'])->assertRedirect('/');
        $this->assertAuthenticatedAs($user);
        $this->assertCount(0, $user->refresh()->two_factor_recovery_codes);
    }

    public function test_two_factor_challenge_is_rate_limited_after_six_attempts(): void
    {
        RateLimiter::clear('throttle:6,1');

        [$user] = $this->userWithTwoFactor();
        $this->post(route('app.auth.login'), ['email' => $user->email, 'password' => 'password']);

        // Submit 6 wrong codes — all should fail with validation errors (not 429)
        for ($i = 1; $i <= 6; $i++) {
            $response = $this->post(route('app.auth.two-factor.store'), ['code' => '000000']);
            $response->assertStatus(302); // redirect back with errors
            $this->assertGuest();
        }

        // 7th attempt must be rate-limited
        $response = $this->post(route('app.auth.two-factor.store'), ['code' => '000000']);
        $response->assertStatus(429);
    }
}
