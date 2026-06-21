<?php

namespace App\Http\Controllers;

use App\Enums\UrlStatus;
use App\Http\Requests\UrlRequest;
use App\Models\Statistic;
use App\Services\StatisticsAggregator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class UrlController extends Controller
{
    use \App\Http\Controllers\Concerns\InteractsWithUrlSettings;

    public function index(Request $request)
    {
        $project = $request->get('project');

        return inertia('Links/Index', [
            'urls' => $project->urls()
                ->with('domain')
                ->latest()
                ->get()
                ->map(fn ($url) => [
                    'id' => $url->id,
                    'slug' => $url->slug,
                    'url' => $url->url,
                    'type' => $url->type->value,
                    'status' => $url->status->value,
                    'archived' => $url->archived,
                    'clicks' => $url->clicks,
                    'unique_clicks' => $url->unique_clicks,
                    'expired_at' => $url->expired_at?->toISOString(),
                    'created_at' => $url->created_at->toISOString(),
                    'domain' => $url->domain
                        ? ['id' => $url->domain->id, 'name' => $url->domain->name]
                        : null,
                ]),
        ]);
    }

    public function show(Request $request, StatisticsAggregator $stats, string $url)
    {
        $project = $request->get('project');
        $model = $project->urls()->with(['domain', 'qrCode'])->findOrFail($url);

        $days = (int) $request->input('days', 30);
        $days = in_array($days, [7, 30, 90], true) ? $days : 30;
        $since = now()->subDays($days - 1)->startOfDay();

        return inertia('Links/Show', [
            'link' => [
                'id' => $model->id,
                'slug' => $model->slug,
                'url' => $model->url,
                'type' => $model->type->value,
                'type_label' => $model->type->label(),
                'status' => $model->status->value,
                'clicks' => $model->clicks,
                // Distinct-IP over all time so this matches the range "unique"
                // card's semantics (rangeUnique) — both count unique IPs, just
                // over different windows. (The denormalized urls.unique_clicks
                // counter uses a 24h-dedup rule and is not comparable.)
                'unique_clicks' => $stats->uniqueClicks($project->id, $model->id),
                'expired_at' => $model->expired_at?->toISOString(),
                'created_at' => $model->created_at->toISOString(),
                'has_qr_code' => $model->qrCode !== null,
                'domain' => $model->domain
                    ? ['id' => $model->domain->id, 'name' => $model->domain->name]
                    : null,
            ],
            'days' => $days,
            'rangeClicks' => $stats->totalClicks($project->id, $model->id, $since),
            'rangeUnique' => $stats->uniqueClicks($project->id, $model->id, $since),
            'clicksByDay' => $stats->clicksByDay($project->id, $model->id, $days),
            'topCountries' => $stats->breakdown($project->id, $model->id, 'country', $since),
            'topCities' => $stats->breakdown($project->id, $model->id, 'city', $since),
            'topBrowsers' => $stats->breakdown($project->id, $model->id, 'browser', $since),
            'topOs' => $stats->breakdown($project->id, $model->id, 'os', $since),
            'topReferrers' => $stats->breakdown($project->id, $model->id, 'domain', $since),
            'recentClicks' => $stats->recentClicks($project->id, $model->id, $since),
        ]);
    }

    public function create(Request $request)
    {
        $project = $request->get('project');

        return inertia('Links/Create', [
            'domains' => $project->domains()->get(['id', 'name']),
            'pixels' => $project->pixels()->get(['id', 'name', 'provider']),
        ]);
    }

    public function store(UrlRequest $request)
    {
        $project = $request->get('project');
        $validated = $request->validated();
        $pixelIds = $request->input('pixel_ids', []);

        $url = $project->urls()->create($validated);
        $this->syncUrlPixels($url, $pixelIds);

        return redirect()->route('app.project.links.index')
            ->with('success', 'Link created.');
    }

    public function edit(Request $request, string $url)
    {
        $project = $request->get('project');
        $model = $project->urls()->with(['domain', 'pixels'])->findOrFail($url);

        return inertia('Links/Edit', [
            'url' => [
                'id' => $model->id,
                'domain_id' => $model->domain_id,
                'slug' => $model->slug,
                'url' => $model->url,
                'type' => $model->type->value,
                'status' => $model->status->value,
                // Password is write-only — never expose the stored hash. Leave blank to keep it.
                'password' => '',
                'has_password' => filled($model->password),
                'expired_at' => $model->expired_at?->format('Y-m-d\TH:i'),
                'archived' => $model->archived,
                'targeting_geo' => $model->targeting_geo ?? [],
                'targeting_device' => $model->targeting_device ?? [],
                'targeting_language' => $model->targeting_language ?? [],
                'targeting_ab' => $model->targeting_ab ?? [],
                'pixel_ids' => $model->pixels->pluck('id')->toArray(),
            ],
            'domains' => $project->domains()->get(['id', 'name']),
            'pixels' => $project->pixels()->get(['id', 'name', 'provider']),
            'history' => Inertia::optional(
                fn () => $model->activitiesAsSubject()->with('causer')->latest('id')->limit(50)->get()->map->toFeedArray()
            ),
        ]);
    }

    public function update(UrlRequest $request, string $url)
    {
        $project = $request->get('project');
        $model = $project->urls()->findOrFail($url);
        $validated = $request->validated();
        $pixelIds = $request->input('pixel_ids', []);

        $validated = $this->dropBlankPassword($validated);

        $model->update($validated);
        $this->syncUrlPixels($model, $pixelIds);

        return redirect()->route('app.project.links.index')
            ->with('success', 'Link updated.');
    }

    public function toggleStatus(Request $request, string $url)
    {
        $project = $request->get('project');
        $model = $project->urls()->findOrFail($url);

        $model->status = $model->status === UrlStatus::ACTIVATED
            ? UrlStatus::DEACTIVATED
            : UrlStatus::ACTIVATED;
        $model->save();

        return back()->with('success', 'Status updated.');
    }

    public function destroy(Request $request, string $url)
    {
        $project = $request->get('project');
        $project->urls()->findOrFail($url)->delete();

        return redirect()->route('app.project.links.index')
            ->with('success', 'Link deleted.');
    }

    public function resetStats(Request $request, string $url)
    {
        $project = $request->get('project');
        $model = $project->urls()->findOrFail($url);

        DB::transaction(function () use ($model, $request) {
            Statistic::where('url_id', $model->id)->forceDelete();
            $model->update(['clicks' => 0, 'unique_clicks' => 0]);

            activity('url')
                ->performedOn($model)
                ->causedBy($request->user())
                ->event('stats_reset')
                ->log('Stats reset');
        });

        return back()->with('success', 'Statistics reset.');
    }
}
