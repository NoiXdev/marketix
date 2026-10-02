<?php

namespace App\Http\Controllers;

use App\Enums\WidgetType;
use App\Support\Widgets\WidgetRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WidgetDataController extends Controller
{
    public function show(Request $request, WidgetRegistry $registry): JsonResponse
    {
        $request->validate(['type' => ['required', 'string', 'in:'.implode(',', WidgetType::values())]]);
        $type = WidgetType::from($request->string('type'));

        $config = $request->validate($registry->configRules($type));

        $project = $request->get('project');

        return response()->json($registry->data($type, $project->id, $config));
    }
}
