<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class BrandingSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Access enforced by EnsureSuperAdmin.
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'app_name' => ['nullable', 'string', 'max:255'],

            // SVG allowed for logos — they're rendered via <img src>, where embedded
            // scripts don't execute. Common format for vector brand logos.
            'logo_light' => ['nullable', 'image:allow_svg', 'max:2048'],
            'logo_dark' => ['nullable', 'image:allow_svg', 'max:2048'],
            'logo_email' => ['nullable', 'image:allow_svg', 'max:2048'],
            // SVG intentionally excluded for the favicon — raster/ico only.
            'favicon' => ['nullable', 'file', 'mimes:ico,png,jpg,jpeg', 'max:2048'],

            'remove_logo_light' => ['nullable', 'boolean'],
            'remove_logo_dark' => ['nullable', 'boolean'],
            'remove_logo_email' => ['nullable', 'boolean'],
            'remove_favicon' => ['nullable', 'boolean'],
        ];
    }
}
