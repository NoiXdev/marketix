<?php

namespace App\Crawler;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Spatie\Browsershot\Browsershot;

/**
 * Captures desktop + mobile screenshots of a page with headless Chrome and stores
 * them on a filesystem disk. Screenshots are JPEG, viewport-sized (not full page)
 * to keep files small and predictable. SSRF-safe: a private/internal host is never
 * screenshotted.
 */
class PageScreenshotter
{
    private const DESKTOP_WIDTH = 1280;

    private const DESKTOP_HEIGHT = 800;

    private const MOBILE_WIDTH = 390;

    private const MOBILE_HEIGHT = 844;

    private const MOBILE_UA = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1';

    /**
     * Capture both viewports and store them under $pathPrefix on $disk. Returns the
     * stored path per variant (null when the host is unsafe or capture failed).
     *
     * @return array{desktop: ?string, mobile: ?string}
     */
    public function capture(string $url, string $disk, string $pathPrefix): array
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (! is_string($host) || ! UrlSafety::hostIsSafe($host)) {
            return ['desktop' => null, 'mobile' => null];
        }

        return [
            'desktop' => $this->shoot($url, $disk, "{$pathPrefix}-desktop.jpg", self::DESKTOP_WIDTH, self::DESKTOP_HEIGHT, null),
            'mobile' => $this->shoot($url, $disk, "{$pathPrefix}-mobile.jpg", self::MOBILE_WIDTH, self::MOBILE_HEIGHT, self::MOBILE_UA),
        ];
    }

    private function shoot(string $url, string $disk, string $path, int $width, int $height, ?string $mobileUserAgent): ?string
    {
        try {
            $browsershot = Browsershot::url($url)
                ->noSandbox()
                ->addChromiumArguments(['disable-dev-shm-usage'])
                ->windowSize($width, $height)
                ->setScreenshotType('jpeg', 70);

            if ($mobileUserAgent !== null) {
                $browsershot->userAgent($mobileUserAgent)->mobile()->touch();
            }

            Storage::disk($disk)->put($path, $browsershot->screenshot());

            return $path;
        } catch (\Throwable $e) {
            Log::warning('Screenshot capture failed.', ['url' => $url, 'path' => $path, 'error' => $e->getMessage()]);

            return null;
        }
    }
}
