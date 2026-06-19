<?php

namespace Tests\Feature\ActivityLog;

use App\Enums\ProjectRole;
use App\Jobs\RegenerateTraefikConfigJob;
use App\Models\Project;
use App\Models\Url;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ResourceHistoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Creating a Domain dispatches RegenerateTraefikConfigJob, which writes
        // to a host path absent in CI. Fake only that job so it never runs.
        Queue::fake([RegenerateTraefikConfigJob::class]);
    }

    public function test_url_edit_history_partial_returns_only_that_subjects_activity(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create();
        $project->users()->attach($user, ['role' => ProjectRole::Admin->value, 'active' => true]);

        $url = Url::factory()->for($project)->create(['slug' => 'a']);
        $other = Url::factory()->for($project)->create(['slug' => 'b']);
        $url->update(['slug' => 'a2']);
        $other->update(['slug' => 'b2']);

        // Resolve the current asset version the same way Inertia middleware does.
        $version = file_exists($manifest = public_path('build/manifest.json'))
            ? hash_file('xxh128', $manifest)
            : '';

        // Partial reload requesting only the lazy history prop.
        $response = $this->actingAs($user)
            ->get(route('app.project.links.edit', ['project' => $project->id, 'url' => $url->id]), [
                'X-Inertia' => true,
                'X-Inertia-Partial-Data' => 'history',
                'X-Inertia-Partial-Component' => 'Links/Edit',
                'X-Inertia-Version' => $version,
            ])
            ->assertOk();

        $history = $response->json('props.history');

        $this->assertNotNull($history);
        $this->assertNotEmpty($history, 'Expected at least one history entry for the target URL');
        $this->assertTrue(
            collect($history)->every(fn ($a) => $a['subject_type'] === 'Url'),
            'Expected all history entries to have subject_type === Url'
        );
    }
}
