<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Funnel;
use App\Models\PageView;
use App\Models\Project;
use App\Models\Site;
use App\Models\User;
use App\Models\Visit;
use App\Services\FunnelAggregator;
use App\Support\Analytics\AnalyticsQuery;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class FunnelTest extends TestCase
{
    use RefreshDatabase;

    private Site $site;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-07 15:30:00'));
        $this->site = Site::factory()->create();
    }

    private function visitWith(array $steps, array $visit = []): Visit
    {
        $record = Visit::factory()->forSite($this->site)->create($visit + ['pageview_count' => count($steps)]);
        $at = CarbonImmutable::now()->subHours(2);

        foreach ($steps as $step) {
            $at = $at->addMinute();
            if (str_starts_with($step, '/')) {
                PageView::factory()->forVisit($record)->create(['path' => $step, 'created_at' => $at, 'country_code' => $record->country_code]);
            } else {
                Event::factory()->forVisit($record)->create(['name' => $step, 'created_at' => $at]);
            }
        }

        return $record;
    }

    private function checkout(): Funnel
    {
        return Funnel::factory()->forSite($this->site)->create([
            'steps' => [
                ['type' => 'pageview', 'value' => '/products/*', 'label' => 'Product'],
                ['type' => 'pageview', 'value' => '/cart', 'label' => null],
                ['type' => 'event', 'value' => 'purchase', 'label' => 'Purchase'],
            ],
        ]);
    }

    public function test_report_counts_sessions_reaching_each_step_in_order(): void
    {
        $this->visitWith(['/products/a', '/cart', 'purchase']);
        $this->visitWith(['/products/b', '/about', '/cart']);
        $this->visitWith(['/products/c']);
        $this->visitWith(['/cart', '/products/d', 'purchase']);
        $this->visitWith(['/home']);

        $report = (new FunnelAggregator)->report($this->checkout(), 7);

        $this->assertSame([4, 2, 1], array_column($report['steps'], 'sessions'));
        $this->assertSame(4, $report['entered']);
        $this->assertSame(1, $report['completed']);
        $this->assertSame(25.0, $report['conversion_rate']);
        $this->assertSame([100.0, 50.0, 25.0], array_column($report['steps'], 'rate'));
        $this->assertSame([null, 50.0, 50.0], array_column($report['steps'], 'step_rate'));
        $this->assertSame([0, 2, 1], array_column($report['steps'], 'drop_off'));
        $this->assertSame('Product', $report['steps'][0]['label']);
    }

    public function test_report_respects_dashboard_filters(): void
    {
        $this->visitWith(['/products/a', '/cart', 'purchase'], ['country_code' => 'CH']);
        $this->visitWith(['/products/b', '/cart', 'purchase'], ['country_code' => 'DE']);

        $report = (new FunnelAggregator)->report($this->checkout(), AnalyticsQuery::lastDays(7, ['country_code' => 'CH']));

        $this->assertSame([1, 1, 1], array_column($report['steps'], 'sessions'));
    }

    public function test_empty_funnel_report(): void
    {
        $report = (new FunnelAggregator)->report($this->checkout(), 7);

        $this->assertSame(0, $report['entered']);
        $this->assertSame(0.0, $report['conversion_rate']);
        $this->assertSame([0.0, 0.0, 0.0], array_column($report['steps'], 'rate'));
    }

    public function test_funnels_can_be_created_updated_and_deleted(): void
    {
        $user = User::factory()->create();
        $project = Project::find($this->site->project_id);
        $user->projects()->attach($project);
        $params = ['project' => $project->id, 'site' => $this->site->id];
        $conversions = route('app.project.analytics.show', $params + ['tab' => 'conversions']);

        $this->actingAs($user)
            ->post(route('app.project.analytics.funnels.store', $params), [
                'name' => 'Signup',
                'steps' => [
                    ['type' => 'pageview', 'value' => 'pricing', 'label' => ' '],
                    ['type' => 'event', 'value' => ' signup ', 'label' => 'Signed up'],
                ],
            ])
            ->assertRedirect($conversions);

        $funnel = Funnel::sole();
        $this->assertSame([
            ['type' => 'pageview', 'value' => '/pricing', 'label' => null],
            ['type' => 'event', 'value' => 'signup', 'label' => 'Signed up'],
        ], $funnel->steps);

        $this->actingAs($user)
            ->get(route('app.project.analytics.funnels.edit', $params + ['funnel' => $funnel->id]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('Funnels/Edit')->where('funnel.name', 'Signup')->where('limits.max', Funnel::MAX_STEPS));

        $this->actingAs($user)
            ->put(route('app.project.analytics.funnels.update', $params + ['funnel' => $funnel->id]), [
                'name' => 'Signup v2',
                'steps' => [['type' => 'pageview', 'value' => '/', 'label' => null], ['type' => 'pageview', 'value' => '/pricing', 'label' => null]],
            ])
            ->assertRedirect($conversions);
        $this->assertSame('Signup v2', $funnel->refresh()->name);

        $this->actingAs($user)
            ->delete(route('app.project.analytics.funnels.destroy', $params + ['funnel' => $funnel->id]))
            ->assertRedirect($conversions);
        $this->assertSoftDeleted($funnel);
    }

    public function test_funnel_validation(): void
    {
        $user = User::factory()->create();
        $project = Project::find($this->site->project_id);
        $user->projects()->attach($project);
        $route = route('app.project.analytics.funnels.store', ['project' => $project->id, 'site' => $this->site->id]);

        $this->actingAs($user)->post($route, ['name' => 'x', 'steps' => [['type' => 'pageview', 'value' => '/a']]])->assertSessionHasErrors('steps');
        $this->actingAs($user)->post($route, ['name' => 'x', 'steps' => [['type' => 'click', 'value' => '/a'], ['type' => 'pageview', 'value' => '']]])
            ->assertSessionHasErrors(['steps.0.type', 'steps.1.value']);
        $this->actingAs($user)->post($route, ['name' => 'x', 'steps' => array_fill(0, Funnel::MAX_STEPS + 1, ['type' => 'pageview', 'value' => '/a'])])
            ->assertSessionHasErrors('steps');

        $this->assertSame(0, Funnel::count());
    }

    public function test_foreign_site_funnels_are_not_accessible(): void
    {
        $user = User::factory()->create();
        $own = Project::create(['name' => 'Own']);
        $user->projects()->attach($own);
        $funnel = $this->checkout();

        $this->actingAs($user)
            ->get(route('app.project.analytics.funnels.edit', ['project' => $own->id, 'site' => $this->site->id, 'funnel' => $funnel->id]))
            ->assertNotFound();
    }

    public function test_conversions_tab_lists_funnel_reports(): void
    {
        $user = User::factory()->create();
        $project = Project::find($this->site->project_id);
        $user->projects()->attach($project);
        $this->checkout();
        $this->visitWith(['/products/a', '/cart']);

        $this->actingAs($user)
            ->get(route('app.project.analytics.show', ['project' => $project->id, 'site' => $this->site->id, 'tab' => 'conversions']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('funnels', 1)
                ->where('funnels.0.name', 'Checkout')
                ->where('funnels.0.entered', 1)
                ->has('funnels.0.steps', 3)
            );
    }
}
