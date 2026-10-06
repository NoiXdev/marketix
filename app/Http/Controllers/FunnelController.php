<?php

namespace App\Http\Controllers;

use App\Enums\GoalType;
use App\Http\Requests\FunnelRequest;
use App\Models\Funnel;
use App\Models\Site;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class FunnelController extends Controller
{
    private function site(Request $request, string $site): Site
    {
        return $request->get('project')->sites()->findOrFail($site);
    }

    private function backToConversions(Site $site, string $message): RedirectResponse
    {
        return redirect()
            ->route('app.project.analytics.show', ['project' => $site->project_id, 'site' => $site->id, 'tab' => 'conversions'])
            ->with('success', $message);
    }

    public function create(Request $request, string $site)
    {
        $model = $this->site($request, $site);

        return inertia('Funnels/Create', [
            'site' => ['id' => $model->id, 'name' => $model->name],
            'stepTypes' => GoalType::options(),
            'limits' => ['min' => Funnel::MIN_STEPS, 'max' => Funnel::MAX_STEPS],
        ]);
    }

    public function store(FunnelRequest $request, string $site)
    {
        $model = $this->site($request, $site);
        $model->funnels()->create($request->validated() + ['project_id' => $model->project_id]);

        return $this->backToConversions($model, __('app.funnel_created'));
    }

    public function edit(Request $request, string $site, string $funnel)
    {
        $model = $this->site($request, $site);
        $record = $model->funnels()->findOrFail($funnel);

        return inertia('Funnels/Edit', [
            'site' => ['id' => $model->id, 'name' => $model->name],
            'funnel' => ['id' => $record->id, 'name' => $record->name, 'steps' => $record->steps],
            'stepTypes' => GoalType::options(),
            'limits' => ['min' => Funnel::MIN_STEPS, 'max' => Funnel::MAX_STEPS],
        ]);
    }

    public function update(FunnelRequest $request, string $site, string $funnel)
    {
        $model = $this->site($request, $site);
        $model->funnels()->findOrFail($funnel)->update($request->validated());

        return $this->backToConversions($model, __('app.funnel_updated'));
    }

    public function destroy(Request $request, string $site, string $funnel)
    {
        $model = $this->site($request, $site);
        $model->funnels()->findOrFail($funnel)->delete();

        return $this->backToConversions($model, __('app.funnel_deleted'));
    }
}
