<?php

namespace App\Crawler;

use Illuminate\Support\Facades\Http;

class RobotsTxtReader
{
    /** Major AI/answer-engine crawler user-agents. */
    private const AI_BOTS = [
        'GPTBot', 'OAI-SearchBot', 'ChatGPT-User', 'ClaudeBot', 'anthropic-ai', 'Claude-Web',
        'PerplexityBot', 'Google-Extended', 'CCBot', 'Bytespider', 'Applebot-Extended',
        'Amazonbot', 'Meta-ExternalAgent',
    ];

    /** AI bots that robots.txt disallows from the site root ("/"). @return string[] */
    public function blockedAiBots(string $startUrl): array
    {
        $parts = parse_url($startUrl);
        if (! isset($parts['scheme'], $parts['host'])) {
            return [];
        }
        try {
            $response = Http::timeout(15)->get($parts['scheme'].'://'.$parts['host'].'/robots.txt');
        } catch (\Throwable) {
            return [];
        }
        if (! $response->successful()) {
            return [];
        }

        $groups = $this->parse($response->body());
        $blocked = [];
        foreach (self::AI_BOTS as $bot) {
            $group = $groups[strtolower($bot)] ?? $groups['*'] ?? null;
            if ($group !== null && in_array('/', $group, true)) {
                $blocked[] = $bot;
            }
        }

        return $blocked;
    }

    /** @return array<string, string[]> user-agent (lower) => disallow paths */
    private function parse(string $text): array
    {
        $groups = [];
        $current = [];
        $afterRule = false;
        foreach (preg_split('/\r\n|\r|\n/', $text) ?: [] as $line) {
            $line = trim((string) preg_replace('/#.*$/', '', $line));
            if ($line === '' || ! str_contains($line, ':')) {
                continue;
            }
            [$field, $value] = array_map('trim', explode(':', $line, 2));
            $field = strtolower($field);
            if ($field === 'user-agent') {
                if ($afterRule) {
                    $current = [];
                    $afterRule = false;
                }
                $current[] = strtolower($value);
            } else {
                $afterRule = true;
                if ($field === 'disallow') {
                    foreach ($current as $ua) {
                        $groups[$ua][] = $value;
                    }
                }
            }
        }

        return $groups;
    }
}
