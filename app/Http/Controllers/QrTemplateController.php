<?php

namespace App\Http\Controllers;

use App\Http\Requests\QrTemplateRequest;
use App\Models\QrTemplate;
use Illuminate\Http\Request;

class QrTemplateController extends Controller
{
    // Mutations and the template list are plain JSON: they back both the panel
    // embedded in the QR code editor and the management page, which keep their
    // own client-side state instead of round-tripping through Inertia. Only
    // index() — the management page shell — is an inertia() response.

    public function index()
    {
        return inertia('QrCodes/Templates/Index');
    }

    public function list(Request $request)
    {
        $project = $request->get('project');

        return response()->json([
            'templates' => QrTemplate::where('project_id', $project->id)
                ->latest()
                ->get(['id', 'name', 'style']),
        ]);
    }

    public function store(QrTemplateRequest $request)
    {
        $project = $request->get('project');

        $template = QrTemplate::create([
            ...$request->validated(),
            'project_id' => $project->id,
        ]);

        return response()->json([
            'template' => $template->only(['id', 'name', 'style']),
        ], 201);
    }

    public function update(QrTemplateRequest $request, string $qrTemplate)
    {
        $template = $this->findInProject($request, $qrTemplate);

        // A template is a copy-on-apply preset: updating it never rewrites the
        // style already stored on existing QR codes.
        $template->update($request->validated());

        return response()->json([
            'template' => $template->only(['id', 'name', 'style']),
        ]);
    }

    public function destroy(Request $request, string $qrTemplate)
    {
        $this->findInProject($request, $qrTemplate)->delete();

        return response()->json(null, 204);
    }

    private function findInProject(Request $request, string $qrTemplate): QrTemplate
    {
        $project = $request->get('project');

        return QrTemplate::where('project_id', $project->id)->findOrFail($qrTemplate);
    }
}
