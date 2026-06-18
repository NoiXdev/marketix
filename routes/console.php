<?php

use App\Jobs\CheckDomainStatusJob;
use App\Models\Domain;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('geoip:update')->daily();

Schedule::call(function () {
    Domain::query()->each(fn (Domain $domain) => CheckDomainStatusJob::dispatch($domain));
})->everyFifteenMinutes()->name('domains:check-status')->withoutOverlapping();
