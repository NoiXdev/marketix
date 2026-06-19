<?php

namespace App\Providers;

use App\Settings\BrandingSettings;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Laravel\Octane\Events\RequestReceived;
use Throwable;

class BrandingServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->applyBranding();

        // Re-apply per queued job and per Octane request — the worker/container
        // persists, so without this it would serve stale branding after a save.
        Event::listen(JobProcessing::class, fn () => $this->applyBranding());
        Event::listen(RequestReceived::class, fn () => $this->applyBranding());
    }

    /**
     * Test helper: re-run after saving settings mid-test.
     *
     * @internal Not for production use — tests only.
     */
    public function bootForTesting(): void
    {
        $this->applyBranding();
    }

    private function applyBranding(): void
    {
        try {
            $b = $this->app->make(BrandingSettings::class)->refresh();

            config(['app.name' => $b->appName()]);
            View::share('brandFaviconUrl', $b->faviconUrl());
            View::share('brandEmailLogoUrl', $b->emailLogoUrl());
        } catch (Throwable) {
            // Settings unavailable (table not migrated, no DB) — keep config/.env defaults.
        }
    }
}
