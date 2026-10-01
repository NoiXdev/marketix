<?php

namespace Tests\Feature\Docs;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class DataPrivacyPageTest extends TestCase
{
    use RefreshDatabase;

    private function projectForMember(): array
    {
        $user = User::factory()->create();
        $project = Project::create(['name' => 'Acme']);
        $user->projects()->attach($project);

        return [$user, $project];
    }

    public function test_guests_are_redirected_to_login(): void
    {
        [, $project] = $this->projectForMember();

        $this->get(route('app.project.docs.privacy', ['project' => $project->id]))
            ->assertRedirect(route('app.auth.show-login'));
    }

    public function test_a_member_can_view_the_data_privacy_page(): void
    {
        [$user, $project] = $this->projectForMember();

        $this->actingAs($user)
            ->get(route('app.project.docs.privacy', ['project' => $project->id]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Docs/DataPrivacy')
                ->where('statsMonths', (int) config('statistics.retention_months'))
                ->where('analyticsMonths', (int) config('analytics.retention_months'))
                ->has('appName')
            );
    }

    public function test_a_non_member_cannot_view_another_projects_page(): void
    {
        [$user] = $this->projectForMember();
        $otherProject = Project::create(['name' => 'Other']);

        $this->actingAs($user)
            ->get(route('app.project.docs.privacy', ['project' => $otherProject->id]))
            ->assertForbidden();
    }
}
