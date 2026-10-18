<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class LoginLinkCommandTest extends TestCase
{
    use RefreshDatabase;

    private function runFor(string $identifier): string
    {
        $exit = Artisan::call('marketix:login-link', ['user' => $identifier]);
        $output = Artisan::output();

        $this->assertSame(0, $exit, "Command failed:\n".$output);

        return $output;
    }

    private function tokenFrom(string $output): string
    {
        $this->assertMatchesRegularExpression('#/auth/login-link/[A-Za-z0-9]{64}#', $output);
        preg_match('#/auth/login-link/([A-Za-z0-9]{64})#', $output, $matches);

        return $matches[1];
    }

    public function test_printed_link_logs_in_the_user_identified_by_id(): void
    {
        $user = User::factory()->create();

        $token = $this->tokenFrom($this->runFor((string) $user->getKey()));

        $this->get(route('app.auth.login-link', ['token' => $token]));

        $this->assertAuthenticatedAs($user);
    }

    public function test_user_can_be_identified_by_email(): void
    {
        $user = User::factory()->create(['email' => 'customer@example.com']);

        $token = $this->tokenFrom($this->runFor('customer@example.com'));

        $this->get(route('app.auth.login-link', ['token' => $token]));

        $this->assertAuthenticatedAs($user);
    }

    public function test_link_points_at_the_host_the_route_is_served_on(): void
    {
        $user = User::factory()->create();

        $output = $this->runFor((string) $user->getKey());
        $token = $this->tokenFrom($output);

        $this->assertStringContainsString(
            route('app.auth.login-link', ['token' => $token]),
            $output,
        );
    }

    public function test_output_names_the_user_and_the_validity_window(): void
    {
        $user = User::factory()->create(['email' => 'customer@example.com']);

        $output = $this->runFor((string) $user->getKey());

        $this->assertStringContainsString('customer@example.com', $output);
        $this->assertStringContainsString('5 minutes', $output);
    }

    public function test_fails_for_an_unknown_user(): void
    {
        $this->artisan('marketix:login-link', ['user' => 'nobody@example.com'])
            ->expectsOutputToContain('No user found')
            ->assertFailed();
    }

    public function test_issuing_is_recorded_as_a_security_activity(): void
    {
        $user = User::factory()->create();

        $this->runFor((string) $user->getKey());

        $activity = Activity::where('log_name', 'security')
            ->where('description', 'login_link.issued')
            ->first();

        $this->assertNotNull($activity);
        $this->assertTrue($activity->causer->is($user));
    }

    public function test_no_link_is_issued_when_the_user_is_unknown(): void
    {
        $this->artisan('marketix:login-link', ['user' => '99999'])->assertFailed();

        $this->assertSame(0, Activity::where('description', 'login_link.issued')->count());
    }
}
