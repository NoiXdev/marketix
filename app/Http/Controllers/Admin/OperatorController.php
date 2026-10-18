<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\OperatorSettingsRequest;
use App\Settings\OperatorSettings;

class OperatorController extends Controller
{
    public function edit(OperatorSettings $settings)
    {
        return inertia('Admin/Operator/Edit', [
            'agency_name' => $settings->agency_name,
            'agency_address' => $settings->agency_address,
            'hosting_provider' => $settings->hosting_provider,
            'hosting_location' => $settings->hosting_location,
        ]);
    }

    public function update(OperatorSettingsRequest $request, OperatorSettings $settings)
    {
        $settings->agency_name = $request->validated('agency_name') ?: null;
        $settings->agency_address = $request->validated('agency_address') ?: null;
        $settings->hosting_provider = $request->validated('hosting_provider') ?: null;
        $settings->hosting_location = $request->validated('hosting_location') ?: null;

        $settings->save();

        return redirect()->route('app.admin.operator.edit')->with('success', __('flash.admin.operator_saved'));
    }
}
