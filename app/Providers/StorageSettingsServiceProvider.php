<?php

namespace App\Providers;

use App\Settings\StorageSettings;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Laravel\Octane\Events\RequestReceived;
use Throwable;

class StorageSettingsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->apply();

        // Re-apply before every queued job: the long-lived `database` queue
        // worker would otherwise keep stale disk config after an admin saves.
        Event::listen(JobProcessing::class, fn () => $this->apply());

        // Re-apply per Octane/FrankenPHP web request, where the worker persists
        // across requests. Under plain php-fpm this listener simply never fires.
        Event::listen(RequestReceived::class, fn () => $this->apply());
    }

    public function apply(): void
    {
        try {
            // Force a fresh load: under Octane/queue the container persists and
            // would return a stale cached singleton.
            $settings = $this->app->make(StorageSettings::class)->refresh();

            if ($settings->driver === 's3') {
                config([
                    'filesystems.disks.s3.key' => $settings->s3_key,
                    'filesystems.disks.s3.secret' => $settings->s3_secret,
                    'filesystems.disks.s3.region' => $settings->s3_region,
                    'filesystems.disks.s3.bucket' => $settings->s3_bucket,
                    'filesystems.disks.s3.endpoint' => $settings->s3_endpoint ?: null,
                    'filesystems.disks.s3.use_path_style_endpoint' => $settings->s3_use_path_style,
                    'filesystems.default' => 's3',
                ]);
            }
            // driver === 'local' → leave filesystems.default as configured in env.
        } catch (Throwable) {
            // Settings unavailable (table not migrated, no DB, etc.) — fall back
            // to .env/config defaults.
        }
    }
}
