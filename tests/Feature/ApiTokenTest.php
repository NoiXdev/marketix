<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class ApiTokenTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_a_token_and_plaintext_is_flashed_once(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->post(route('app.profile.tokens.store'), ['name' => 'My CLI Token'])
            ->assertRedirect();

        $response->assertSessionHas('token');
        $plain = $response->getSession()->get('token');
        $this->assertIsString($plain);
        $this->assertNotSame('', $plain);

        $this->assertDatabaseHas('personal_access_tokens', [
            'name' => 'My CLI Token',
            'tokenable_id' => $user->id,
            'tokenable_type' => $user->getMorphClass(),
        ]);
    }

    public function test_store_requires_a_name(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('app.profile.tokens.store'), ['name' => ''])
            ->assertSessionHasErrors('name');

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_user_can_revoke_their_own_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('Revoke Me');

        $this->actingAs($user)
            ->delete(route('app.profile.tokens.destroy', ['token' => $token->accessToken->id]))
            ->assertRedirect();

        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $token->accessToken->id,
        ]);
    }

    public function test_user_cannot_revoke_another_users_token(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $token = $owner->createToken('Owned By Someone Else');

        $this->actingAs($other)
            ->delete(route('app.profile.tokens.destroy', ['token' => $token->accessToken->id]))
            ->assertNotFound();

        $this->assertDatabaseHas('personal_access_tokens', [
            'id' => $token->accessToken->id,
        ]);
    }

    public function test_token_hash_is_never_exposed_in_response(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->post(route('app.profile.tokens.store'), ['name' => 'Check Exposure']);

        $stored = PersonalAccessToken::query()->where('tokenable_id', $user->id)->first();

        $response->assertSessionMissing('tokens');
        $this->assertStringNotContainsString($stored->token, $response->getSession()->get('token'));
    }
}
