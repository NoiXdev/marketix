<?php

namespace App\Http\Controllers;

use App\Enums\GoalType;
use App\Http\Requests\GoalRequest;
use App\Services\RevenueAggregator;
use App\Support\Analytics\AnalyticsQuery;
use Illuminate\Http\Request;

class GoalController extends Controller
{
    private function site(Request $request, string $site)
    {
        return $request->get('project')->sites()->findOrFail($site);
    }

    public function index(Request $request, string $site)
    {
        $model = $this->site($request, $site);

        return inertia('Goals/Index', [
            'site' => ['id' => $model->id, 'name' => $model->name],
            'goals' => $model->goals()->latest()->get()->map(fn ($g) => [
                'id' => $g->id,
                'name' => $g->name,
                'type' => $g->type->value,
                'match_value' => $g->match_value,
                'conditions' => $g->conditions ?? [],
                'value' => $g->value,
                'currency' => $g->currency,
            ]),
        ]);
    }

    public function create(Request $request, RevenueAggregator $revenue, string $site)
    {
        $model = $this->site($request, $site);

        return inertia('Goals/Create', [
            'site' => ['id' => $model->id],
            'goalTypes' => GoalType::options(),
            // Prefill the goal value currency with the one the site's purchases use
            'defaultCurrency' => $revenue->currencies($model->id, AnalyticsQuery::lastDays(365))[0]['currency'] ?? null,
        ]);
    }

    public function store(GoalRequest $request, string $site)
    {
        $model = $this->site($request, $site);
        $model->goals()->create($request->validated() + ['project_id' => $model->project_id]);

        return redirect()->route('app.project.analytics.goals.index', ['project' => $model->project_id, 'site' => $model->id])
            ->with('success', __('app.goal_created'));
    }

    public function edit(Request $request, string $site, string $goal)
    {
        $model = $this->site($request, $site);
        $g = $model->goals()->findOrFail($goal);

        return inertia('Goals/Edit', [
            'site' => ['id' => $model->id],
            'goal' => [
                'id' => $g->id,
                'name' => $g->name,
                'type' => $g->type->value,
                'match_value' => $g->match_value,
                'conditions' => $g->conditions ?? [],
                'value' => $g->value,
                'currency' => $g->currency,
            ],
            'goalTypes' => GoalType::options(),
        ]);
    }

    public function update(GoalRequest $request, string $site, string $goal)
    {
        $model = $this->site($request, $site);
        $model->goals()->findOrFail($goal)->update($request->validated());

        return redirect()->route('app.project.analytics.goals.index', ['project' => $model->project_id, 'site' => $model->id])
            ->with('success', __('app.goal_updated'));
    }

    public function destroy(Request $request, string $site, string $goal)
    {
        $model = $this->site($request, $site);
        $model->goals()->findOrFail($goal)->delete();

        return redirect()->route('app.project.analytics.goals.index', ['project' => $model->project_id, 'site' => $model->id])
            ->with('success', __('app.goal_deleted'));
    }
}
