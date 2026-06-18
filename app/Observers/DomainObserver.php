<?php

namespace App\Observers;

use App\Jobs\CheckDomainStatusJob;
use App\Jobs\RegenerateTraefikConfigJob;
use App\Models\Domain;

class DomainObserver
{
    public function creating(Domain $domain): void
    {
        RegenerateTraefikConfigJob::dispatch();
    }

    public function created(Domain $domain): void
    {
        CheckDomainStatusJob::dispatch($domain);
    }

    public function updating(Domain $domain): void
    {
        if ($domain->isDirty('name')) {
            RegenerateTraefikConfigJob::dispatch();
        }
    }

    public function deleted(Domain $domain): void
    {
        RegenerateTraefikConfigJob::dispatch();
    }

    public function forceDeleted(Domain $domain): void
    {
        RegenerateTraefikConfigJob::dispatch();
    }
}
