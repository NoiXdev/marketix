<?php

namespace App\Http\Controllers;

use App\Http\Requests\DomainRequest;
use Illuminate\Http\Request;

class DomainController extends Controller
{
    public function index(Request $request)
    {
        $project = $request->get('project');

        return inertia('Domains/Index', [
            'domains' => $project->domains()->latest()->get(),
        ]);
    }

    public function create()
    {
        return inertia('Domains/Create');
    }

    public function store(DomainRequest $request)
    {
        $project = $request->get('project');

        $project->domains()->create($request->validated());

        return redirect()->route('app.project.domains.index')
            ->with('success', 'Domain created.');
    }

    public function edit(Request $request, int $domain)
    {
        $project = $request->get('project');

        return inertia('Domains/Edit', [
            'domain' => $project->domains()->findOrFail($domain),
        ]);
    }

    public function update(DomainRequest $request, int $domain)
    {
        $project = $request->get('project');

        $model = $project->domains()->findOrFail($domain);

        $model->update($request->validated());

        return redirect()->route('app.project.domains.index')
            ->with('success', 'Domain updated.');
    }

    public function destroy(Request $request, int $domain)
    {
        $project = $request->get('project');

        $project->domains()->findOrFail($domain)->delete();

        return redirect()->route('app.project.domains.index')
            ->with('success', 'Domain deleted.');
    }
}
