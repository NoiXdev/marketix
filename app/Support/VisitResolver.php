<?php

namespace App\Support;

use App\Models\Visit;
use Illuminate\Support\Facades\Cache;

class VisitResolver
{
    public const SESSION_WINDOW_MINUTES = 30;

    private const CAMPAIGN_SOURCE = ['entry_path', 'referer_domain', 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'];

    /**
     * Return the visitor's open session within the window, or create a new Visit.
     * First-touch fields are applied ONLY when a new Visit is created. A new
     * Visit is returning when the visitor had any earlier one on the site.
     * Right after consent, a new Visit keeps the anonymous visit's campaign
     * source instead of the page the visitor consented on.
     *
     * @param  array<string, ?string>  $firstTouch
     */
    public function resolve(string $siteId, string $projectId, string $visitorHash, bool $isBot, string $path, array $firstTouch): Visit
    {
        $now = now();
        $windowStart = $now->copy()->subMinutes(self::SESSION_WINDOW_MINUTES);

        $latest = Visit::where('site_id', $siteId)
            ->where('visitor_hash', $visitorHash)
            ->latest('last_activity_at')
            ->first();

        if ($latest !== null && $latest->last_activity_at->gte($windowStart)) {
            return $latest;
        }

        $source = $this->takeHandover($siteId, $visitorHash);
        $attribution = $source !== null
            ? $source->only(self::CAMPAIGN_SOURCE)
            : ['entry_path' => $path] + array_intersect_key($firstTouch, array_flip(self::CAMPAIGN_SOURCE));

        return Visit::create([
            'site_id' => $siteId,
            'project_id' => $projectId,
            'visitor_hash' => $visitorHash,
            'started_at' => $now,
            'last_activity_at' => $now,
            'pageview_count' => 0,
            'entry_path' => $attribution['entry_path'] ?? $path,
            'exit_path' => $path,
            'country_code' => $firstTouch['country_code'] ?? null,
            'browser' => $firstTouch['browser'] ?? null,
            'os' => $firstTouch['os'] ?? null,
            'device' => $firstTouch['device'] ?? null,
            'referer_domain' => $attribution['referer_domain'] ?? null,
            'is_bot' => $isBot,
            'is_returning' => $latest !== null,
            'utm_source' => $attribution['utm_source'] ?? null,
            'utm_medium' => $attribution['utm_medium'] ?? null,
            'utm_campaign' => $attribution['utm_campaign'] ?? null,
            'utm_term' => $attribution['utm_term'] ?? null,
            'utm_content' => $attribution['utm_content'] ?? null,
        ]);
    }

    /**
     * Hybrid mode: the visitor consented after anonymous hits. Remember which
     * anonymous visit was theirs until their next visit under the visitor id
     * starts, so only its campaign source carries over.
     */
    public function handOver(string $siteId, string $anonymousHash, string $visitorHash): void
    {
        Cache::put($this->handoverKey($siteId, $visitorHash), $anonymousHash, now()->addMinutes(self::SESSION_WINDOW_MINUTES));
    }

    public function visitBeforeConsent(string $siteId, string $visitorHash): ?Visit
    {
        $anonymousHash = Cache::get($this->handoverKey($siteId, $visitorHash));

        return is_string($anonymousHash) ? $this->openVisit($siteId, $anonymousHash) : null;
    }

    private function takeHandover(string $siteId, string $visitorHash): ?Visit
    {
        $anonymousHash = Cache::pull($this->handoverKey($siteId, $visitorHash));

        return is_string($anonymousHash) ? $this->openVisit($siteId, $anonymousHash) : null;
    }

    private function handoverKey(string $siteId, string $visitorHash): string
    {
        return "visit_handover:{$siteId}:{$visitorHash}";
    }

    private function openVisit(string $siteId, string $visitorHash): ?Visit
    {
        return Visit::where('site_id', $siteId)
            ->where('visitor_hash', $visitorHash)
            ->where('last_activity_at', '>=', now()->subMinutes(self::SESSION_WINDOW_MINUTES))
            ->latest('last_activity_at')
            ->first();
    }
}
