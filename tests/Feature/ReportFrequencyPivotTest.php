<?php

namespace Tests\Feature;

use App\Enums\ProjectRole;
use App\Enums\ReportFrequency;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportFrequencyPivotTest extends TestCase
{
    use RefreshDatabase;

    public function test_pivot_defaults_to_off_and_casts_to_enum(): void
    {
        $project = Project::factory()->create();
        $user = User::factory()->create();
        $project->users()->attach($user, ['role' => ProjectRole::Member->value, 'active' => true]);

        $pivot = $user->projects()->whereKey($project->id)->first()->pivot;

        $this->assertSame(ReportFrequency::Off, $pivot->report_frequency);
    }

    public function test_report_frequency_is_persisted_and_cast_back(): void
    {
        $project = Project::factory()->create();
        $user = User::factory()->create();
        $project->users()->attach($user, ['role' => ProjectRole::Member->value, 'active' => true]);

        $user->projects()->updateExistingPivot($project->id, [
            'report_frequency' => ReportFrequency::Weekly->value,
        ]);

        $pivot = $user->fresh()->projects()->whereKey($project->id)->first()->pivot;
        $this->assertSame(ReportFrequency::Weekly, $pivot->report_frequency);
    }
}
