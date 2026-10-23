<?php

namespace App\Support\Privacy;

use App\Enums\ConsentMode;
use App\Enums\TrackingMode;

/**
 * What a single Site actually does, as reported by ProcessingSnapshot.
 * A plain, immutable value object — no query logic belongs here.
 */
final class SiteProcessing
{
    public function __construct(
        public readonly string $domain,
        public readonly TrackingMode $trackingMode,
        public readonly ConsentMode $consentMode,
        public readonly bool $respectsDnt,
        public readonly bool $tracksOutboundLinks,
        public readonly bool $tracksFileDownloads,
        public readonly bool $tracksContactLinks,
        public readonly bool $tracksSiteSearch,
        public readonly int $retentionDays,
    ) {}
}
