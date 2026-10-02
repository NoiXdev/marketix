<?php

namespace Tests\Feature\Dashboards;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WidgetDataTest extends TestCase
{
    use RefreshDatabase;

    private function member(): array
    {
        $user = User::factory()->create();
        $project = Project::create(['name' => 'Acme']);
        $user->projects()->attach($project);

        return [$user, $project];
    }

    public function test_kpi_data_endpoint_returns_payload(): void
    {
        [$user, $project] = $this->member();

        $this->actingAs($user)
            ->getJson(route('app.project.widgets.data', ['project' => $project->id, 'type' => 'kpi', 'metric' => 'clicks', 'days' => 30]))
            ->assertOk()
            ->assertJsonStructure(['value', 'deltaPct']);
    }

    public function test_rejects_unknown_type(): void
    {
        [$user, $project] = $this->member();
        $this->actingAs($user)
            ->getJson(route('app.project.widgets.data', ['project' => $project->id, 'type' => 'evil']))
            ->assertStatus(422);
    }

    public function test_rejects_bad_dimension(): void
    {
        [$user, $project] = $this->member();
        $this->actingAs($user)
            ->getJson(route('app.project.widgets.data', ['project' => $project->id, 'type' => 'top_list', 'dimension' => 'secrets', 'limit' => 5, 'days' => 30]))
            ->assertStatus(422);
    }
}
