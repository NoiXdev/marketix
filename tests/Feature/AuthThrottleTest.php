<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AuthThrottleTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_is_throttled_after_five_failed_attempts(): void
    {
        User::factory()->create(['email' => 'user@test.dev', 'password' => Hash::make('correct-horse')]);

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('app.auth.login'), ['email' => 'user@test.dev', 'password' => 'wrong']);
        }

        $response = $this->post(route('app.auth.login'), ['email' => 'user@test.dev', 'password' => 'wrong']);

        $response->assertSessionHasErrors('email');
        $this->assertStringContainsString('Too many', session('errors')->first('email'));
    }

    public function test_successful_login_clears_the_rate_limiter(): void
    {
        User::factory()->create(['email' => 'user@test.dev', 'password' => Hash::make('correct-horse')]);

        $this->post(route('app.auth.login'), ['email' => 'user@test.dev', 'password' => 'wrong']);
        $this->post(route('app.auth.login'), ['email' => 'user@test.dev', 'password' => 'wrong']);

        $this->post(route('app.auth.login'), ['email' => 'user@test.dev', 'password' => 'correct-horse'])
            ->assertRedirect('/');

        // Key must match AuthController::throttleKey (lower(email) . '|' . ip).
        $key = 'user@test.dev|127.0.0.1';
        $this->assertSame(0, RateLimiter::attempts($key));
    }

    public function test_forgot_password_is_throttled_per_minute(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post(route('app.auth.forgot'), ['email' => 'user@test.dev']);
        }

        $this->post(route('app.auth.forgot'), ['email' => 'user@test.dev'])
            ->assertStatus(429);
    }
}
