<?php

namespace Tests\Feature\Dashboards;

use App\Models\Dashboard;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardManageTest extends TestCase
{
    use RefreshDatabase;

    private function member(): array
    {
        $user = User::factory()->create();
        $project = Project::create(['name' => 'Acme']);
        $user->projects()->attach($project);

        return [$user, $project];
    }

    public function test_create_and_update_persists_sanitized_widgets(): void
    {
        [$user, $project] = $this->member();

        $this->actingAs($user)->post(route('app.project.dashboards.store', ['project' => $project->id]), ['name' => 'Campaigns'])->assertRedirect();
        $dashboard = $user->dashboards()->where('project_id', $project->id)->where('name', 'Campaigns')->firstOrFail();

        $this->actingAs($user)->put(route('app.project.dashboards.update', ['project' => $project->id, 'dashboard' => $dashboard->id]), [
            'name' => 'Campaigns',
            'widgets' => [[
                'id' => 'w1', 'type' => 'kpi',
                'config' => ['metric' => 'clicks', 'days' => 30, 'title' => null],
                'layout' => ['x' => 99, 'y' => 0, 'w' => 99, 'h' => 99], // out of bounds -> clamped
            ]],
        ])->assertRedirect();

        $w = $dashboard->fresh()->widgets[0];
        $this->assertLessThanOrEqual(12, $w['layout']['w']);
        $this->assertLessThanOrEqual(12 - $w['layout']['w'], $w['layout']['x']);
        $this->assertLessThanOrEqual(4, $w['layout']['h']); // kpi maxH
    }

    public function test_update_rejects_unknown_widget_type(): void
    {
        [$user, $project] = $this->member();
        $this->actingAs($user)->post(route('app.project.dashboards.store', ['project' => $project->id]), ['name' => 'X'])->assertRedirect();
        $dashboard = $user->dashboards()->where('project_id', $project->id)->where('name', 'X')->firstOrFail();

        $this->actingAs($user)->put(route('app.project.dashboards.update', ['project' => $project->id, 'dashboard' => $dashboard->id]), [
            'name' => 'X',
            'widgets' => [['id' => 'w1', 'type' => 'evil', 'config' => [], 'layout' => ['x' => 0, 'y' => 0, 'w' => 3, 'h' => 2]]],
        ])->assertSessionHasErrors();
    }

    public function test_cannot_touch_another_users_dashboard(): void
    {
        [, $project] = $this->member();
        $other = User::factory()->create();
        $other->projects()->attach($project);
        $foreign = Dashboard::factory()->create(['user_id' => $other->id, 'project_id' => $project->id]);

        $mine = User::factory()->create();
        $mine->projects()->attach($project);

        $this->actingAs($mine)->put(route('app.project.dashboards.update', ['project' => $project->id, 'dashboard' => $foreign->id]), ['name' => 'Hijack', 'widgets' => []])->assertNotFound();
    }
}
