<?php

namespace App\Providers;

use App\Settings\MailSettings;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Laravel\Octane\Events\RequestReceived;
use Throwable;

class MailSettingsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->applyMailSettings();

        // Re-apply before every queued job. Production runs a long-lived
        // `database` queue worker, so without this a worker keeps stale
        // config after an admin saves new mail settings.
        Event::listen(JobProcessing::class, fn () => $this->applyMailSettings());

        // Re-apply per Octane web request. Under Octane/FrankenPHP the worker
        // (and container) persists across requests, so boot() runs once and
        // would otherwise serve stale config. Under plain php-fpm the provider
        // re-boots each request, so this listener simply never fires there.
        Event::listen(RequestReceived::class, fn () => $this->applyMailSettings());
    }

    private function applyMailSettings(): void
    {
        try {
            // Force a fresh load: under Octane/queue the container persists and
            // would return a stale cached singleton. refresh() reloads values
            // from the repository (and clears spatie's locked-property cache).
            $settings = $this->app->make(MailSettings::class)->refresh();

            config([
                'mail.default' => $settings->default_mailer,
                'mail.from.address' => $settings->from_address,
                'mail.from.name' => $settings->from_name,
                'postal.domain' => $settings->postal_url,
                'postal.key' => $settings->postal_key,
                'mail.mailers.smtp.host' => $settings->smtp_host,
                'mail.mailers.smtp.port' => $settings->smtp_port,
                'mail.mailers.smtp.username' => $settings->smtp_username,
                'mail.mailers.smtp.password' => $settings->smtp_password,
                'mail.mailers.smtp.scheme' => $settings->smtp_scheme ?: null,
                // config/mail.php's smtp mailer reads `url` from MAIL_URL; when
                // set, Laravel ignores host/port. Null it so the admin's
                // host/port always win.
                'mail.mailers.smtp.url' => null,
            ]);
        } catch (Throwable) {
            // Settings unavailable (table not migrated yet, no DB, etc.) —
            // fall back to .env/config defaults.
        }
    }
}
