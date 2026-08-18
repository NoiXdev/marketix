<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class DemoServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if (! config('demo.enabled')) {
            return;
        }

        // Demo-only wiring is registered here in later tasks.
    }
}
