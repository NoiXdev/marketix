<?php

namespace App\Jobs;

use App\Models\PageView;
use App\Models\Visit;
use App\Support\VisitResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RecordEngagementJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const MAX_SECONDS_PER_PAGE = 6 * 3600;

    public function __construct(
        private string $siteId,
        private string $visitorHash,
        private string $path,
        private int $engagedSeconds,
        private ?int $scrollDepth,
    ) {}

    public function handle(): void
    {
        $visit = Visit::query()
            ->where('site_id', $this->siteId)
            ->where('visitor_hash', $this->visitorHash)
            ->where('last_activity_at', '>=', now()->subMinutes(VisitResolver::SESSION_WINDOW_MINUTES))
            ->latest('last_activity_at')
            ->first();

        if ($visit === null) {
            return;
        }

        $pageView = PageView::query()
            ->where('visit_id', $visit->id)
            ->where('path', $this->path)
            ->latest('created_at')
            ->first();

        if ($pageView !== null) {
            $pageView->update([
                'engaged_seconds' => min(($pageView->engaged_seconds ?? 0) + $this->engagedSeconds, self::MAX_SECONDS_PER_PAGE),
                'scroll_depth' => $this->scrollDepth === null
                    ? $pageView->scroll_depth
                    : max($pageView->scroll_depth ?? 0, $this->scrollDepth),
            ]);
        }

        if ($this->engagedSeconds > 0 && now()->gt($visit->last_activity_at)) {
            $visit->update(['last_activity_at' => now()]);
        }
    }
}
