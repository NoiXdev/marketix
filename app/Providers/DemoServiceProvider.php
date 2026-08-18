<?php

namespace App\Providers;

use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class DemoServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if (! config('demo.enabled')) {
            return;
        }

        // Driver-independent backstop: cancel the send no matter what mailer any
        // code path selects. Returning false from a MessageSending listener aborts
        // transmission.
        Event::listen(MessageSending::class, fn () => false);

        // Demo-only wiring is registered here in later tasks.
    }
}
