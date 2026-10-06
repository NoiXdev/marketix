<?php

namespace App\Http\Controllers;

use App\Enums\ConsentMode;
use App\Enums\TrackingMode;
use App\Http\Requests\SiteRequest;
use App\Services\SiteOverview;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SiteController extends Controller
{
    public function index(Request $request, SiteOverview $overview)
    {
        $project = $request->get('project');
        $sites = $project->sites()->latest()->get();

        return inertia('Sites/Index', [
            'sites' => $sites->map(fn ($s) => [
                'id' => $s->id,
                'name' => $s->name,
                'domain' => $s->domain,
                'tracking_id' => $s->tracking_id,
                'tracking_mode' => $s->tracking_mode->value,
                'tracking_mode_label' => $s->tracking_mode->label(),
                'consent_mode' => $s->consent_mode->value,
                'created_at' => $s->created_at->toISOString(),
            ]),
            'stats' => Inertia::defer(fn () => $overview->forSites($sites)),
        ]);
    }

    public function create(Request $request)
    {
        return inertia('Sites/Create', [
            'trackingModes' => TrackingMode::options(),
            'consentModes' => ConsentMode::selectableOptions(),
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
                'track_outbound_links' => (bool) $model->track_outbound_links,
                'track_file_downloads' => (bool) $model->track_file_downloads,
                'site_search_params' => $model->site_search_params,
                'retention_days' => $model->retention_days,
            ],
            'trackingModes' => TrackingMode::options(),
            'consentModes' => ConsentMode::selectableOptions(),
            'snippet' => $model->trackingSnippet(),
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
