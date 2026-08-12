<?php

namespace App\Jobs;

use App\Crawler\PageAnalyzer;
use App\Crawler\UrlSafety;
use App\Enums\CrawlMode;
use App\Enums\CrawlStatus;
use App\Models\Crawl;
use App\Observers\CrawlPageObserver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;
use RuntimeException;
use Spatie\Browsershot\Browsershot;
use Spatie\Crawler\Crawler;
use Spatie\Crawler\CrawlProfiles\CrawlInternalUrls;
use Spatie\Crawler\JavaScriptRenderers\BrowsershotRenderer;
use Spatie\Crawler\JavaScriptRenderers\JavaScriptRenderer;

class RunCrawlJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800;

    public function __construct(public Crawl $crawl) {}

    public function handle(): void
    {
        $this->crawl->update(['status' => CrawlStatus::Running, 'started_at' => now()]);

        $host = parse_url($this->crawl->start_url, PHP_URL_HOST) ?? '';
        $observer = new CrawlPageObserver($this->crawl, new PageAnalyzer, $host);

        $this->buildCrawler($observer)->start();

        AggregateCrawlJob::dispatch($this->crawl);
    }

    protected function buildCrawler(CrawlPageObserver $observer): Crawler
    {
        $crawl = $this->crawl;

        $crawler = Crawler::create($crawl->start_url, [
            'allow_redirects' => [
                'track_redirects' => true,
                'on_redirect' => $this->guardRedirect(),
            ],
            'timeout' => 30,
            'connect_timeout' => 15,
        ])
            ->crawlProfile(new CrawlInternalUrls($crawl->start_url, includeSubdomains: $crawl->include_subdomains))
            ->concurrency(5)
            ->delay($crawl->delay_ms)
            ->addObserver($observer);

        if ($crawl->mode === CrawlMode::SinglePage) {
            $crawler->limit(1);
        } elseif ($crawl->max_pages !== null) {
            $crawler->limit($crawl->max_pages);
        }

        if ($crawl->render_js) {
            $crawler->executeJavaScript($this->jsRenderer());
        }

        if (! $crawl->respect_robots) {
            $crawler->ignoreRobots();
        }

        return $crawler;
    }

    /**
     * Headless-browser renderer for JS crawls. Chromium runs server-side (in a
     * container, typically as root), where it cannot use its sandbox — so we must
     * launch it with --no-sandbox, or every render fails with "No usable sandbox".
     * --disable-dev-shm-usage avoids Chrome crashing on large pages when the
     * container's /dev/shm is small.
     */
    protected function jsRenderer(): JavaScriptRenderer
    {
        $browsershot = (new Browsershot)
            ->noSandbox()
            ->addChromiumArguments(['disable-dev-shm-usage']);

        return new BrowsershotRenderer($browsershot);
    }

    /**
     * Guzzle `on_redirect` callback: re-validates every redirect hop's host against the
     * same private/reserved-IP logic as SafeCrawlUrl, so a public start URL cannot be
     * used to smuggle the crawler into fetching a private/internal address via a 30x
     * redirect (SSRF). Throwing here aborts following that redirect.
     */
    protected function guardRedirect(): callable
    {
        return function (RequestInterface $request, ResponseInterface $response, UriInterface $uri): void {
            $host = $uri->getHost();

            if (! UrlSafety::hostIsSafe($host)) {
                throw new RuntimeException("Refusing to follow redirect to unsafe host [{$host}].");
            }
        };
    }

    public function failed(\Throwable $e): void
    {
        $this->crawl->update([
            'status' => CrawlStatus::Failed,
            'error' => $e->getMessage(),
            'finished_at' => now(),
        ]);
    }
}
