<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Laravel\Passkeys\Contracts\PasskeyUser;
use Tests\TestCase;

class PasskeyWiringTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_implements_passkey_contract_and_relation(): void
    {
        $user = User::factory()->create();

        $this->assertInstanceOf(PasskeyUser::class, $user);
        $this->assertFalse($user->hasPasskeysEnabled());
        $this->assertCount(0, $user->passkeys()->get());
    }

    public function test_package_routes_are_registered(): void
    {
        foreach (['passkey.login-options', 'passkey.login', 'passkey.registration-options', 'passkey.store', 'passkey.destroy'] as $name) {
            $this->assertTrue(Route::has($name), "Missing route: {$name}");
        }
    }

    public function test_passkeys_table_uses_ulid_user_foreign_key(): void
    {
        $user = User::factory()->create();

        // Inserting a passkey row keyed by the ULID user id must succeed.
        $user->passkeys()->create([
            'name' => 'Test Key',
            'credential_id' => 'cred-'.$user->id,
            'credential' => ['aaguid' => '00000000-0000-0000-0000-000000000000'],
        ]);

        $this->assertTrue($user->fresh()->hasPasskeysEnabled());
    }

    public function test_management_middleware_is_relaxed(): void
    {
        $this->assertSame([], config('passkeys.management_middleware'));
    }

    public function test_challenge_passkey_options_requires_pending_session(): void
    {
        $this->getJson(route('app.auth.two-factor.passkey-options'))->assertStatus(409);
    }

    public function test_rename_updates_owned_passkey(): void
    {
        $user = User::factory()->create();
        $passkey = $user->passkeys()->create([
            'name' => 'Old',
            'credential_id' => 'cred-rename',
            'credential' => ['aaguid' => '00000000-0000-0000-0000-000000000000'],
        ]);

        $this->actingAs($user)
            ->patch(route('app.passkeys.rename', ['passkey' => $passkey->id]), ['name' => 'New Name'])
            ->assertRedirect();

        $this->assertSame('New Name', $passkey->fresh()->name);
    }

    public function test_rename_forbidden_for_other_users_passkey(): void
    {
        $owner = User::factory()->create();
        $passkey = $owner->passkeys()->create([
            'name' => 'Theirs',
            'credential_id' => 'cred-other',
            'credential' => ['aaguid' => '00000000-0000-0000-0000-000000000000'],
        ]);

        $this->actingAs(User::factory()->create())
            ->patch(route('app.passkeys.rename', ['passkey' => $passkey->id]), ['name' => 'Hijack'])
            ->assertForbidden();
    }
}
