<?php

namespace App\Jobs;

use App\Models\Event;
use App\Support\CrawlerDetector;
use App\Support\UserAgent;
use App\Support\VisitResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Records a custom event on the visitor's current session (via VisitResolver).
 * Events never increment pageview_count/entry/exit; they only extend the session.
 */
class RecordEventJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @param  array<string, mixed>|null  $props
     */
    public function __construct(
        private string $siteId,
        private string $projectId,
        private string $visitorHash,
        private string $userAgent,
        private string $name,
        private ?array $props,
        private string $path,
        private ?string $referer,
    ) {}

    public function handle(): void
    {
        $isBot = CrawlerDetector::isBot($this->userAgent);

        $visit = app(VisitResolver::class)->resolve(
            $this->siteId,
            $this->projectId,
            $this->visitorHash,
            $isBot,
            $this->path,
            [
                'country_code' => null,
                'browser' => UserAgent::browser($this->userAgent),
                'os' => UserAgent::os($this->userAgent),
                'device' => UserAgent::device($this->userAgent),
                'referer_domain' => $this->referer ? parse_url($this->referer, PHP_URL_HOST) : null,
            ],
        );

        $visit->update(['last_activity_at' => now()]);

        Event::create([
            'visit_id' => $visit->id,
            'site_id' => $this->siteId,
            'project_id' => $this->projectId,
            'visitor_hash' => $this->visitorHash,
            'name' => $this->name,
            'props' => $this->props,
            'path' => $this->path,
            'is_bot' => $isBot,
            'created_at' => now(),
        ]);
    }
}
