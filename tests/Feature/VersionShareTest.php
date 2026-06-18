<?php

namespace Tests\Feature;

use App\Jobs\RegenerateTraefikConfigJob;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class VersionShareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake([RegenerateTraefikConfigJob::class]);
    }

    public function test_inertia_shares_version_from_package_json(): void
    {
        $expected = json_decode(file_get_contents(base_path('package.json')))->version;

        $user = User::factory()->create();
        $project = Project::create(['name' => 'Acme']);
        $user->projects()->attach($project);

        $this->actingAs($user)
            ->get(route('app.project.dashboard', ['project' => $project->id]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('version', $expected));
    }
}
