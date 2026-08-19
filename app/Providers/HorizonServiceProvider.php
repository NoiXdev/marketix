<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        parent::boot();

        // Horizon::routeSmsNotificationsTo('15556667777');
        // Horizon::routeMailNotificationsTo('example@example.com');
        // Horizon::routeSlackNotificationsTo('slack-webhook-url', '#channel');
    }

    /**
     * Register the Horizon gate.
     *
     * This gate determines who can access Horizon in non-local environments.
     */
    protected function gate(): void
    {
        Gate::define('viewHorizon', function ($user = null) {
            // The demo account is a super admin by design, and no horizon.*
            // route is on DemoGuard's deny list — so without this, every
            // demo visitor could open the queue console (job payloads,
            // failed-job retry, monitoring writes), and retrying a stored
            // RunCrawlJob is a second route around the crawl quota.
            return $user->super_admin === true && ! config('demo.enabled');
        });
    }
}
