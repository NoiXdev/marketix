<?php

namespace App\Http\Controllers;

use App\Enums\ConsentMode;
use App\Enums\TrackingMode;
use App\Http\Requests\SiteRequest;
use Illuminate\Http\Request;

class SiteController extends Controller
{
    public function index(Request $request)
    {
        $project = $request->get('project');

        return inertia('Sites/Index', [
            'sites' => $project->sites()->latest()->get()->map(fn ($s) => [
                'id' => $s->id,
                'name' => $s->name,
                'domain' => $s->domain,
                'tracking_id' => $s->tracking_id,
                'tracking_mode' => $s->tracking_mode->value,
                'consent_mode' => $s->consent_mode->value,
                'created_at' => $s->created_at->toISOString(),
            ]),
        ]);
    }

    public function create(Request $request)
    {
        return inertia('Sites/Create', [
            'trackingModes' => TrackingMode::options(),
            'consentModes' => ConsentMode::options(),
        ]);
    }

    public function store(SiteRequest $request)
    {
        $project = $request->get('project');
        $project->sites()->create($request->validated());

        return redirect()->route('app.project.sites.index', ['project' => $project->id])
            ->with('success', __('app.site_created'));
    }

    public function edit(Request $request, string $site)
    {
        $project = $request->get('project');
        $model = $project->sites()->findOrFail($site);

        return inertia('Sites/Edit', [
            'site' => [
                'id' => $model->id,
                'name' => $model->name,
                'domain' => $model->domain,
                'tracking_id' => $model->tracking_id,
                'tracking_mode' => $model->tracking_mode->value,
                'consent_mode' => $model->consent_mode->value,
                'consent_signal' => $model->consent_signal,
                'respect_dnt' => (bool) $model->respect_dnt,
                'retention_days' => $model->retention_days,
            ],
            'trackingModes' => TrackingMode::options(),
            'consentModes' => ConsentMode::options(),
            'snippetUrl' => rtrim(config('app.url'), '/').'/mx.js',
        ]);
    }

    public function update(SiteRequest $request, string $site)
    {
        $project = $request->get('project');
        $model = $project->sites()->findOrFail($site);
        $model->update($request->validated());

        return redirect()->route('app.project.sites.index', ['project' => $project->id])
            ->with('success', __('app.site_updated'));
    }

    public function destroy(Request $request, string $site)
    {
        // Soft delete only: raw analytics (visits/page_views) are NOT cascaded
        // here. They are erased by the daily `analytics:prune` command once
        // they age past the site's (or project's) retention window.
        $project = $request->get('project');
        $project->sites()->findOrFail($site)->delete();

        return redirect()->route('app.project.sites.index', ['project' => $project->id])
            ->with('success', __('app.site_deleted'));
    }
}
