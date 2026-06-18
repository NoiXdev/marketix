<?php

namespace App\Providers;

use App\Services\CertificateReader;
use App\Services\DnsResolver;
use App\Services\SystemCertificateReader;
use App\Services\SystemDnsResolver;
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
    }
}
