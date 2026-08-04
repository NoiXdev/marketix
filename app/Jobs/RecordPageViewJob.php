<?php

namespace App\Jobs;

use App\Models\PageView;
use App\Models\Visit;
use App\Support\CrawlerDetector;
use App\Support\UserAgent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Records a single page view off the request hot-path and maintains its Visit
 * (session). Geo and visitor hash are resolved on the request and passed in,
 * so the raw IP never reaches the queue.
 */
class RecordPageViewJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const SESSION_WINDOW_MINUTES = 30;

    /**
     * @param  array<string, mixed>  $geo
     */
    public function __construct(
        private string $siteId,
        private string $projectId,
        private string $visitorHash,
        private string $userAgent,
        private string $path,
        private ?string $referer,
        private ?string $language,
        private array $geo,
    ) {}

    public function handle(): void
    {
        $isBot = CrawlerDetector::isBot($this->userAgent);
        $browser = UserAgent::browser($this->userAgent);
        $os = UserAgent::os($this->userAgent);
        $device = UserAgent::device($this->userAgent);
        $refererDomain = $this->referer ? parse_url($this->referer, PHP_URL_HOST) : null;

        $now = now();
        $windowStart = $now->copy()->subMinutes(self::SESSION_WINDOW_MINUTES);

        $visit = Visit::where('site_id', $this->siteId)
            ->where('visitor_hash', $this->visitorHash)
            ->where('last_activity_at', '>=', $windowStart)
            ->latest('last_activity_at')
            ->first();

        if ($visit === null) {
            $visit = Visit::create([
                'site_id' => $this->siteId,
                'project_id' => $this->projectId,
                'visitor_hash' => $this->visitorHash,
                'started_at' => $now,
                'last_activity_at' => $now,
                'pageview_count' => 1,
                'entry_path' => $this->path,
                'exit_path' => $this->path,
                'country_code' => $this->geo['country_code'] ?? null,
                'browser' => $browser,
                'os' => $os,
                'device' => $device,
                'referer_domain' => $refererDomain,
                'is_bot' => $isBot,
            ]);
        } else {
            $visit->update([
                'last_activity_at' => $now,
                'exit_path' => $this->path,
                'pageview_count' => $visit->pageview_count + 1,
            ]);
        }

        PageView::create([
            'visit_id' => $visit->id,
            'site_id' => $this->siteId,
            'project_id' => $this->projectId,
            'visitor_hash' => $this->visitorHash,
            'path' => $this->path,
            'referer' => $this->referer,
            'referer_domain' => $refererDomain,
            'country' => $this->geo['country'] ?? null,
            'country_code' => $this->geo['country_code'] ?? null,
            'city' => $this->geo['city'] ?? null,
            'browser' => $browser,
            'os' => $os,
            'device' => $device,
            'language' => $this->language,
            'is_bot' => $isBot,
            'created_at' => $now,
        ]);
    }
}
