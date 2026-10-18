<?php

namespace Tests\Feature\Auth;

use App\Models\Activity;
use App\Models\User;
use App\Support\LoginLink;
use App\Support\TwoFactor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use PragmaRX\Google2FAQRCode\Google2FA;
use Tests\TestCase;

class LoginLinkRouteTest extends TestCase
{
    use RefreshDatabase;

    private function visit(string $token): TestResponse
    {
        return $this->get(route('app.auth.login-link', ['token' => $token]));
    }

    public function test_valid_link_authenticates_the_target_user(): void
    {
        $user = User::factory()->create();

        $this->visit(LoginLink::issue($user))->assertRedirect(route('app.root'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_link_skips_the_two_factor_challenge(): void
    {
        $user = User::factory()->create();
        $user->forceFill([
            'two_factor_secret' => (new TwoFactor(new Google2FA))->generateSecret(),
            'two_factor_recovery_codes' => [Hash::make('AAAAA-AAAAA')],
            'two_factor_confirmed_at' => now(),
        ])->save();

        $this->visit(LoginLink::issue($user))->assertRedirect(route('app.root'));

        $this->assertAuthenticatedAs($user);
        $this->assertNull(session('auth.2fa.pending_id'));
    }

    public function test_link_cannot_be_used_a_second_time(): void
    {
        $user = User::factory()->create();
        $token = LoginLink::issue($user);

        $this->visit($token);
        $this->post(route('app.auth.logout'));

        $this->visit($token)->assertRedirect(route('app.auth.show-login'));
        $this->assertGuest();
    }

    public function test_expired_link_is_rejected(): void
    {
        $user = User::factory()->create();
        $token = LoginLink::issue($user);

        $this->travel(6)->minutes();

        $this->visit($token)->assertRedirect(route('app.auth.show-login'));
        $this->assertGuest();
    }

    public function test_unknown_token_is_rejected_with_a_generic_error(): void
    {
        $this->visit(str_repeat('a', 64))
            ->assertRedirect(route('app.auth.show-login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_link_replaces_an_existing_session(): void
    {
        $support = User::factory()->create();
        $customer = User::factory()->create();

        $this->actingAs($support);

        $this->visit(LoginLink::issue($customer))->assertRedirect(route('app.root'));

        $this->assertAuthenticatedAs($customer);
    }

    public function test_use_is_recorded_as_a_security_activity(): void
    {
        $user = User::factory()->create();

        $this->visit(LoginLink::issue($user));

        $activity = Activity::where('log_name', 'security')
            ->where('description', 'login_link.used')
            ->first();

        $this->assertNotNull($activity);
        $this->assertTrue($activity->causer->is($user));
    }

    public function test_rejected_link_is_not_recorded_as_a_login(): void
    {
        $this->visit(str_repeat('a', 64));

        $this->assertSame(0, Activity::where('description', 'login_link.used')->count());
    }
}
