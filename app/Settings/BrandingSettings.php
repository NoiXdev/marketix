<?php

namespace App\Settings;

use Illuminate\Support\Facades\Storage;
use Spatie\LaravelSettings\Settings;

class BrandingSettings extends Settings
{
    public ?string $app_name = null;

    public ?string $logo_light_path = null;

    public ?string $logo_dark_path = null;

    public ?string $logo_email_path = null;

    public ?string $favicon_path = null;

    public static function group(): string
    {
        return 'branding';
    }

    public function appName(): string
    {
        return $this->app_name ?: 'Marketix';
    }

    public function logoLightUrl(): ?string
    {
        return $this->urlFor($this->logo_light_path);
    }

    public function logoDarkUrl(): ?string
    {
        return $this->urlFor($this->logo_dark_path);
    }

    public function emailLogoUrl(): ?string
    {
        return $this->urlFor($this->logo_email_path);
    }

    public function faviconUrl(): ?string
    {
        return $this->urlFor($this->favicon_path);
    }

    private function urlFor(?string $path): ?string
    {
        return $path ? Storage::disk()->url($path) : null;
    }
}
