<?php

namespace App\Crawler;

use Illuminate\Support\Facades\Http;

class LlmsTxtReader
{
    public function exists(string $startUrl): bool
    {
        $parts = parse_url($startUrl);
        if (! isset($parts['scheme'], $parts['host'])) {
            return false;
        }
        try {
            $response = Http::timeout(15)->get($parts['scheme'].'://'.$parts['host'].'/llms.txt');
        } catch (\Throwable) {
            return false;
        }

        return $response->successful() && trim($response->body()) !== '';
    }
}
