<?php

namespace Tests\Feature;

use App\Enums\ProjectRole;
use App\Enums\ReportFrequency;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class ReportSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_returns_current_cadence_for_the_user(): void
    {
        $project = Project::factory()->create();
        $user = User::factory()->create();
        $project->users()->attach($user, ['role' => ProjectRole::Member->value, 'active' => true, 'report_frequency' => 'weekly']);

        $this->actingAs($user)
            ->get(route('app.project.settings.notifications', ['project' => $project->id]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Settings/Notifications')
                ->where('frequency', 'weekly')
                ->has('options', 4));
    }

    public function test_update_persists_cadence_for_current_user_only(): void
    {
        $project = Project::factory()->create();
        $me = User::factory()->create();
        $other = User::factory()->create();
        $project->users()->attach($me, ['role' => ProjectRole::Member->value, 'active' => true, 'report_frequency' => 'off']);
        $project->users()->attach($other, ['role' => ProjectRole::Member->value, 'active' => true, 'report_frequency' => 'off']);

        $this->actingAs($me)
            ->put(route('app.project.settings.notifications.update', ['project' => $project->id]), ['frequency' => 'monthly'])
            ->assertRedirect();

        $this->assertSame(ReportFrequency::Monthly, $me->fresh()->projects()->whereKey($project->id)->first()->pivot->report_frequency);
        // The other member is untouched.
        $this->assertSame(ReportFrequency::Off, $other->fresh()->projects()->whereKey($project->id)->first()->pivot->report_frequency);
    }

    public function test_update_rejects_invalid_cadence(): void
    {
        $project = Project::factory()->create();
        $user = User::factory()->create();
        $project->users()->attach($user, ['role' => ProjectRole::Member->value, 'active' => true]);

        $this->actingAs($user)
            ->put(route('app.project.settings.notifications.update', ['project' => $project->id]), ['frequency' => 'yearly'])
            ->assertSessionHasErrors('frequency');
    }
}
