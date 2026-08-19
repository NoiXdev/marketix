<?php

namespace Tests\Feature\Demo;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/**
 * The demo account is a super admin by design (DemoSeeder), and no
 * horizon.* route was ever added to DemoGuard::BLOCKED_ROUTES — so without
 * this, any demo visitor could open /horizon and get the queue console:
 * job payloads, the failed-jobs list, retry (a second route around
 * DemoCrawlQuota via a stored RunCrawlJob), and monitoring writes.
 *
 * The fix tightens HorizonServiceProvider::gate() to deny on demo
 * instances rather than enumerating horizon.* routes in the deny list.
 */
class DemoHorizonGateTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        $user = User::factory()->create();
        $user->super_admin = true;
        $user->save();

        return $user;
    }

    public function test_a_demo_super_admin_is_denied_the_horizon_gate(): void
    {
        config(['demo.enabled' => true]);

        $this->assertFalse(Gate::forUser($this->superAdmin())->allows('viewHorizon'));
    }

    public function test_a_normal_super_admin_passes_the_horizon_gate(): void
    {
        config(['demo.enabled' => false]);

        $this->assertTrue(Gate::forUser($this->superAdmin())->allows('viewHorizon'));
    }

    public function test_a_non_super_admin_is_denied_the_horizon_gate_regardless_of_demo_mode(): void
    {
        config(['demo.enabled' => false]);

        $this->assertFalse(Gate::forUser(User::factory()->create())->allows('viewHorizon'));
    }
}
