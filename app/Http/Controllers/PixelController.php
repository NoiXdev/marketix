<?php

namespace App\Http\Controllers;

use App\Enums\PixelProvider;
use App\Http\Requests\PixelRequest;
use Illuminate\Http\Request;

class PixelController extends Controller
{
    public function index(Request $request)
    {
        $project = $request->get('project');

        return inertia('Pixels/Index', [
            'pixels' => $project->pixels()->latest()->get()->map(fn ($p) => [
                'id' => $p->id,
                'provider' => $p->provider->value,
                'name' => $p->name,
                'tag' => $p->tag,
                'created_at' => $p->created_at->toISOString(),
            ]),
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
            ->with('success', 'Pixel created.');
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
        ]);
    }

    public function update(PixelRequest $request, string $pixel)
    {
        $project = $request->get('project');
        $model = $project->pixels()->findOrFail($pixel);

        $model->update($request->validated());

        return redirect()->route('app.project.pixels.index')
            ->with('success', 'Pixel updated.');
    }

    public function destroy(Request $request, string $pixel)
    {
        $project = $request->get('project');
        $project->pixels()->findOrFail($pixel)->delete();

        return redirect()->route('app.project.pixels.index')
            ->with('success', 'Pixel deleted.');
    }
}
