<?php

namespace App\Jobs;

use App\Models\Statistic;
use App\Models\Url;
use App\Support\UserAgent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Records a single click off the request hot-path.
 *
 * Geo lookup is resolved on the request (it is also needed for geo targeting),
 * so the already-computed array is passed in rather than re-resolved here.
 */
class RecordClickStatisticJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @param  array<string, mixed>  $geo
     */
    public function __construct(
        private string $urlId,
        private string $projectId,
        private ?string $ip,
        private string $userAgent,
        private ?string $referer,
        private ?string $language,
        private array $geo,
    ) {}

    public function handle(): void
    {
        $isUnique = ! Statistic::where('url_id', $this->urlId)
            ->where('ip', $this->ip)
            ->where('created_at', '>=', now()->subDay())
            ->exists();

        Statistic::create([
            'project_id' => $this->projectId,
            'url_id' => $this->urlId,
            'ip' => $this->ip,
            'country' => $this->geo['country'] ?? null,
            'city' => $this->geo['city'] ?? null,
            'language' => $this->language,
            'domain' => $this->referer ? parse_url($this->referer, PHP_URL_HOST) : null,
            'referer' => $this->referer,
            'browser' => UserAgent::browser($this->userAgent),
            'os' => UserAgent::os($this->userAgent),
        ]);

        // Atomic DB-side increments — avoids the read-modify-write race of $model->increment().
        Url::whereKey($this->urlId)->increment('clicks');
        if ($isUnique) {
            Url::whereKey($this->urlId)->increment('unique_clicks');
        }
    }
}
