<?php

namespace App\Http\Controllers;

use App\Enums\PixelProvider;
use App\Http\Controllers\Concerns\FiltersIndex;
use App\Http\Requests\PixelRequest;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PixelController extends Controller
{
    use FiltersIndex;

    public function index(Request $request)
    {
        $project = $request->get('project');
        $search = $this->search($request);

        return inertia('Pixels/Index', [
            'pixels' => $project->pixels()
                ->when($search, fn ($query) => $query->where(fn ($query) => $query
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('tag', 'like', "%{$search}%")))
                ->latest()
                ->paginate($this->perPage($request))
                ->withQueryString()
                ->through(fn ($p) => [
                    'id' => $p->id,
                    'provider' => $p->provider->value,
                    'name' => $p->name,
                    'tag' => $p->tag,
                    'created_at' => $p->created_at->toISOString(),
                ]),
            'filters' => $this->filters($request),
            'providers' => PixelProvider::options(),
        ]);
    }

    public function create()
    {
        return inertia('Pixels/Create', [
            'providers' => PixelProvider::options(),
        ]);
    }

    public function store(PixelRequest $request)
    {
        $project = $request->get('project');

        $project->pixels()->create($request->validated());

        return redirect()->route('app.project.pixels.index')
            ->with('success', __('flash.pixels.created'));
    }

    public function edit(Request $request, string $pixel)
    {
        $project = $request->get('project');
        $model = $project->pixels()->findOrFail($pixel);

        return inertia('Pixels/Edit', [
            'pixel' => [
                'id' => $model->id,
                'provider' => $model->provider->value,
                'name' => $model->name,
                'tag' => $model->tag,
            ],
            'providers' => PixelProvider::options(),
            'history' => Inertia::optional(
                fn () => $model->activitiesAsSubject()->with('causer')->latest('id')->limit(50)->get()->map->toFeedArray()
            ),
        ]);
    }

    public function update(PixelRequest $request, string $pixel)
    {
        $project = $request->get('project');
        $model = $project->pixels()->findOrFail($pixel);

        $model->update($request->validated());

        return redirect()->route('app.project.pixels.index')
            ->with('success', __('flash.pixels.updated'));
    }

    public function destroy(Request $request, string $pixel)
    {
        $project = $request->get('project');
        $project->pixels()->findOrFail($pixel)->delete();

        return redirect()->route('app.project.pixels.index')
            ->with('success', __('flash.pixels.deleted'));
    }
}
