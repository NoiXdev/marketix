<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ForcePasswordChangeFlagTest extends TestCase
{
    use RefreshDatabase;

    public function test_force_password_change_defaults_false_and_casts_to_bool(): void
    {
        $user = User::factory()->create();

        $this->assertFalse($user->force_password_change);

        $user->force_password_change = 1;
        $user->save();

        $this->assertTrue($user->fresh()->force_password_change);
    }
}
