<?php

namespace App\Jobs;

use App\Support\VisitResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Hybrid mode: the visitor consented after anonymous hits were recorded, so
 * their open session continues under the visitor id.
 */
class RecordConsentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private string $siteId,
        private string $anonymousHash,
        private string $visitorHash,
    ) {}

    public function handle(): void
    {
        app(VisitResolver::class)->adopt($this->siteId, $this->anonymousHash, $this->visitorHash);
    }
}
