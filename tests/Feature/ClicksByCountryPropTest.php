<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Statistic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ClicksByCountryPropTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_statistics_includes_clicks_by_country(): void
    {
        // Arrange: a user who belongs to a project with a DE click.
        $user = User::factory()->create();
        $project = Project::create(['name' => 'Acme']);
        $user->projects()->attach($project);
        Statistic::factory()->forProject($project)
            ->state(['country' => 'Germany', 'country_code' => 'DE'])->create();

        $this->actingAs($user)
            ->get(route('app.project.statistics', ['project' => $project->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Statistics/Index')
                ->has('clicksByCountry', 1, fn (Assert $row) => $row
                    ->where('country_code', 'DE')
                    ->where('country', 'Germany')
                    ->where('count', 1)
                )
            );
    }
}
