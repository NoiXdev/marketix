<?php

// tests/Feature/Dashboards/ResolveDefaultDashboardTest.php

namespace Tests\Feature\Dashboards;

use App\Actions\ResolveDefaultDashboard;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResolveDefaultDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_a_default_once_then_reuses_it(): void
    {
        $user = User::factory()->create();
        $project = Project::create(['name' => 'Acme']);
        $action = app(ResolveDefaultDashboard::class);

        $first = $action($user, $project);
        $this->assertTrue($first->is_default);
        $this->assertSame('Overview', $first->name);
        $this->assertNotEmpty($first->widgets);
        $this->assertSame('kpi', $first->widgets[0]['type']);

        $second = $action($user, $project);
        $this->assertTrue($first->is($second));
        $this->assertSame(1, $user->dashboards()->where('project_id', $project->id)->count());
    }
}
