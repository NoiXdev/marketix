<?php

use App\Console\Commands\SendScheduledReports;
use App\Jobs\CheckDomainStatusJob;
use App\Models\Domain;
use Illuminate\Support\Facades\Schedule;

Schedule::command('marketix:geoip:update')->daily();
Schedule::command('activitylog:clean')->daily();

Schedule::call(function () {
    Domain::query()->each(fn (Domain $domain) => CheckDomainStatusJob::dispatch($domain));
})->everyFifteenMinutes()->name('domains:check-status')->withoutOverlapping();

Schedule::command(SendScheduledReports::class, ['--cadence' => 'daily'])
    ->dailyAt('08:00')->withoutOverlapping();
Schedule::command(SendScheduledReports::class, ['--cadence' => 'weekly'])
    ->weeklyOn(1, '08:00')->withoutOverlapping();
Schedule::command(SendScheduledReports::class, ['--cadence' => 'monthly'])
    ->monthlyOn(1, '08:00')->withoutOverlapping();
