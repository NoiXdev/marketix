<?php

namespace App\Jobs;

use App\Models\Domain;
use App\Services\DomainStatusChecker;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CheckDomainStatusJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public Domain $domain) {}

    public function handle(DomainStatusChecker $checker): void
    {
        $result = $checker->check($this->domain);

        $this->domain->forceFill([
            'dns_ok' => $result['dns_ok'],
            'reachable_ok' => $result['reachable_ok'],
            'ssl_ok' => $result['ssl_ok'],
            'check_details' => $result['check_details'],
            'last_checked_at' => now(),
        ])->save();
    }
}
