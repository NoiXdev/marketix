<?php

use App\Jobs\CheckDomainStatusJob;
use App\Models\Domain;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Schedule::command('marketix:geoip:update')->daily();
Schedule::command('activitylog:clean')->daily();

Schedule::call(function () {
    Domain::query()->each(fn (Domain $domain) => CheckDomainStatusJob::dispatch($domain));
})->everyFifteenMinutes()->name('domains:check-status')->withoutOverlapping();
