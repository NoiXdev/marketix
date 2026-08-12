<?php

namespace App\Crawler\Analyzers;

use App\Crawler\AnalyzerResult;
use App\Crawler\IssueCode;
use App\Crawler\PageContext;
use Symfony\Component\DomCrawler\Crawler;

class IndexabilityAnalyzer implements Analyzer
{
    public function analyze(Crawler $dom, PageContext $ctx): AnalyzerResult
    {
        $r = new AnalyzerResult;
        $indexable = true;
        $reason = null;

        $robotsNode = $dom->filter('head meta[name="robots"]');
        $robots = $robotsNode->count() ? strtolower($robotsNode->first()->attr('content') ?? '') : '';
        $noindex = str_contains($robots, 'noindex');

        $canonicalNode = $dom->filter('head link[rel="canonical"]');
        $canonical = $canonicalNode->count() ? $canonicalNode->first()->attr('href') : null;

        if ($ctx->robotsBlocked) {
            $indexable = false;
            $reason = 'robots_blocked';
            $r->issue(IssueCode::RobotsBlocked);
        } elseif ($noindex) {
            $indexable = false;
            $reason = 'noindex';
            $r->issue(IssueCode::Noindex);
        }

        if ($canonical !== null && $this->differs($canonical, $ctx->url)) {
            if ($reason === null) {
                $reason = 'canonicalised';
            }
            $r->issue(IssueCode::CanonicalMismatch);
        }

        $r->add('is_indexable', $indexable);
        $r->add('indexability_reason', $reason);

        return $r;
    }

    private function differs(string $canonical, string $url): bool
    {
        $norm = fn (string $u) => rtrim(parse_url($u, PHP_URL_PATH) ?? '/', '/') ?: '/';

        return $norm($canonical) !== $norm($url);
    }
}
