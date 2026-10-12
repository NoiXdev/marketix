<?php

namespace App\Http\Controllers;

use App\Actions\MoveToProject;
use App\Http\Requests\MoveToProjectRequest;
use App\Models\Project;
use App\Models\QrTemplate;
use Illuminate\Http\RedirectResponse;

class MoveToProjectController extends Controller
{
    public function __construct(private MoveToProject $move) {}

    public function url(MoveToProjectRequest $request, string $url)
    {
        $model = $request->get('project')->urls()->findOrFail($url);
        $target = $request->targetProject();

        $this->move->url($model, $target, $request->targetDomain($model), $request->user());

        return $this->moved('app.project.links.index', $target);
    }

    public function qrCode(MoveToProjectRequest $request, string $qrCode)
    {
        $model = $request->get('project')->qrCodes()->with('url')->findOrFail($qrCode);
        $target = $request->targetProject();
        $domain = $model->url ? $request->targetDomain($model->url) : null;

        $this->move->qrCode($model, $target, $domain, $request->user());

        return $this->moved('app.project.qrcodes.index', $target);
    }

    public function domain(MoveToProjectRequest $request, string $domain)
    {
        $model = $request->get('project')->domains()->findOrFail($domain);
        $target = $request->targetProject();

        $this->move->domain($model, $target, $request->user());

        return $this->moved('app.project.domains.index', $target);
    }

    public function site(MoveToProjectRequest $request, string $site)
    {
        $model = $request->get('project')->sites()->findOrFail($site);
        $target = $request->targetProject();

        $this->move->site($model, $target, $request->user());

        return $this->moved('app.project.sites.index', $target);
    }

    public function pixel(MoveToProjectRequest $request, string $pixel)
    {
        $model = $request->get('project')->pixels()->findOrFail($pixel);
        $target = $request->targetProject();

        $this->move->pixel($model, $target, $request->user());

        return $this->moved('app.project.pixels.index', $target);
    }

    public function qrTemplate(MoveToProjectRequest $request, string $qrTemplate)
    {
        $model = QrTemplate::where('project_id', $request->get('project')->id)->findOrFail($qrTemplate);
        $target = $request->targetProject();

        $this->move->qrTemplate($model, $target);

        return $this->moved('app.project.qr-templates.index', $target);
    }

    private function moved(string $route, Project $target): RedirectResponse
    {
        return redirect()->route($route)->with('success', __('move.moved', ['project' => $target->name]));
    }
}
