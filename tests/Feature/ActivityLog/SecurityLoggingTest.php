<?php

namespace Tests\Feature\ActivityLog;

use App\Models\Activity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SecurityLoggingTest extends TestCase
{
    use RefreshDatabase;

    public function test_successful_login_is_logged_with_null_project(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password123')]);

        $this->post(route('app.auth.login'), ['email' => $user->email, 'password' => 'password123']);

        $activity = Activity::query()->where('log_name', 'security')->where('description', 'login')->latest('id')->first();
        $this->assertNotNull($activity);
        $this->assertNull($activity->project_id);
        $this->assertTrue($activity->causer->is($user));
    }

    public function test_password_change_is_logged(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put(route('app.profile.update'), [
            'current_password' => 'password',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ]);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'security',
            'description' => 'password_changed',
            'causer_id' => $user->id,
        ]);
    }
}
