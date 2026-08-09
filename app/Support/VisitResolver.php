<?php

namespace App\Support;

use App\Models\Visit;

class VisitResolver
{
    public const SESSION_WINDOW_MINUTES = 30;

    /**
     * Return the visitor's open session within the window, or create a new Visit.
     * First-touch fields are applied ONLY when a new Visit is created.
     *
     * @param  array<string, ?string>  $firstTouch
     */
    public function resolve(string $siteId, string $projectId, string $visitorHash, bool $isBot, string $path, array $firstTouch): Visit
    {
        $now = now();
        $windowStart = $now->copy()->subMinutes(self::SESSION_WINDOW_MINUTES);

        $visit = Visit::where('site_id', $siteId)
            ->where('visitor_hash', $visitorHash)
            ->where('last_activity_at', '>=', $windowStart)
            ->latest('last_activity_at')
            ->first();

        if ($visit !== null) {
            return $visit;
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
            'utm_source' => $firstTouch['utm_source'] ?? null,
            'utm_medium' => $firstTouch['utm_medium'] ?? null,
            'utm_campaign' => $firstTouch['utm_campaign'] ?? null,
            'utm_term' => $firstTouch['utm_term'] ?? null,
            'utm_content' => $firstTouch['utm_content'] ?? null,
        ]);
    }
}
