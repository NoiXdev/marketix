<?php

namespace App\Observers;

use App\Crawler\IssueCode;
use App\Crawler\PageAnalyzer;
use App\Crawler\PageContext;
use App\Models\Crawl;
use App\Models\CrawlPage;
use GuzzleHttp\Exception\RequestException;
use Spatie\Crawler\CrawlObservers\CrawlObserver;
use Spatie\Crawler\CrawlProgress;
use Spatie\Crawler\CrawlResponse;
use Spatie\Crawler\Enums\ResourceType;
use Spatie\Crawler\TransferStatistics;

class CrawlPageObserver extends CrawlObserver
{
    public function __construct(
        private Crawl $crawl,
        private PageAnalyzer $analyzer,
        private string $baseHost,
    ) {}

    public function crawled(
        string $url,
        CrawlResponse $response,
        CrawlProgress $progress,
    ): void {
        $this->recordResponse(
            $url,
            $response->status(),
            $response->headers(),
            $response->body(),
            $response->transferStats()?->transferTimeInMs() ?? 0.0,
        );
    }

    public function crawlFailed(
        string $url,
        RequestException $requestException,
        CrawlProgress $progress,
        ?string $foundOnUrl = null,
        ?string $linkText = null,
        ?ResourceType $resourceType = null,
        ?TransferStatistics $transferStats = null,
    ): void {
        $status = $requestException->getResponse()?->getStatusCode();
        $page = $this->crawl->pages()->create([
            'url' => $url,
            'status_code' => $status,
            'issues' => [$status && $status >= 400 && $status < 500 ? IssueCode::ClientError->value : IssueCode::ServerError->value],
        ]);
        $this->crawl->increment('pages_crawled');
        // no links to record for a failed fetch
        unset($page);
    }

    /** @param array<string, string[]> $headers */
    public function recordResponse(string $url, int $status, array $headers, string $body, float $responseMs): CrawlPage
    {
        $ctx = new PageContext($url, $status, $this->baseHost);
        $analysis = $this->analyzer->analyze($body, $ctx);

        $issues = array_map(fn (IssueCode $i) => $i->value, $analysis->issues);
        if ($status >= 500) {
            $issues[] = IssueCode::ServerError->value;
        } elseif ($status >= 400) {
            $issues[] = IssueCode::ClientError->value;
        }

        [$chain, $finalUrl] = $this->redirects($url, $headers);
        if (count($chain) > 1) {
            $issues[] = IssueCode::RedirectChain->value;
        }

        $links = $analysis->data['links'] ?? [];
        unset($analysis->data['links']);

        $page = $this->crawl->pages()->create(array_merge($analysis->data, [
            'url' => $url,
            'final_url' => $finalUrl,
            'status_code' => $status,
            'redirect_chain' => $chain ?: null,
            'content_type' => $headers['Content-Type'][0] ?? $headers['content-type'][0] ?? null,
            'response_time_ms' => (int) round($responseMs),
            'size_bytes' => strlen($body),
            'issues' => array_values(array_unique($issues)),
        ]));

        foreach ($links as $link) {
            $page->outLinks()->create([
                'crawl_id' => $this->crawl->id,
                'to_url' => $link['to_url'],
                'type' => $link['type'],
                'anchor' => $link['anchor'],
                'rel' => $link['rel'],
            ]);
        }

        $this->crawl->increment('pages_crawled');

        return $page;
    }

    /**
     * @param  array<string, string[]>  $headers
     * @return array{0: string[], 1: ?string}
     */
    private function redirects(string $url, array $headers): array
    {
        $history = $headers['X-Guzzle-Redirect-History'][0] ?? null;
        if ($history === null) {
            return [[], null];
        }
        $chain = array_merge([$url], array_map('trim', explode(',', $history)));
        $final = end($chain) ?: null;

        return [$chain, $final];
    }
}
