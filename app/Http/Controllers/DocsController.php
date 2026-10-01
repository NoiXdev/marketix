<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class DocsController extends Controller
{
    /**
     * Static "Data Privacy" help page. Content is rendered from translations;
     * the dynamic values below keep the retention figures in sync with config.
     */
    public function dataPrivacy(Request $request)
    {
        return inertia('Docs/DataPrivacy', [
            'appName' => config('app.name'),
            'statsMonths' => (int) config('statistics.retention_months'),
            'analyticsMonths' => (int) config('analytics.retention_months'),
        ]);
    }
}
