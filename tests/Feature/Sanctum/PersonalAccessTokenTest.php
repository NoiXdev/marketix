<?php

namespace Tests\Feature\Sanctum;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PersonalAccessTokenTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_create_and_authenticate_with_a_token(): void
    {
        $user = User::factory()->create();

        $token = $user->createToken('cli')->plainTextToken;

        $this->assertNotEmpty($token);
        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'name' => 'cli',
        ]);
        $this->assertTrue($user->tokens()->count() === 1);
    }
}
