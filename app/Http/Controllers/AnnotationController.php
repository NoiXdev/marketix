<?php

namespace App\Http\Controllers;

use App\Http\Requests\AnnotationRequest;
use App\Models\Site;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AnnotationController extends Controller
{
    private function site(Request $request, string $site): Site
    {
        return $request->get('project')->sites()->findOrFail($site);
    }

    private function backToAnalytics(Site $site, string $message): RedirectResponse
    {
        return redirect()
            ->back(fallback: route('app.project.analytics.show', ['project' => $site->project_id, 'site' => $site->id]))
            ->with('success', $message);
    }

    public function store(AnnotationRequest $request, string $site)
    {
        $model = $this->site($request, $site);
        $model->annotations()->create($request->validated() + [
            'project_id' => $model->project_id,
            'user_id' => $request->user()->id,
        ]);

        return $this->backToAnalytics($model, __('app.annotation_created'));
    }

    public function update(AnnotationRequest $request, string $site, string $annotation)
    {
        $model = $this->site($request, $site);
        $model->annotations()->findOrFail($annotation)->update($request->validated());

        return $this->backToAnalytics($model, __('app.annotation_updated'));
    }

    public function destroy(Request $request, string $site, string $annotation)
    {
        $model = $this->site($request, $site);
        $model->annotations()->findOrFail($annotation)->delete();

        return $this->backToAnalytics($model, __('app.annotation_deleted'));
    }
}
