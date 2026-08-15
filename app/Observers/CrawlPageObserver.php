<?php

namespace App\Observers;

use App\Crawler\IssueCode;
use App\Crawler\PageAnalyzer;
use App\Crawler\PageContext;
use App\Crawler\RedirectClassifier;
use App\Crawler\ResourceClassifier;
use App\Crawler\UrlChecker;
use App\Models\Crawl;
use App\Models\CrawlPage;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\TooManyRedirectsException;
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
        $tooMany = $requestException instanceof TooManyRedirectsException;
        $page = $this->crawl->pages()->create([
            'url' => $url,
            'status_code' => $status,
            'issues' => RedirectClassifier::failureIssues($status, $tooMany),
        ]);
        $this->crawl->increment('pages_crawled');
        unset($page);
    }

    /** @param array<string, string[]> $headers */
    public function recordResponse(string $url, int $status, array $headers, string $body, float $responseMs): CrawlPage
    {
        $contentType = $headers['Content-Type'][0] ?? $headers['content-type'][0] ?? null;
        $category = ResourceClassifier::categorize($contentType);
        $size = strlen($body);

        $lower = array_change_key_case($headers, CASE_LOWER);
        $securityHeaders = [];
        foreach (['content-security-policy', 'x-frame-options', 'x-content-type-options', 'strict-transport-security', 'referrer-policy', 'content-type'] as $key) {
            if (isset($lower[$key][0])) {
                $securityHeaders[$key] = $lower[$key][0];
            }
        }

        $sniffHtml = (bool) preg_match('#^\s*(<!doctype html|<html[\s>])#i', ltrim($body, "\xEF\xBB\xBF"));
        $declaredMedia = strtolower(trim(explode(';', (string) $contentType)[0]));
        $wrongContentType = $sniffHtml && ! in_array($declaredMedia, ['text/html', 'application/xhtml+xml'], true);

        $issues = [];
        if ($status >= 500) {
            $issues[] = IssueCode::ServerError->value;
        } elseif ($status >= 400) {
            $issues[] = IssueCode::ClientError->value;
        }

        [$chain, $finalUrl] = $this->redirects($url, $headers);
        $scheme = strtolower(parse_url($finalUrl ?: $url, PHP_URL_SCHEME) ?: 'https');

        $links = [];
        $resources = [];
        $data = [];

        if ($category === ResourceClassifier::HTML) {
            $ctx = new PageContext($url, $status, $this->baseHost, false, $securityHeaders, $scheme);
            $analysis = $this->analyzer->analyze($body, $ctx);
            foreach ($analysis->issues as $issue) {
                $issues[] = $issue->value;
            }
            $links = $analysis->data['links'] ?? [];
            unset($analysis->data['links']);
            $resources = $analysis->data['resources'] ?? [];
            unset($analysis->data['resources']);
            $data = $analysis->data;
        } else {
            // Non-HTML resource (image, PDF, media, …): the HTML/SEO analyzers make
            // no sense here. Record it as a file and flag only if it's too large for
            // the web. It is not an indexable page, which also keeps aggregation from
            // flagging it as an orphan or "not in sitemap".
            $data = ['is_indexable' => false];
            if ($sizeIssue = ResourceClassifier::sizeIssue($category, $size)) {
                $issues[] = $sizeIssue->value;
            }
            if ($category === ResourceClassifier::IMAGE && $size > 102400) {
                $issues[] = IssueCode::ImageOver100kb->value;
            }
        }

        if ($wrongContentType) {
            $issues[] = IssueCode::WrongContentType->value;
        }

        foreach (UrlChecker::issues($url) as $code) {
            $issues[] = $code;
        }

        foreach (RedirectClassifier::issues($chain, $lower, $body, $category === ResourceClassifier::HTML) as $code) {
            $issues[] = $code;
        }

        $page = $this->crawl->pages()->create(array_merge($data, [
            'url' => $url,
            'final_url' => $finalUrl,
            'status_code' => $status,
            'redirect_chain' => $chain ?: null,
            'content_type' => $contentType,
            'content_category' => $category,
            'response_time_ms' => (int) round($responseMs),
            'size_bytes' => $size,
            'issues' => array_values(array_unique($issues)),
            'security_headers' => $securityHeaders ?: null,
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

        foreach ($resources as $res) {
            $page->resources()->create([
                'crawl_id' => $this->crawl->id,
                'url' => $res['url'],
                'type' => $res['type'],
                'is_internal' => $res['is_internal'],
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
