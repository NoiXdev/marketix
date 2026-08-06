<?php

namespace App\Http\Controllers;

use App\Http\Requests\QrTemplateRequest;
use App\Models\QrTemplate;
use Illuminate\Http\Request;

class QrTemplateController extends Controller
{
    // Templates are not a standalone Inertia page — they back a panel embedded
    // in the QR code editor (Task 10), so index/store/destroy are plain JSON
    // rather than inertia() responses, matching the pattern used elsewhere in
    // this app for supporting resources that aren't full-page views (e.g.
    // TwoFactorPasskeyController).

    public function index(Request $request)
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

    public function destroy(Request $request, string $qrTemplate)
    {
        $project = $request->get('project');

        $template = QrTemplate::where('project_id', $project->id)->findOrFail($qrTemplate);
        $template->delete();

        return response()->json(null, 204);
    }
}
