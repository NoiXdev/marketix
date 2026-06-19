<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CreateAdminCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_super_admin_from_options(): void
    {
        $this->artisan('marketix:create-admin', [
            '--name' => 'Ada Admin',
            '--email' => 'ada@example.com',
            '--password' => 'secret-password',
        ])->assertSuccessful();

        $user = User::where('email', 'ada@example.com')->first();

        $this->assertNotNull($user);
        $this->assertSame('Ada Admin', $user->name);
        $this->assertTrue($user->super_admin);
        $this->assertFalse($user->force_password_change);
    }

    public function test_password_is_hashed_not_stored_plaintext(): void
    {
        $this->artisan('marketix:create-admin', [
            '--name' => 'Ada Admin',
            '--email' => 'ada@example.com',
            '--password' => 'secret-password',
        ])->assertSuccessful();

        $user = User::where('email', 'ada@example.com')->first();

        $this->assertNotSame('secret-password', $user->password);
        $this->assertTrue(Hash::check('secret-password', $user->password));
    }

    public function test_prompts_for_missing_values(): void
    {
        $this->artisan('marketix:create-admin')
            ->expectsQuestion('Name', 'Ada Admin')
            ->expectsQuestion('Email', 'ada@example.com')
            ->expectsQuestion('Password', 'secret-password')
            ->expectsQuestion('Confirm password', 'secret-password')
            ->assertSuccessful();

        $user = User::where('email', 'ada@example.com')->first();

        $this->assertNotNull($user);
        $this->assertTrue($user->super_admin);
    }

    public function test_fails_when_password_confirmation_does_not_match(): void
    {
        $this->artisan('marketix:create-admin', [
            '--name' => 'Ada Admin',
            '--email' => 'ada@example.com',
        ])
            ->expectsQuestion('Password', 'secret-password')
            ->expectsQuestion('Confirm password', 'different-password')
            ->assertFailed();

        $this->assertDatabaseMissing('users', ['email' => 'ada@example.com']);
    }

    public function test_promotes_existing_user_to_super_admin(): void
    {
        $existing = User::factory()->create([
            'email' => 'member@example.com',
            'password' => Hash::make('original-password'),
        ]);
        $this->assertFalse($existing->fresh()->super_admin);

        $this->artisan('marketix:create-admin', [
            '--name' => 'Ignored Name',
            '--email' => 'member@example.com',
            '--password' => 'ignored-password',
            '--force' => true,
        ])->assertSuccessful();

        $existing->refresh();

        $this->assertTrue($existing->super_admin);
        // Promotion grants a role; it must not reset the existing password.
        $this->assertTrue(Hash::check('original-password', $existing->password));
        $this->assertSame(1, User::where('email', 'member@example.com')->count());
    }

    public function test_no_op_when_user_is_already_super_admin(): void
    {
        $admin = User::factory()->create(['email' => 'boss@example.com']);
        $admin->super_admin = true;
        $admin->save();

        $this->artisan('marketix:create-admin', [
            '--name' => 'Ignored Name',
            '--email' => 'boss@example.com',
            '--password' => 'ignored-password',
            '--force' => true,
        ])->assertSuccessful();

        $this->assertSame(1, User::where('email', 'boss@example.com')->count());
    }

    public function test_rejects_invalid_email(): void
    {
        $this->artisan('marketix:create-admin', [
            '--name' => 'Ada Admin',
            '--email' => 'not-an-email',
            '--password' => 'secret-password',
        ])->assertFailed();

        $this->assertSame(0, User::count());
    }

    public function test_rejects_short_password(): void
    {
        $this->artisan('marketix:create-admin', [
            '--name' => 'Ada Admin',
            '--email' => 'ada@example.com',
            '--password' => 'short',
        ])->assertFailed();

        $this->assertDatabaseMissing('users', ['email' => 'ada@example.com']);
    }
}
