<?php

namespace Tests\Feature;

use App\Actions\MoveToProject;
use App\Models\Activity;
use App\Models\Annotation;
use App\Models\Project;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class AnnotationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Project $project;

    private Site $site;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['name' => 'Jane Doe']);
        $this->project = Project::create(['name' => 'Acme']);
        $this->user->projects()->attach($this->project);
        $this->site = Site::factory()->forProject($this->project)->create();
    }

    private function params(array $extra = []): array
    {
        return ['project' => $this->project->id, 'site' => $this->site->id, ...$extra];
    }

    public function test_store_creates_an_annotation_by_the_current_user_and_returns_to_the_dashboard(): void
    {
        $dashboard = route('app.project.analytics.show', $this->params()).'?range=90d';

        $this->actingAs($this->user)
            ->from($dashboard)
            ->post(route('app.project.analytics.annotations.store', $this->params()), [
                'date' => '2026-10-10',
                'title' => 'Tracking switched from cookie to hybrid',
                'description' => 'Consent banner replaced',
            ])
            ->assertRedirect($dashboard)
            ->assertSessionHas('success');

        $annotation = Annotation::firstOrFail();
        $this->assertSame($this->site->id, $annotation->site_id);
        $this->assertSame($this->project->id, $annotation->project_id);
        $this->assertSame($this->user->id, $annotation->user_id);
        $this->assertSame('2026-10-10', $annotation->date->toDateString());
        $this->assertSame('annotation', Activity::query()->where('subject_id', $annotation->id)->firstOrFail()->log_name);
    }

    public function test_store_validates_the_input(): void
    {
        $this->actingAs($this->user)
            ->post(route('app.project.analytics.annotations.store', $this->params()), [
                'date' => '10.10.2026',
                'title' => str_repeat('x', Annotation::TITLE_MAX + 1),
            ])
            ->assertSessionHasErrors(['date', 'title']);

        $this->assertSame(0, Annotation::count());
    }

    public function test_update_and_destroy(): void
    {
        $annotation = Annotation::factory()->forSite($this->site)->create();

        $this->actingAs($this->user)
            ->put(route('app.project.analytics.annotations.update', $this->params(['annotation' => $annotation->id])), [
                'date' => '2026-09-01',
                'title' => 'Relaunch v2',
                'description' => null,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('Relaunch v2', $annotation->fresh()->title);
        $this->assertSame('2026-09-01', $annotation->fresh()->date->toDateString());

        $this->actingAs($this->user)
            ->delete(route('app.project.analytics.annotations.destroy', $this->params(['annotation' => $annotation->id])))
            ->assertSessionHas('success');

        $this->assertSoftDeleted($annotation);
    }

    public function test_annotations_of_other_sites_cannot_be_changed(): void
    {
        $other = Annotation::factory()->forSite(Site::factory()->forProject($this->project)->create())->create();

        $this->actingAs($this->user)
            ->delete(route('app.project.analytics.annotations.destroy', $this->params(['annotation' => $other->id])))
            ->assertNotFound();

        $foreignSite = Site::factory()->create();

        $this->actingAs($this->user)
            ->post(route('app.project.analytics.annotations.store', ['project' => $this->project->id, 'site' => $foreignSite->id]), [
                'date' => '2026-10-10',
                'title' => 'Sneaky',
            ])
            ->assertNotFound();

        $this->assertNotSoftDeleted($other);
        $this->assertSame(0, $foreignSite->annotations()->count());
    }

    public function test_dashboard_lists_the_sites_annotations_by_date(): void
    {
        Annotation::factory()->forSite($this->site)->create(['date' => '2026-10-10', 'title' => 'Hybrid tracking', 'user_id' => $this->user->id]);
        Annotation::factory()->forSite($this->site)->create(['date' => '2026-08-01', 'title' => 'Relaunch']);
        Annotation::factory()->forSite(Site::factory()->forProject($this->project)->create())->create(['title' => 'Elsewhere']);

        $this->actingAs($this->user)
            ->get(route('app.project.analytics.show', $this->params()))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('annotations', 2)
                ->where('annotations.0.title', 'Relaunch')
                ->where('annotations.0.author', null)
                ->where('annotations.1.date', '2026-10-10')
                ->where('annotations.1.author', 'Jane Doe')
            );
    }

    public function test_before_and_after_comparison_on_the_dashboard(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 22)->setTime(12, 0));

        $this->actingAs($this->user)
            ->get(route('app.project.analytics.show', $this->params()).'?range=custom&from=2026-10-10&to=2026-10-22&compare=custom&compare_to=2026-10-09')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('period.compare', 'custom')
                ->where('period.compare_from', '2026-09-27')
                ->where('period.compare_to', '2026-10-09')
                ->where('period.compare_days', 13)
                ->has('timeseries', 13)
                ->has('previousTimeseries', 13)
                ->where('previousTimeseries.0.date', '2026-09-27')
            );
    }

    public function test_annotations_move_with_their_site(): void
    {
        $annotation = Annotation::factory()->forSite($this->site)->create();
        $target = Project::create(['name' => 'Target']);

        app(MoveToProject::class)->site($this->site, $target, $this->user);

        $this->assertSame($target->id, $annotation->fresh()->project_id);
    }
}
