<?php

use App\Jobs\CheckDomainStatusJob;
use App\Models\Domain;
use Illuminate\Support\Facades\Schedule;

Schedule::command('marketix:geoip:update')->daily();
Schedule::command('activitylog:clean')->daily();
Schedule::command('statistics:prune')->daily();
Schedule::command('analytics:prune')->daily();
Schedule::command('reports:dispatch-due')->hourly()->withoutOverlapping();

Schedule::call(function () {
    Domain::query()->each(fn (Domain $domain) => CheckDomainStatusJob::dispatch($domain));
})->everyFifteenMinutes()->name('domains:check-status')->withoutOverlapping();

// Demo instances rebuild themselves nightly. Guarded so a normal deployment
// never schedules a destructive command at all.
if (config('demo.enabled')) {
    Schedule::command('marketix:demo:reset')
        ->dailyAt(config('demo.reset_at'))
        ->withoutOverlapping()
        ->name('demo:reset');
}
