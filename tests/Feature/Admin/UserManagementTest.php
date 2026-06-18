<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        $u = User::factory()->create();
        $u->super_admin = true;
        $u->save();

        return $u;
    }

    public function test_non_super_admin_is_forbidden(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('app.admin.users.index'))
            ->assertForbidden();
    }

    public function test_super_admin_sees_user_list(): void
    {
        $admin = $this->superAdmin();
        User::factory()->count(2)->create();

        $this->actingAs($admin)
            ->get(route('app.admin.users.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('Admin/Users/Index')->has('users.data'));
    }

    public function test_can_create_user_with_super_admin_flag(): void
    {
        $admin = $this->superAdmin();

        $this->actingAs($admin)->post(route('app.admin.users.store'), [
            'name' => 'Created',
            'email' => 'created@example.com',
            'password' => 'password123',
            'super_admin' => true,
        ])->assertRedirect(route('app.admin.users.index'));

        $user = User::where('email', 'created@example.com')->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->super_admin);
    }

    public function test_can_toggle_super_admin_on_update(): void
    {
        $admin = $this->superAdmin();
        $target = User::factory()->create();

        $this->actingAs($admin)->put(route('app.admin.users.update', ['user' => $target->id]), [
            'name' => $target->name,
            'email' => $target->email,
            'super_admin' => true,
        ])->assertRedirect(route('app.admin.users.index'));

        $this->assertTrue($target->fresh()->super_admin);
    }

    public function test_cannot_remove_own_super_admin(): void
    {
        $admin = $this->superAdmin();

        $this->actingAs($admin)->put(route('app.admin.users.update', ['user' => $admin->id]), [
            'name' => $admin->name,
            'email' => $admin->email,
            'super_admin' => false,
        ])->assertSessionHas('error');

        $this->assertTrue($admin->fresh()->super_admin);
    }

    public function test_cannot_delete_self(): void
    {
        $admin = $this->superAdmin();

        $this->actingAs($admin)->delete(route('app.admin.users.destroy', ['user' => $admin->id]))
            ->assertSessionHas('error');

        $this->assertNotNull($admin->fresh());
    }

    public function test_can_delete_other_user(): void
    {
        $admin = $this->superAdmin();
        $target = User::factory()->create();

        $this->actingAs($admin)->delete(route('app.admin.users.destroy', ['user' => $target->id]))
            ->assertRedirect(route('app.admin.users.index'));

        $this->assertNull(User::find($target->id));
    }

    public function test_update_sets_force_password_change_flag(): void
    {
        $admin = $this->superAdmin();
        $target = User::factory()->create();

        $this->actingAs($admin)
            ->put(route('app.admin.users.update', ['user' => $target->id]), [
                'name' => $target->name,
                'email' => $target->email,
                'force_password_change' => true,
            ])
            ->assertRedirect(route('app.admin.users.index'));

        $this->assertTrue($target->fresh()->force_password_change);
    }

    public function test_edit_page_exposes_force_password_change(): void
    {
        $admin = $this->superAdmin();
        $target = User::factory()->create();
        $target->force_password_change = true;
        $target->save();

        $this->actingAs($admin)
            ->get(route('app.admin.users.edit', ['user' => $target->id]))
            ->assertOk()
            ->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
                ->component('Admin/Users/Edit')
                ->where('user.force_password_change', true));
    }
}
