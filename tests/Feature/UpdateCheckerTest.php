<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use App\Services\UpdateChecker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class UpdateCheckerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.github.update_check' => true, 'services.github.repository' => 'NoiXdev/marketix']);
    }

    private function fakeRelease(string $tag): void
    {
        Http::fake([
            'api.github.com/repos/NoiXdev/marketix/releases/latest' => Http::response([
                'tag_name' => $tag,
                'html_url' => "https://github.com/NoiXdev/marketix/releases/tag/{$tag}",
            ]),
        ]);
    }

    public function test_reports_newer_release(): void
    {
        $this->fakeRelease('v999.0.0');

        $this->assertSame([
            'version' => '999.0.0',
            'url' => 'https://github.com/NoiXdev/marketix/releases/tag/v999.0.0',
        ], app(UpdateChecker::class)->availableUpdate());
    }

    public function test_ignores_same_or_older_release(): void
    {
        $this->fakeRelease('v0.0.1');

        $this->assertNull(app(UpdateChecker::class)->availableUpdate());
    }

    public function test_caches_result_between_calls(): void
    {
        $this->fakeRelease('v999.0.0');
        $checker = app(UpdateChecker::class);

        $checker->availableUpdate();
        $checker->availableUpdate();

        Http::assertSentCount(1);
    }

    public function test_caches_failures_to_avoid_repeated_requests(): void
    {
        Http::fake(['api.github.com/*' => Http::response(null, 500)]);
        $checker = app(UpdateChecker::class);

        $this->assertNull($checker->availableUpdate());
        $this->assertNull($checker->availableUpdate());

        Http::assertSentCount(1);
    }

    public function test_skips_request_when_disabled(): void
    {
        config(['services.github.update_check' => false]);
        Http::fake();

        $this->assertNull(app(UpdateChecker::class)->availableUpdate());

        Http::assertNothingSent();
    }

    public function test_shares_update_with_authenticated_users(): void
    {
        $this->fakeRelease('v999.0.0');
        $user = User::factory()->create();
        $project = Project::create(['name' => 'Acme']);
        $user->projects()->attach($project);

        $this->actingAs($user)
            ->get(route('app.project.dashboard', ['project' => $project->id]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('updateAvailable.version', '999.0.0'));
    }

    public function test_does_not_share_update_with_guests(): void
    {
        $this->fakeRelease('v999.0.0');

        $this->get(route('app.auth.show-login'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('updateAvailable', null));

        Http::assertNothingSent();
    }
}
