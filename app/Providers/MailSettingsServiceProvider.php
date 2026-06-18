<?php

namespace App\Providers;

use App\Settings\MailSettings;
use Illuminate\Support\ServiceProvider;
use Throwable;

class MailSettingsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        try {
            $settings = $this->app->make(MailSettings::class);

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
            ]);
        } catch (Throwable) {
            // Settings unavailable (table not migrated yet, no DB, etc.) —
            // fall back to .env/config defaults.
        }
    }
}
