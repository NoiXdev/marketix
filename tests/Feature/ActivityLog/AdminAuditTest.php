<?php

namespace Tests\Feature\ActivityLog;

use App\Models\User;
use App\Support\ActivityRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_is_forbidden(): void
    {
        $user = User::factory()->create(['super_admin' => false]);

        $this->actingAs($user)->get(route('app.admin.activity.index'))->assertForbidden();
    }

    public function test_admin_sees_security_events(): void
    {
        $admin = User::factory()->create(['super_admin' => true]);
        ActivityRecorder::security('login', $admin);

        $this->actingAs($admin)
            ->get(route('app.admin.activity.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Activity/Index')
                ->where('activities.data', fn ($data) => collect($data)->contains(fn ($a) => $a['log_name'] === 'security'))
            );
    }
}
