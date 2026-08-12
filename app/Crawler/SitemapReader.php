<?php

namespace App\Crawler;

use Illuminate\Support\Facades\Http;

class SitemapReader
{
    /** @return string[] */
    public function urlsFor(string $startUrl): array
    {
        $parts = parse_url($startUrl);
        if (! isset($parts['scheme'], $parts['host'])) {
            return [];
        }
        $origin = $parts['scheme'].'://'.$parts['host'];

        return $this->fetch($origin.'/sitemap.xml', followIndex: true);
    }

    /** @return string[] */
    private function fetch(string $url, bool $followIndex): array
    {
        try {
            $response = Http::timeout(15)->get($url);
        } catch (\Throwable) {
            return [];
        }
        if (! $response->successful()) {
            return [];
        }

        $xml = @simplexml_load_string($response->body());
        if ($xml === false) {
            return [];
        }

        // sitemap index → follow children one level
        if (isset($xml->sitemap) && $followIndex) {
            $urls = [];
            foreach ($xml->sitemap as $child) {
                $urls = array_merge($urls, $this->fetch((string) $child->loc, followIndex: false));
            }

            return array_values(array_unique($urls));
        }

        $urls = [];
        foreach ($xml->url ?? [] as $entry) {
            $loc = trim((string) $entry->loc);
            if ($loc !== '') {
                $urls[] = $loc;
            }
        }

        return $urls;
    }
}
