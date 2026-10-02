<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class UpdateChecker
{
    public const CACHE_KEY = 'update-checker.latest-release';

    public const CACHE_TTL_SECONDS = 4 * 60 * 60;

    public function currentVersion(): string
    {
        return json_decode(file_get_contents(base_path('package.json')))->version;
    }

    /**
     * @return array{version: string, url: string}|null
     */
    public function availableUpdate(): ?array
    {
        if (! config('services.github.update_check')) {
            return null;
        }

        $latest = $this->latestRelease();

        if ($latest === null || ! version_compare($latest['version'], $this->currentVersion(), '>')) {
            return null;
        }

        return $latest;
    }

    /**
     * @return array{version: string, url: string}|null
     */
    public function latestRelease(): ?array
    {
        $release = Cache::remember(self::CACHE_KEY, self::CACHE_TTL_SECONDS, fn () => $this->fetchLatestRelease() ?? false);

        return $release ?: null;
    }

    /**
     * @return array{version: string, url: string}|null
     */
    private function fetchLatestRelease(): ?array
    {
        $repository = config('services.github.repository');

        try {
            $response = Http::acceptJson()
                ->timeout(3)
                ->get("https://api.github.com/repos/{$repository}/releases/latest");
        } catch (Throwable) {
            return null;
        }

        $tag = $response->json('tag_name');

        if (! $response->successful() || ! is_string($tag)) {
            return null;
        }

        return [
            'version' => ltrim($tag, 'vV'),
            'url' => $response->json('html_url') ?? "https://github.com/{$repository}/releases/latest",
        ];
    }
}
