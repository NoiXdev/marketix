<?php

namespace App\Http\Middleware;

use App\Settings\BrandingSettings;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user,
            ],
            'version' => json_decode(file_get_contents(base_path('package.json')))->version,
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'warning' => fn () => $request->session()->get('warning'),
            ],
            'branding' => $this->branding(),
        ];
    }

    /**
     * @return array<string, string|null>
     */
    private function branding(): array
    {
        try {
            $b = app(BrandingSettings::class);

            return [
                'appName' => $b->appName(),
                'logoLight' => $b->logoLightUrl(),
                'logoDark' => $b->logoDarkUrl(),
                'favicon' => $b->faviconUrl(),
            ];
        } catch (\Throwable) {
            // Settings table not migrated yet — fall back to defaults.
            return ['appName' => 'Marketix', 'logoLight' => null, 'logoDark' => null, 'favicon' => null];
        }
    }
}
