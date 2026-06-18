<?php

namespace App\Providers;

use App\Services\CertificateReader;
use App\Services\DnsResolver;
use App\Services\SystemCertificateReader;
use App\Services\SystemDnsResolver;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        URL::forceScheme('https');

        $this->app->bind(DnsResolver::class, SystemDnsResolver::class);
        $this->app->bind(CertificateReader::class, SystemCertificateReader::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        // The default ResetPassword notification builds its link via
        // route('password.reset'), which this app does not define — the reset
        // page lives at the custom-named app.auth.show-reset route, with the
        // email carried as a query param (read by PasswordResetController@showReset).
        ResetPassword::createUrlUsing(fn ($notifiable, string $token) => route('app.auth.show-reset', [
            'token' => $token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]));
    }
}
