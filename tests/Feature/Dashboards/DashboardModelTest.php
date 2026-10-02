<?php

namespace Tests\Feature\Dashboards;

use App\Models\Dashboard;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_persists_widgets_as_array_and_relates(): void
    {
        $user = User::factory()->create();
        $project = Project::create(['name' => 'Acme']);

        $dashboard = Dashboard::create([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'name' => 'Overview',
            'is_default' => true,
            'position' => 0,
            'widgets' => [['id' => 'w1', 'type' => 'kpi', 'config' => ['metric' => 'clicks', 'days' => 30], 'layout' => ['x' => 0, 'y' => 0, 'w' => 3, 'h' => 2]]],
        ]);

        $fresh = $dashboard->fresh();
        $this->assertIsArray($fresh->widgets);
        $this->assertSame('kpi', $fresh->widgets[0]['type']);
        $this->assertTrue($fresh->is_default);
        $this->assertTrue($user->dashboards()->whereKey($dashboard->id)->exists());
        $this->assertTrue($project->dashboards()->whereKey($dashboard->id)->exists());
    }
}
