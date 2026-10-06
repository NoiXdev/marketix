<?php

namespace Tests\Feature;

use App\Models\Goal;
use App\Models\Project;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class GoalCrudTest extends TestCase
{
    use RefreshDatabase;

    private function ctx(): array
    {
        $user = User::factory()->create();
        $project = Project::create(['name' => 'Acme']);
        $user->projects()->attach($project);
        $site = Site::factory()->forProject($project)->create();

        return [$user, $project, $site];
    }

    public function test_index_lists_site_goals(): void
    {
        [$user, $project, $site] = $this->ctx();
        Goal::factory()->forSite($site)->create(['name' => 'Signup']);

        $this->actingAs($user)
            ->get(route('app.project.analytics.goals.index', ['project' => $project->id, 'site' => $site->id]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p->component('Goals/Index')->has('goals', 1));
    }

    public function test_store_creates_a_goal_and_prefixes_pageview_path(): void
    {
        [$user, $project, $site] = $this->ctx();

        $this->actingAs($user)
            ->post(route('app.project.analytics.goals.store', ['project' => $project->id, 'site' => $site->id]), [
                'name' => 'Thank you',
                'type' => 'pageview',
                'match_value' => 'danke', // no leading slash
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('goals', ['site_id' => $site->id, 'type' => 'pageview', 'match_value' => '/danke']);
    }

    public function test_store_saves_event_conditions_and_value(): void
    {
        [$user, $project, $site] = $this->ctx();

        $this->actingAs($user)
            ->post(route('app.project.analytics.goals.store', ['project' => $project->id, 'site' => $site->id]), [
                'name' => 'Pro signup',
                'type' => 'event',
                'match_value' => 'signup',
                'conditions' => [['property' => 'plan', 'value' => 'pro'], ['property' => '', 'value' => '']],
                'value' => '49.90',
                'currency' => 'chf',
            ])
            ->assertSessionHasNoErrors();

        $goal = Goal::firstOrFail();
        $this->assertSame([['property' => 'plan', 'value' => 'pro']], $goal->conditions); // blank row dropped
        $this->assertSame(49.9, $goal->value);
        $this->assertSame('CHF', $goal->currency);
    }

    public function test_pageview_goals_drop_conditions_and_currency_needs_a_value(): void
    {
        [$user, $project, $site] = $this->ctx();

        $this->actingAs($user)
            ->post(route('app.project.analytics.goals.store', ['project' => $project->id, 'site' => $site->id]), [
                'name' => 'Thanks', 'type' => 'pageview', 'match_value' => '/danke',
                'conditions' => [['property' => 'plan', 'value' => 'pro']],
                'value' => '', 'currency' => 'CHF',
            ])
            ->assertSessionHasNoErrors();

        $goal = Goal::firstOrFail();
        $this->assertNull($goal->conditions);
        $this->assertNull($goal->value);
        $this->assertNull($goal->currency);
    }

    public function test_invalid_conditions_and_missing_currency_are_rejected(): void
    {
        [$user, $project, $site] = $this->ctx();

        $this->actingAs($user)
            ->post(route('app.project.analytics.goals.store', ['project' => $project->id, 'site' => $site->id]), [
                'name' => 'Bad', 'type' => 'event', 'match_value' => 'signup',
                'conditions' => [['property' => 'plan"]', 'value' => 'pro'], ['property' => 'tier', 'value' => '']],
                'value' => '10',
            ])
            ->assertSessionHasErrors(['conditions.0.property', 'conditions.1.value', 'currency']);

        $this->assertSame(0, Goal::count());
    }

    public function test_update_and_destroy(): void
    {
        [$user, $project, $site] = $this->ctx();
        $goal = Goal::factory()->forSite($site)->create();

        $this->actingAs($user)->put(route('app.project.analytics.goals.update', ['project' => $project->id, 'site' => $site->id, 'goal' => $goal->id]), [
            'name' => 'Renamed', 'type' => 'event', 'match_value' => 'purchase',
        ])->assertRedirect();
        $this->assertDatabaseHas('goals', ['id' => $goal->id, 'name' => 'Renamed', 'match_value' => 'purchase']);

        $this->actingAs($user)->delete(route('app.project.analytics.goals.destroy', ['project' => $project->id, 'site' => $site->id, 'goal' => $goal->id]))->assertRedirect();
        $this->assertSoftDeleted('goals', ['id' => $goal->id]);
    }

    public function test_foreign_site_goal_is_not_found(): void
    {
        [$user, $project, $site] = $this->ctx();
        $foreignGoal = Goal::factory()->create(); // different site/project

        $this->actingAs($user)
            ->get(route('app.project.analytics.goals.edit', ['project' => $project->id, 'site' => $site->id, 'goal' => $foreignGoal->id]))
            ->assertNotFound();
    }

    public function test_guests_redirected_to_login(): void
    {
        [, $project, $site] = $this->ctx();
        $this->get(route('app.project.analytics.goals.index', ['project' => $project->id, 'site' => $site->id]))
            ->assertRedirect(route('app.auth.show-login'));
    }
}
