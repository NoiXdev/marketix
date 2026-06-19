<?php

namespace App\Http\Controllers;

use App\Http\Requests\DomainRequest;
use App\Jobs\CheckDomainStatusJob;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DomainController extends Controller
{
    public function index(Request $request)
    {
        $project = $request->get('project');

        return inertia('Domains/Index', [
            'domains' => $project->domains()->latest()->get(),
            'appDomain' => config('app.domain'),
        ]);
    }

    public function create()
    {
        return inertia('Domains/Create', [
            'appDomain' => config('app.domain'),
        ]);
    }

    public function store(DomainRequest $request)
    {
        $project = $request->get('project');

        $project->domains()->create($request->validated());

        return redirect()->route('app.project.domains.index')
            ->with('success', 'Domain created.');
    }

    public function edit(Request $request, string $domain)
    {
        $project = $request->get('project');
        $model = $project->domains()->findOrFail($domain);

        return inertia('Domains/Edit', [
            'domain' => $model,
            'appDomain' => config('app.domain'),
            'history' => Inertia::optional(
                fn () => $model->activitiesAsSubject()->with('causer')->latest('id')->limit(50)->get()->map->toFeedArray()
            ),
        ]);
    }

    public function update(DomainRequest $request, string $domain)
    {
        $project = $request->get('project');

        $model = $project->domains()->findOrFail($domain);

        $model->update($request->validated());

        return redirect()->route('app.project.domains.index')
            ->with('success', 'Domain updated.');
    }

    public function check(Request $request, string $domain)
    {
        $project = $request->get('project');

        $model = $project->domains()->findOrFail($domain);

        CheckDomainStatusJob::dispatchSync($model);

        return redirect()->route('app.project.domains.index')
            ->with('success', 'Domain status refreshed.');
    }

    public function destroy(Request $request, string $domain)
    {
        $project = $request->get('project');

        $project->domains()->findOrFail($domain)->delete();

        return redirect()->route('app.project.domains.index')
            ->with('success', 'Domain deleted.');
    }
}
