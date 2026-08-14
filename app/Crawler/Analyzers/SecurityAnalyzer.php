<?php

namespace App\Crawler\Analyzers;

use App\Crawler\AnalyzerResult;
use App\Crawler\IssueCode;
use App\Crawler\PageContext;
use Symfony\Component\DomCrawler\Crawler;

class SecurityAnalyzer implements Analyzer
{
    /** Subresource selectors → the attribute holding the URL. */
    private const RESOURCE_SELECTORS = [
        'img[src]' => 'src',
        'script[src]' => 'src',
        'link[rel="stylesheet"][href]' => 'href',
        'iframe[src]' => 'src',
        'audio[src]' => 'src',
        'video[src]' => 'src',
        'source[src]' => 'src',
        'object[data]' => 'data',
    ];

    public function analyze(Crawler $dom, PageContext $ctx): AnalyzerResult
    {
        $r = new AnalyzerResult;
        $h = $ctx->securityHeaders;
        $isHttps = $ctx->scheme === 'https';

        // Scheme (https_urls is an informational `info` signal).
        $r->issue($isHttps ? IssueCode::HttpsUrls : IssueCode::HttpUrls);

        // Header checks.
        if ($isHttps && ! isset($h['strict-transport-security'])) {
            $r->issue(IssueCode::MissingHstsHeader);
        }
        if (! isset($h['content-security-policy'])) {
            $r->issue(IssueCode::MissingCspHeader);
        }
        if (! isset($h['x-content-type-options'])) {
            $r->issue(IssueCode::MissingXContentTypeOptions);
        }
        $cspHasFrameAncestors = stripos($h['content-security-policy'] ?? '', 'frame-ancestors') !== false;
        if (! isset($h['x-frame-options']) && ! $cspHasFrameAncestors) {
            $r->issue(IssueCode::MissingXFrameOptions);
        }
        if (! isset($h['referrer-policy']) && $dom->filter('meta[name="referrer"]')->count() === 0) {
            $r->issue(IssueCode::MissingReferrerPolicy);
        }

        // Subresources: mixed content + protocol-relative.
        foreach ($this->resourceUrls($dom) as $url) {
            if (str_starts_with($url, '//')) {
                $r->issue(IssueCode::ProtocolRelativeResourceLinks);
            }
            if ($isHttps && preg_match('#^http://#i', $url)) {
                $r->issue(IssueCode::MixedContent);
            }
        }

        // Unsafe cross-origin target=_blank links.
        $dom->filter('a[href][target="_blank"]')->each(function (Crawler $a) use ($r, $ctx) {
            $href = trim($a->attr('href') ?? '');
            if (! preg_match('#^https?://#i', $href)) {
                return; // relative → same origin
            }
            $host = parse_url($href, PHP_URL_HOST) ?? '';
            if ($host === '' || $host === $ctx->baseHost) {
                return;
            }
            $rel = strtolower($a->attr('rel') ?? '');
            if (! str_contains($rel, 'noopener') && ! str_contains($rel, 'noreferrer')) {
                $r->issue(IssueCode::UnsafeCrossOriginLinks);
            }
        });

        // Forms.
        if (! $isHttps && $dom->filter('form')->count() > 0) {
            $r->issue(IssueCode::FormOnHttp);
        }
        $dom->filter('form[action]')->each(function (Crawler $f) use ($r) {
            if (preg_match('#^http://#i', trim($f->attr('action') ?? ''))) {
                $r->issue(IssueCode::FormUrlInsecure);
            }
        });

        return $r;
    }

    /** @return string[] */
    private function resourceUrls(Crawler $dom): array
    {
        $urls = [];
        foreach (self::RESOURCE_SELECTORS as $selector => $attr) {
            $dom->filter($selector)->each(function (Crawler $n) use (&$urls, $attr) {
                $v = trim($n->attr($attr) ?? '');
                if ($v !== '') {
                    $urls[] = $v;
                }
            });
        }

        return $urls;
    }
}
