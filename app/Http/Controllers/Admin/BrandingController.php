<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BrandingSettingsRequest;
use App\Settings\BrandingSettings;
use Illuminate\Support\Facades\Storage;

class BrandingController extends Controller
{
    /**
     * Map of upload field name => settings property holding the stored path.
     *
     * @var array<string, string>
     */
    private const IMAGE_FIELDS = [
        'logo_light' => 'logo_light_path',
        'logo_dark' => 'logo_dark_path',
        'logo_email' => 'logo_email_path',
        'favicon' => 'favicon_path',
    ];

    public function edit(BrandingSettings $settings)
    {
        return inertia('Admin/Branding/Edit', [
            'app_name' => $settings->app_name,
            'logo_light_url' => $settings->logoLightUrl(),
            'logo_dark_url' => $settings->logoDarkUrl(),
            'logo_email_url' => $settings->emailLogoUrl(),
            'favicon_url' => $settings->faviconUrl(),
        ]);
    }

    public function update(BrandingSettingsRequest $request, BrandingSettings $settings)
    {
        $settings->app_name = $request->validated('app_name') ?: null;

        foreach (self::IMAGE_FIELDS as $field => $property) {
            if ($request->boolean("remove_{$field}")) {
                $this->deleteIfPresent($settings->{$property});
                $settings->{$property} = null;

                continue;
            }

            if ($request->hasFile($field)) {
                $this->deleteIfPresent($settings->{$property});
                $settings->{$property} = $request->file($field)->storePublicly('branding');
            }
        }

        $settings->save();

        return redirect()->route('app.admin.branding.edit')->with('success', 'Branding saved.');
    }

    private function deleteIfPresent(?string $path): void
    {
        if ($path) {
            Storage::disk()->delete($path);
        }
    }
}
