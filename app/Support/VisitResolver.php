<?php

namespace App\Support;

use App\Models\Event;
use App\Models\PageView;
use App\Models\Visit;
use Illuminate\Support\Facades\DB;

class VisitResolver
{
    public const SESSION_WINDOW_MINUTES = 30;

    /**
     * Return the visitor's open session within the window, or create a new Visit.
     * First-touch fields are applied ONLY when a new Visit is created. A new
     * Visit is returning when the visitor had any earlier one on the site.
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

        return Visit::create([
            'site_id' => $siteId,
            'project_id' => $projectId,
            'visitor_hash' => $visitorHash,
            'started_at' => $now,
            'last_activity_at' => $now,
            'pageview_count' => 0,
            'entry_path' => $path,
            'exit_path' => $path,
            'country_code' => $firstTouch['country_code'] ?? null,
            'browser' => $firstTouch['browser'] ?? null,
            'os' => $firstTouch['os'] ?? null,
            'device' => $firstTouch['device'] ?? null,
            'referer_domain' => $firstTouch['referer_domain'] ?? null,
            'is_bot' => $isBot,
            'is_returning' => $latest !== null,
            'utm_source' => $firstTouch['utm_source'] ?? null,
            'utm_medium' => $firstTouch['utm_medium'] ?? null,
            'utm_campaign' => $firstTouch['utm_campaign'] ?? null,
            'utm_term' => $firstTouch['utm_term'] ?? null,
            'utm_content' => $firstTouch['utm_content'] ?? null,
        ]);
    }

    /**
     * Move the open anonymous session over to the visitor id once the visitor
     * consents, so the page that asked for consent does not start a second visit.
     */
    public function adopt(string $siteId, string $anonymousHash, string $visitorHash): ?Visit
    {
        if ($this->openVisit($siteId, $visitorHash) !== null) {
            return null;
        }

        $visit = $this->openVisit($siteId, $anonymousHash);
        if ($visit === null) {
            return null;
        }

        $returning = Visit::where('site_id', $siteId)->where('visitor_hash', $visitorHash)->exists();

        DB::transaction(function () use ($visit, $visitorHash, $returning) {
            $visit->update(['visitor_hash' => $visitorHash, 'is_returning' => $returning]);
            PageView::where('visit_id', $visit->id)->update(['visitor_hash' => $visitorHash]);
            Event::where('visit_id', $visit->id)->update(['visitor_hash' => $visitorHash]);
        });

        return $visit;
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
